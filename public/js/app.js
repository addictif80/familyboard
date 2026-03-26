// ============================================
// FamilyBoard - Core JS
// ============================================

// ---- Dialog system (replaces alert / confirm) ----

const Dialog = (() => {
    // Inject styles once
    const s = document.createElement('style');
    s.textContent = `
    .dlg-overlay{position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:9990;display:flex;align-items:center;justify-content:center;padding:1rem;animation:dlgFadeIn .15s ease}
    @keyframes dlgFadeIn{from{opacity:0}to{opacity:1}}
    .dlg-box{background:var(--card-bg);border-radius:var(--radius);box-shadow:0 8px 32px rgba(0,0,0,.18);padding:1.5rem;max-width:380px;width:100%;animation:dlgSlideIn .15s ease}
    @keyframes dlgSlideIn{from{transform:translateY(12px);opacity:0}to{transform:translateY(0);opacity:1}}
    .dlg-title{font-weight:700;font-size:1rem;margin-bottom:.5rem}
    .dlg-msg{color:var(--text-muted);font-size:.9rem;line-height:1.55;margin-bottom:1.25rem;white-space:pre-wrap}
    .dlg-actions{display:flex;justify-content:flex-end;gap:.5rem}
    .toast-wrap{position:fixed;bottom:1.25rem;right:1.25rem;z-index:9999;display:flex;flex-direction:column;gap:.4rem;pointer-events:none}
    .dlg-toast{padding:.55rem 1rem;border-radius:8px;font-size:.85rem;font-weight:500;color:#fff;box-shadow:0 4px 14px rgba(0,0,0,.15);animation:toastIn .2s ease;pointer-events:auto;cursor:default}
    @keyframes toastIn{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:translateY(0)}}
    .dlg-toast.success{background:var(--success,#27ae60)}
    .dlg-toast.error  {background:var(--danger,#e74c3c)}
    .dlg-toast.info   {background:var(--primary,#4a90d9)}
    @media(max-width:768px){.dlg-overlay{align-items:flex-end;padding:0}.dlg-box{border-radius:var(--radius) var(--radius) 0 0;max-width:100%}}
    `;
    document.head.appendChild(s);

    let toastWrap = null;
    function getToastWrap() {
        if (!toastWrap) { toastWrap = document.createElement('div'); toastWrap.className = 'toast-wrap'; document.body.appendChild(toastWrap); }
        return toastWrap;
    }

    function toast(msg, type = 'success', duration = 3000) {
        const t = document.createElement('div');
        t.className = `dlg-toast ${type}`;
        t.textContent = msg;
        getToastWrap().appendChild(t);
        setTimeout(() => { t.style.transition = 'opacity .3s'; t.style.opacity = '0'; setTimeout(() => t.remove(), 300); }, duration);
    }

    function alert(msg, title = '') {
        return new Promise(resolve => {
            const overlay = document.createElement('div');
            overlay.className = 'dlg-overlay';
            overlay.innerHTML = `<div class="dlg-box">
                ${title ? `<div class="dlg-title">${escapeHtml(title)}</div>` : ''}
                <div class="dlg-msg">${escapeHtml(msg)}</div>
                <div class="dlg-actions"><button class="btn btn-primary" id="dlg-ok">OK</button></div>
            </div>`;
            document.body.appendChild(overlay);
            document.body.style.overflow = 'hidden';
            const close = () => { overlay.remove(); document.body.style.overflow = ''; resolve(); };
            overlay.querySelector('#dlg-ok').addEventListener('click', close);
            overlay.addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === 'Escape') close(); });
            overlay.querySelector('#dlg-ok').focus();
        });
    }

    function confirm(msg, title = 'Confirmation') {
        return new Promise(resolve => {
            const overlay = document.createElement('div');
            overlay.className = 'dlg-overlay';
            overlay.innerHTML = `<div class="dlg-box">
                <div class="dlg-title">${escapeHtml(title)}</div>
                <div class="dlg-msg">${escapeHtml(msg)}</div>
                <div class="dlg-actions">
                    <button class="btn btn-secondary" id="dlg-cancel">Annuler</button>
                    <button class="btn btn-danger"    id="dlg-confirm">Confirmer</button>
                </div>
            </div>`;
            document.body.appendChild(overlay);
            document.body.style.overflow = 'hidden';
            const close = ok => { overlay.remove(); document.body.style.overflow = ''; resolve(ok); };
            overlay.querySelector('#dlg-cancel').addEventListener('click', () => close(false));
            overlay.querySelector('#dlg-confirm').addEventListener('click', () => close(true));
            overlay.addEventListener('keydown', e => { if (e.key === 'Escape') close(false); if (e.key === 'Enter') close(true); });
            overlay.querySelector('#dlg-cancel').focus();
        });
    }

    return { toast, alert, confirm };
})();

/** Helper for forms with confirmation: <form onsubmit="return confirmSubmit(this,'msg')"> */
function confirmSubmit(form, msg) {
    Dialog.confirm(msg).then(ok => { if (ok) form.submit(); });
    return false;
}

