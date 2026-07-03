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
