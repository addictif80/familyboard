// ---- Barre rapide (mobile) : limite le nombre de modules cochables ----
document.addEventListener('DOMContentLoaded', () => {
    const group = document.querySelector('[data-quick-nav-group]');
    if (!group) return;
    const max = parseInt(group.dataset.max, 10) || 3;
    const hint = document.querySelector('[data-quick-nav-hint]');
    const boxes = () => Array.from(group.querySelectorAll('input[type="checkbox"]'));

    const refresh = () => {
        const checked = boxes().filter(b => b.checked).length;
        boxes().forEach(b => { if (!b.checked) b.disabled = checked >= max; });
        if (hint) hint.textContent = checked >= max
            ? `${max} sélectionnés sur ${max} maximum.`
            : `${checked}/${max} sélectionnés.`;
    };
    group.addEventListener('change', refresh);
    refresh();
});

// ---- Déconnexion de tous les appareils ----
async function confirmLogoutAllDevices() {
    const ok = await Dialog.confirm('Déconnecter tous les appareils, y compris celui-ci ? Vous devrez vous reconnecter.');
    if (!ok) return;
    const r = await apiFetch(BASE_URL + '/settings/logout-all-devices', { method: 'POST' });
    if (!r.success) {
        Dialog.toast(r.error || 'Erreur.', 'error');
        return;
    }
    window.location.href = BASE_URL + '/logout';
}

// ---- Double authentification (2FA) ----
function tfaShowStatus(mode) {
    ['none', 'totp', 'email'].forEach(m => {
        const el = document.getElementById('tfa-status-' + m);
        if (el) el.style.display = m === mode ? 'block' : 'none';
    });
    document.getElementById('tfa-totp-setup').style.display = 'none';
}

async function startTfaTotpSetup() {
    const r = await apiFetch(BASE_URL + '/settings/2fa/totp/start', { method: 'POST' });
    if (!r.success) {
        Dialog.toast(r.error || 'Erreur.', 'error');
        return;
    }
    document.getElementById('tfa-totp-secret').textContent = r.secret;
    document.getElementById('tfa-totp-code').value = '';
    document.getElementById('tfa-totp-setup').style.display = 'block';

    const qrEl = document.getElementById('tfa-totp-qrcode');
    qrEl.innerHTML = '';
    if (typeof qrcode === 'function') {
        // La clé ne quitte jamais le navigateur : le QR code est généré localement,
        // pas via un service tiers (qui verrait le secret 2FA en clair).
        const qr = qrcode(0, 'M');
        qr.addData(r.uri);
        qr.make();
        qrEl.innerHTML = qr.createSvgTag(5);
    }
}

function cancelTfaTotpSetup() {
    document.getElementById('tfa-totp-setup').style.display = 'none';
}

async function confirmTfaTotpSetup() {
    const code = document.getElementById('tfa-totp-code').value.trim();
    const r = await apiFetch(BASE_URL + '/settings/2fa/totp/confirm', {
        method: 'POST',
        body: JSON.stringify({ code }),
    });
    if (!r.success) {
        Dialog.toast(r.error || 'Code invalide.', 'error');
        return;
    }
    Dialog.toast('Double authentification activée.', 'success');
    tfaShowStatus('totp');
}

async function enableTfaEmail() {
    const r = await apiFetch(BASE_URL + '/settings/2fa/email/enable', { method: 'POST' });
    if (!r.success) {
        Dialog.toast(r.error || 'Erreur.', 'error');
        return;
    }
    Dialog.toast('Double authentification activée par email.', 'success');
    tfaShowStatus('email');
}

function openTfaDisableModal() {
    document.getElementById('tfa-disable-password').value = '';
    openModal('tfa-disable-modal');
}

async function confirmTfaDisable() {
    const password = document.getElementById('tfa-disable-password').value;
    const r = await apiFetch(BASE_URL + '/settings/2fa/disable', {
        method: 'POST',
        body: JSON.stringify({ password }),
    });
    if (!r.success) {
        Dialog.toast(r.error || 'Erreur.', 'error');
        return;
    }
    closeModal('tfa-disable-modal');
    Dialog.toast('Double authentification désactivée.', 'success');
    tfaShowStatus('none');
}

// ---- Push notifications toggle ----
async function refreshPushStatus() {
    const btn = document.getElementById('push-toggle-btn');
    const status = document.getElementById('push-status');
    if (!btn || !status) return;

    const { state, message } = await checkPushStatus();

    status.textContent = message;
    status.classList.toggle('push-status-warn', state === 'ios-not-installed' || state === 'blocked' || state === 'not-enabled');

    switch (state) {
        case 'unsupported':
        case 'blocked':
            btn.disabled = true;
            btn.textContent = 'Activer les notifications push';
            break;
        case 'ios-not-installed':
            btn.disabled = false; // clicking still explains what to do, via subscribeToPush's guard
            btn.textContent = 'Activer les notifications push';
            break;
        case 'enabled':
            btn.disabled = false;
            btn.textContent = 'Désactiver les notifications push';
            break;
        default: // not-enabled
            btn.disabled = false;
            btn.textContent = 'Activer les notifications push';
    }
}

async function togglePushNotifications() {
    if (!('serviceWorker' in navigator) || !('PushManager' in window)) return;
    const reg = await navigator.serviceWorker.ready;
    const sub = await reg.pushManager.getSubscription();
    if (sub) {
        await unsubscribeFromPush();
    } else {
        await subscribeToPush();
    }
    refreshPushStatus();
}

refreshPushStatus();

