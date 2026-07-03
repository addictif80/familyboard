// ---- Push notifications toggle ----
async function refreshPushStatus() {
    const btn = document.getElementById('push-toggle-btn');
    const status = document.getElementById('push-status');
    if (!btn || !status) return;

    if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
        btn.disabled = true;
        status.textContent = "Non supporté par ce navigateur.";
        return;
    }

    if (Notification.permission === 'denied') {
        btn.disabled = true;
        status.textContent = "Notifications bloquées dans les réglages du navigateur.";
        return;
    }

    const reg = await navigator.serviceWorker.ready;
    const sub = await reg.pushManager.getSubscription();
    if (sub) {
        btn.textContent = 'Désactiver les notifications push';
        status.textContent = 'Activées sur cet appareil.';
    } else {
        btn.textContent = 'Activer les notifications push';
        status.textContent = '';
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
    const label = document.getElementById('sitter-label').value.trim();
    const hours = parseInt(document.getElementById('sitter-hours').value, 10);
    const r = await apiFetch(BASE_URL + '/api/sitter/links', {
        method: 'POST',
        body: JSON.stringify({ label, hours }),
    });
    if (r.success) {
        document.getElementById('sitter-new-link').innerHTML = `
            <div class="alert alert-success" style="word-break:break-all">
                Lien créé : <a href="${r.link.url}" target="_blank">${r.link.url}</a>
            </div>`;
        setTimeout(() => window.location.reload(), 1500);
    } else {
        Dialog.toast(r.error || 'Erreur.', 'error');
    }
}

async function revokeSitterLink(id) {
    const ok = await Dialog.confirm('Révoquer ce lien ? Il ne sera plus utilisable.');
    if (!ok) return;
    const r = await apiFetch(BASE_URL + '/api/sitter/links/' + id + '/revoke', { method: 'POST' });
    if (r.success) window.location.reload();
}