// ---- Modal management ----
function openModal(id) {
    document.getElementById(id).style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeModal(id) {
    document.getElementById(id).style.display = 'none';
    document.body.style.overflow = '';
}

// Close modal on overlay click
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal-overlay')) {
        e.target.style.display = 'none';
        document.body.style.overflow = '';
    }
});

// Close modal on Escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay').forEach(m => {
            if (m.style.display !== 'none') {
                m.style.display = 'none';
                document.body.style.overflow = '';
            }
        });
    }
});

// Sidebar toggle
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('open');
}

// Notifications
let notifOpen = false;

function toggleNotifications() {
    const panel = document.getElementById('notifications-panel');
    notifOpen = !notifOpen;
    panel.style.display = notifOpen ? 'block' : 'none';
    if (notifOpen) loadNotifications();
}

function loadNotifications() {
    fetch(BASE_URL + '/api/notifications')
        .then(r => r.json())
        .then(data => {
            const list = document.getElementById('notif-list');
            const badge = document.getElementById('notif-badge');
            if (badge) badge.textContent = data.unread || '';
            if (!data.unread) document.getElementById('notif-badge')?.remove();

            if (!data.notifications.length) {
                list.innerHTML = '<p style="padding:.75rem 1rem;color:var(--text-muted);font-size:.8rem">Aucune notification.</p>';
                return;
            }
            list.innerHTML = data.notifications.map(n => `
                <div class="notif-item ${n.is_read ? '' : 'notif-unread'}" onclick="readNotif(${n.id}, '${n.link || '#'}')">
                    <div class="notif-title">${escapeHtml(n.title)}</div>
                    <div class="notif-msg">${escapeHtml(n.message)}</div>
                    <div class="notif-time">${formatTime(n.created_at)}</div>
                </div>
            `).join('');
        });
}

function readNotif(id, link) {
    fetch(BASE_URL + '/api/notifications/' + id + '/read', { method: 'POST' });
    if (link && link !== '#') window.location.href = link;
    toggleNotifications();
}

function markAllRead() {
    fetch(BASE_URL + '/api/notifications/read-all', { method: 'POST' })
        .then(() => loadNotifications());
}

// Add notification styles
const style = document.createElement('style');
style.textContent = `
.notif-item { padding: .75rem 1rem; cursor: pointer; border-bottom: 1px solid var(--border); font-size: .8rem; }
.notif-item:hover { background: var(--bg); }
.notif-unread { background: rgba(74,144,217,.06); }
.notif-title { font-weight: 600; margin-bottom: .15rem; }
.notif-msg { color: var(--text-muted); }
.notif-time { color: var(--text-muted); font-size: .7rem; margin-top: .2rem; }
`;
document.head.appendChild(style);

// Close notifications on outside click
document.addEventListener('click', function(e) {
    const panel = document.getElementById('notifications-panel');
    if (panel && notifOpen && !panel.contains(e.target) && !e.target.closest('.btn-icon')) {
        notifOpen = false;
        panel.style.display = 'none';
    }
});

// Lightbox
function openLightbox(src) {
    const overlay = document.createElement('div');
    overlay.className = 'lightbox-overlay';
    overlay.innerHTML = `<img src="${src}" alt="">`;
    overlay.addEventListener('click', () => overlay.remove());
    document.body.appendChild(overlay);
}

// Helpers
function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function formatTime(datetime) {
    const d = new Date(datetime);
    const now = new Date();
    const diff = now - d;
    if (diff < 60000) return 'à l\'instant';
    if (diff < 3600000) return Math.floor(diff/60000) + 'min';
    if (diff < 86400000) return Math.floor(diff/3600000) + 'h';
    return d.toLocaleDateString('fr-FR', {day:'2-digit',month:'2-digit'});
}

async function apiFetch(url, options = {}) {
    const defaults = {
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    };
    const res = await fetch(url, { ...defaults, ...options, headers: { ...defaults.headers, ...(options.headers || {}) } });
    return res.json();
}

// Auto-dismiss alerts
document.querySelectorAll('.alert').forEach(el => {
    setTimeout(() => { el.style.opacity = '0'; el.style.transition = 'opacity .5s'; setTimeout(() => el.remove(), 500); }, 4000);
});

// BASE_URL is set in layout.php

// ── Web Push subscription ─────────────────────────────────────
function urlBase64ToUint8Array(base64String) {
    const pad = base64String.length % 4 === 0 ? '' : '===='.slice(base64String.length % 4);
    const base64 = (base64String + pad).replace(/-/g, '+').replace(/_/g, '/');
    const raw = atob(base64);
    return Uint8Array.from([...raw].map(c => c.charCodeAt(0)));
}

// ── Push helpers ────────────────────────────────────────────────────────────

// Resolve navigator.serviceWorker.ready with a timeout
function _swReady(ms = 8000) {
    return Promise.race([
        navigator.serviceWorker.ready,
        new Promise((_, reject) =>
            setTimeout(() => reject(new Error('Service worker timeout — rechargez la page.')), ms)
        ),
    ]);
}

