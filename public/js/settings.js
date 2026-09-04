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

// ---- Coffre-fort de mots de passe (Vaultwarden) ----
async function requestVaultInvite() {
    const btn = document.getElementById('vault-invite-btn');
    const msg = document.getElementById('vault-invite-message');
    btn.disabled = true;
    btn.textContent = 'Envoi en cours…';
    const r = await apiFetch(BASE_URL + '/settings/vault/invite', { method: 'POST' });
    if (r.success) {
        window.location.reload();
        return;
    }
    msg.style.color = 'var(--danger)';
    msg.textContent = r.error || 'Erreur lors de l\'envoi de l\'invitation.';
    btn.disabled = false;
    btn.textContent = 'Créer mon coffre-fort';
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
    const instructionsInput = document.getElementById('sitter-instructions');
    const instructions = instructionsInput.value.trim();
    const r = await apiFetch(BASE_URL + '/api/sitter/links', {
        method: 'POST',
        body: JSON.stringify({ label, hours, instructions }),
    });
    if (!r.success) {
        Dialog.toast(r.error || 'Erreur.', 'error');
        return;
    }
    instructionsInput.value = '';

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
        <button class="btn btn-secondary btn-sm" onclick="showKioskLinkModal('${r.link.token}', '${r.link.short_code}', '${escapeHtml(r.link.label).replace(/'/g, "\\'")}')">🔗 Voir le lien</button>
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

function showKioskLinkModal(token, shortCode, label) {
    const url = window.location.origin + BASE_URL + '/kiosk/' + token;
    document.getElementById('kiosk-link-modal-title').textContent = 'Écran mural — ' + label;
    const container = document.getElementById('kiosk-qr-container');
    container.innerHTML = '';
    const qr = qrcode(0, 'M');
    qr.addData(url);
    qr.make();
    container.innerHTML = qr.createSvgTag({ cellSize: 5, margin: 4 });
    document.getElementById('kiosk-qr-link').textContent = url;
    document.getElementById('kiosk-qr-open-link').href = url;
    document.getElementById('kiosk-qr-copy-btn').onclick = () => copyCode(url);
    document.getElementById('kiosk-short-code').textContent = shortCode.replace(/(\d{3})(\d{3})/, '$1 $2');
    openModal('kiosk-link-modal');
}

// ── Domicile (minuteurs) ────────────────────────────────────
function setHomeLocation() {
    const status = document.getElementById('home-location-status');
    if (!('geolocation' in navigator)) {
        status.textContent = "❌ La géolocalisation n'est pas supportée par ce navigateur.";
        return;
    }
    status.textContent = '⏳ Localisation en cours…';
    navigator.geolocation.getCurrentPosition(
        async pos => {
            const r = await apiFetch(BASE_URL + '/settings/home-location', {
                method: 'POST',
                body: JSON.stringify({ lat: pos.coords.latitude, lng: pos.coords.longitude }),
            });
            if (r.success) {
                status.textContent = '✅ Domicile enregistré.';
                setTimeout(() => window.location.reload(), 800);
            } else {
                status.textContent = '❌ ' + (r.error || 'Erreur.');
            }
        },
        err => { status.textContent = '❌ Impossible d\'obtenir votre position : ' + err.message; },
        { enableHighAccuracy: true, timeout: 15000, maximumAge: 60000 }
    );
}

// ---- Enfants de la famille (registre central) ----

function openEditFamilyChildForm(child) {
    document.getElementById('family-child-id').value = child.id;
    document.getElementById('family-child-name').value = child.name;
    document.getElementById('family-child-birthdate').value = child.birth_date || '';
    document.getElementById('family-child-color').value = child.color || '#4A90D9';
    document.getElementById('family-child-form-cancel').style.display = '';
    document.getElementById('family-child-name').scrollIntoView({ behavior: 'smooth', block: 'center' });
}

function resetFamilyChildForm() {
    document.getElementById('family-child-id').value = '';
    document.getElementById('family-child-name').value = '';
    document.getElementById('family-child-birthdate').value = '';
    document.getElementById('family-child-color').value = '#4A90D9';
    document.getElementById('family-child-form-cancel').style.display = 'none';
}

async function saveFamilyChild() {
    const name = document.getElementById('family-child-name').value.trim();
    if (!name) { Dialog.toast('Le nom est requis.', 'error'); return; }
    const payload = {
        name,
        birth_date: document.getElementById('family-child-birthdate').value,
        color: document.getElementById('family-child-color').value,
    };
    const id = document.getElementById('family-child-id').value;
    const url = id ? `${BASE_URL}/api/children/${id}` : `${BASE_URL}/api/children`;
    const r = await apiFetch(url, { method: 'POST', body: JSON.stringify(payload) });
    if (r.success) {
        window.location.reload();
    } else {
        Dialog.toast(r.error || 'Erreur.', 'error');
    }
}

async function deleteFamilyChild(id) {
    const ok = await Dialog.confirm('Supprimer cet enfant du registre familial ? Les données déjà saisies dans les modules (scolaire, nounou, garde alternée, bébé) sont conservées, seulement dépliées de cette fiche.');
    if (!ok) return;
    const r = await apiFetch(`${BASE_URL}/api/children/${id}/delete`, { method: 'POST' });
    if (r.success) window.location.reload();
    else Dialog.toast(r.error || 'Erreur.', 'error');
}
