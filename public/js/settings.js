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