// ── Push state: 'unsupported' | 'denied' | 'inactive' | 'active' | 'no_vapid'
function _pushUpdateUI(state) {
    const sideBtn   = document.getElementById('push-enable-btn');
    if (sideBtn) sideBtn.style.display = state === 'inactive' ? 'flex' : 'none';

    const statusEl  = document.getElementById('push-status-text');
    const enableBtn = document.getElementById('push-settings-enable');
    const disableBtn= document.getElementById('push-settings-disable');
    const deniedMsg = document.getElementById('push-denied-msg');
    if (!statusEl) return;

    const labels = {
        active:      '🔔 Activées',
        inactive:    '🔕 Désactivées',
        denied:      '🚫 Bloquées par le navigateur',
        unsupported: '⚠️ Non supportées sur cet appareil',
        no_vapid:    '⚙️ Non configurées (admin)',
    };
    statusEl.className   = 'push-status-badge push-' + state;
    statusEl.textContent = labels[state] || state;

    if (enableBtn)  enableBtn.disabled      = false;
    if (enableBtn)  enableBtn.style.display  = (state === 'inactive') ? 'inline-flex' : 'none';
    if (disableBtn) disableBtn.style.display = (state === 'active')   ? 'inline-flex' : 'none';
    if (deniedMsg)  deniedMsg.style.display  = (state === 'denied')   ? 'block'       : 'none';
}

async function initPushSubscription() {
    if (typeof VAPID_PUBLIC_KEY === 'undefined') { _pushUpdateUI('no_vapid'); return; }
    if (!('serviceWorker' in navigator) || !('PushManager' in window)) { _pushUpdateUI('unsupported'); return; }
    if (Notification.permission === 'denied') { _pushUpdateUI('denied'); return; }

    try {
        const sw       = await _swReady();
        const existing = await sw.pushManager.getSubscription();

        if (!existing) { _pushUpdateUI('inactive'); return; }

        // Re-sync with server (best-effort — don't block UI)
        try {
            const j = existing.toJSON();
            await apiFetch(`${BASE_URL}/api/push/subscribe`, {
                method: 'POST',
                body: JSON.stringify({ endpoint: j.endpoint, p256dh: j.keys.p256dh, auth: j.keys.auth }),
            });
        } catch (_) {}
        _pushUpdateUI('active');
    } catch (e) {
        // SW timed out or other error → fall back to inactive so button stays usable
        _pushUpdateUI('inactive');
    }
}

async function enablePushNotifications() {
    const enableBtn = document.getElementById('push-settings-enable');
    if (enableBtn) enableBtn.disabled = true;

    if (typeof VAPID_PUBLIC_KEY === 'undefined') {
        Dialog.toast('Notifications push non configurées par l\'administrateur.', 'error');
        if (enableBtn) enableBtn.disabled = false;
        return;
    }
    const perm = await Notification.requestPermission();
    if (perm !== 'granted') {
        _pushUpdateUI('denied');
        Dialog.toast('Permission refusée dans le navigateur.', 'error');
        return;
    }
    try {
        const sw  = await _swReady();

        // Debug info — visible dans la console F12
        const keyBytes = urlBase64ToUint8Array(VAPID_PUBLIC_KEY);
        console.log('[Push] Notification.permission =', Notification.permission);
        console.log('[Push] VAPID key length (doit être 65) =', keyBytes.length);
        console.log('[Push] VAPID key[0] (doit être 4) =', keyBytes[0]);

        const sub = await sw.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: keyBytes,
        });
        const j = sub.toJSON();
        let res;
        try {
            res = await apiFetch(`${BASE_URL}/api/push/subscribe`, {
                method: 'POST',
                body: JSON.stringify({ endpoint: j.endpoint, p256dh: j.keys.p256dh, auth: j.keys.auth }),
            });
        } catch (e) {
            Dialog.toast('Erreur réseau : ' + e.message, 'error');
            if (enableBtn) enableBtn.disabled = false;
            return;
        }
        if (!res || !res.success) {
            Dialog.toast('Erreur serveur : ' + (res?.error || JSON.stringify(res)), 'error');
            if (enableBtn) enableBtn.disabled = false;
            return;
        }
        _pushUpdateUI('active');
        Dialog.toast('Notifications activées !', 'success');
    } catch (e) {
        console.error('[Push] subscribe error:', e.name, e.message, e);
        Dialog.toast('Erreur (' + e.name + ') : ' + e.message, 'error');
        if (enableBtn) enableBtn.disabled = false;
    }
}

async function disablePushNotifications() {
    try {
        const sw  = await _swReady();
        const sub = await sw.pushManager.getSubscription();
        if (sub) {
            await apiFetch(`${BASE_URL}/api/push/unsubscribe`, {
                method: 'POST',
                body: JSON.stringify({ endpoint: sub.endpoint }),
            });
            await sub.unsubscribe();
        }
        _pushUpdateUI('inactive');
        Dialog.toast('Notifications désactivées.', 'info');
    } catch (e) {
        Dialog.toast('Erreur : ' + e.message, 'error');
    }
}

// Call immediately if DOM ready, otherwise wait — avoids PWA timing issues
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initPushSubscription);
} else {
    initPushSubscription();
}