// ---- Envoi d'une notification aux membres de la famille (admin) ----
async function sendFamilyNotification(evt) {
    evt.preventDefault();
    const titleInput = document.getElementById('notify-title');
    const messageInput = document.getElementById('notify-message');
    const includeCoparentInput = document.getElementById('notify-include-coparent');
    const resultEl = document.getElementById('family-notify-result');

    const title = titleInput.value.trim();
    const message = messageInput.value.trim();
    if (!title || !message) return;

    const r = await apiFetch(BASE_URL + '/settings/notify', {
        method: 'POST',
        body: JSON.stringify({
            title,
            message,
            include_coparent: !!(includeCoparentInput && includeCoparentInput.checked),
        }),
    });

    if (!r.success) {
        Dialog.toast(r.error || 'Erreur.', 'error');
        return;
    }

    resultEl.innerHTML = `<div class="alert alert-success">Notification envoyée à ${r.count} membre(s).</div>`;
    titleInput.value = '';
    messageInput.value = '';
    if (includeCoparentInput) includeCoparentInput.checked = false;
}

// ---- Accès baby-sitter ----
async function createSitterLink() {
    const labelInput = document.getElementById('sitter-label');
    const label = labelInput.value.trim();
    const hours = parseInt(document.getElementById('sitter-hours').value, 10);
    const r = await apiFetch(BASE_URL + '/api/sitter/links', {
        method: 'POST',
        body: JSON.stringify({ label, hours }),
    });
    if (!r.success) {
        Dialog.toast(r.error || 'Erreur.', 'error');
        return;
    }

    // Stays on screen (no auto-dismiss / no reload) so there's time to copy the link.
    document.getElementById('sitter-new-link').innerHTML = `
        <div class="alert alert-success">
            <div style="margin-bottom:.5rem">Lien créé pour « ${escapeHtml(r.link.label)} » :</div>
            <div style="display:flex;gap:.5rem;flex-wrap:wrap">
                <input type="text" readonly value="${escapeHtml(r.link.url)}"
                       style="flex:1;min-width:200px" onclick="this.select()">
                <button type="button" class="btn btn-secondary btn-sm" onclick="copyCode('${r.link.url.replace(/'/g, "\\'")}')">📋 Copier</button>
            </div>
        </div>`;
    labelInput.value = '';

    const list = document.getElementById('sitter-links-list');
    const empty = list.querySelector('p');
    if (empty) empty.remove();
    const div = document.createElement('div');
    div.className = 'member-item';
    div.dataset.sitterId = r.link.id;
    div.innerHTML = `
        <div class="member-info">
            <strong>${escapeHtml(r.link.label)}</strong>
            <small>✅ Actif · expire le ${new Date(r.link.expires_at.replace(' ', 'T') + 'Z').toLocaleString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })}</small>
        </div>
        <button class="btn btn-danger btn-sm" onclick="revokeSitterLink(${r.link.id})">Révoquer</button>
    `;
    list.prepend(div);
}

async function revokeSitterLink(id) {
    const ok = await Dialog.confirm('Révoquer ce lien ? Il ne sera plus utilisable.');
    if (!ok) return;
    const r = await apiFetch(BASE_URL + '/api/sitter/links/' + id + '/revoke', { method: 'POST' });
    if (r.success) window.location.reload();
}

// ---- Écran mural (mode kiosque) ----
async function createKioskLink() {
    const labelInput = document.getElementById('kiosk-label');
    const label = labelInput.value.trim();
    const r = await apiFetch(BASE_URL + '/api/kiosk/links', {
        method: 'POST',
        body: JSON.stringify({ label }),
    });
    if (!r.success) {
        Dialog.toast(r.error || 'Erreur.', 'error');
        return;
    }

    // Stays on screen (no auto-dismiss / no reload) so there's time to copy the link.
    document.getElementById('kiosk-new-link').innerHTML = `
        <div class="alert alert-success">
            <div style="margin-bottom:.5rem">Accès créé pour « ${escapeHtml(r.link.label)} » — ouvrez ce lien sur la tablette :</div>
            <div style="display:flex;gap:.5rem;flex-wrap:wrap">
                <input type="text" readonly value="${escapeHtml(r.link.url)}"
                       style="flex:1;min-width:200px" onclick="this.select()">
                <button type="button" class="btn btn-secondary btn-sm" onclick="copyCode('${r.link.url.replace(/'/g, "\\'")}')">📋 Copier</button>
                <a href="${escapeHtml(r.link.url)}" target="_blank" class="btn btn-secondary btn-sm">Ouvrir</a>
            </div>
        </div>`;
    labelInput.value = '';

    const list = document.getElementById('kiosk-links-list');
    const empty = list.querySelector('p');
    if (empty) empty.remove();
    const div = document.createElement('div');
    div.className = 'member-item';
    div.dataset.kioskId = r.link.id;
    div.innerHTML = `
        <div class="member-info">
            <strong>${escapeHtml(r.link.label)}</strong>
            <small>✅ Actif · créé le ${new Date(r.link.created_at.replace(' ', 'T') + 'Z').toLocaleDateString('fr-FR')}</small>
        </div>
        <button class="btn btn-danger btn-sm" onclick="revokeKioskLink(${r.link.id})">Révoquer</button>
    `;
    list.prepend(div);
}

async function revokeKioskLink(id) {
    const ok = await Dialog.confirm('Révoquer cet accès écran mural ? La tablette ne pourra plus afficher les données de la famille.');
    if (!ok) return;
    const r = await apiFetch(BASE_URL + '/api/kiosk/links/' + id + '/revoke', { method: 'POST' });
    if (r.success) window.location.reload();
}
