// ============================================
// FamilyBoard - Core JS
// ============================================

// ---- Theme (clair / sombre) ----
function toggleTheme() {
    const root = document.documentElement;
    const next = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
    root.setAttribute('data-theme', next);
    try { localStorage.setItem('fb-theme', next); } catch {}
}

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

            // Only show unread notifications — once read (or after "Tout lire"),
            // they disappear from the panel. They stay marked read in the DB.
            const unread = data.notifications.filter(n => !n.is_read);

            if (!unread.length) {
                list.innerHTML = '<p style="padding:.75rem 1rem;color:var(--text-muted);font-size:.8rem">Aucune notification.</p>';
                return;
            }
            list.innerHTML = unread.map(n => `
                <div class="notif-item notif-unread" onclick="readNotif(${n.id}, '${n.link || '#'}')">
                    <div class="notif-title">${escapeHtml(n.title)}</div>
                    <div class="notif-msg">${escapeHtml(n.message)}</div>
                    <div class="notif-time">${formatTime(n.created_at)}</div>
                </div>
            `).join('');
        });
}

function readNotif(id, link) {
    apiFetch(BASE_URL + '/api/notifications/' + id + '/read', { method: 'POST' });
    if (link && link !== '#') window.location.href = link;
    toggleNotifications();
}

function markAllRead() {
    apiFetch(BASE_URL + '/api/notifications/read-all', { method: 'POST' })
        .then(() => loadNotifications());
}

// ---- Official alerts banner (veille informationnelle) ----
let officialAlerts = [];
let officialAlertIndex = 0;
let officialAlertTimer = null;

function loadOfficialAlerts() {
    const banner = document.getElementById('alert-banner');
    if (!banner) return;
    fetch(BASE_URL + '/api/alerts/active')
        .then(r => r.json())
        .then(data => {
            officialAlerts = data.alerts || [];
            if (officialAlertTimer) { clearInterval(officialAlertTimer); officialAlertTimer = null; }
            if (!officialAlerts.length) { banner.style.display = 'none'; return; }
            banner.style.display = 'flex';
            officialAlertIndex = 0;
            renderCurrentOfficialAlert();
            if (officialAlerts.length > 1) {
                officialAlertTimer = setInterval(() => {
                    officialAlertIndex = (officialAlertIndex + 1) % officialAlerts.length;
                    renderCurrentOfficialAlert();
                }, 7000);
            }
        })
        .catch(() => {});
}

function renderCurrentOfficialAlert() {
    const track = document.getElementById('alert-banner-track');
    if (!track || !officialAlerts.length) return;
    const a = officialAlerts[officialAlertIndex];
    track.innerHTML = `
        <span class="alert-banner-icon">${a.icon}</span>
        <span class="alert-banner-cat">${escapeHtml(a.label)}</span>
        <span class="alert-banner-title" title="${escapeHtml(a.title)}">${escapeHtml(a.title)}</span>
        <a href="${escapeHtml(a.source_url)}" target="_blank" rel="noopener noreferrer" class="alert-banner-btn">S'informer</a>
    `;
    // Restart the fade-in animation on each rotation
    track.style.animation = 'none';
    void track.offsetWidth;
    track.style.animation = '';
}

loadOfficialAlerts();

// Add notification styles
const style = document.createElement('style');
style.textContent = `
.notif-item { padding: .75rem 1rem; cursor: pointer; border-bottom: 1px solid var(--border); font-size: .8rem; border-left: 3px solid transparent; transition: background .15s; }
.notif-item:hover { background: var(--bg); }
.notif-unread { background: color-mix(in srgb, var(--primary) 8%, var(--card-bg)); border-left-color: var(--accent); }
.notif-title { font-weight: 600; margin-bottom: .15rem; display: flex; align-items: center; gap: .4rem; }
.notif-unread .notif-title::after { content: ''; width: 6px; height: 6px; border-radius: 50%; background: var(--accent); flex-shrink: 0; }
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

// ============================================
// Messages vocaux — enregistreur réutilisable (chat, journal parental, garde partagée)
// ============================================

/**
 * Cable un bouton micro sur MediaRecorder. Cliquer démarre l'enregistrement,
 * re-cliquer l'arrête et appelle onDone({blob, mimeType, durationSec}).
 * Un enregistrement de moins d'une seconde est silencieusement ignoré (clic accidentel).
 */
function initVoiceRecorder(buttonEl, timerEl, onDone) {
    let mediaRecorder = null;
    let chunks = [];
    let stream = null;
    let startedAt = 0;
    let timerInterval = null;

    function pickMimeType() {
        if (!window.MediaRecorder) return '';
        const candidates = ['audio/webm;codecs=opus', 'audio/webm', 'audio/mp4', 'audio/ogg;codecs=opus'];
        return candidates.find(t => MediaRecorder.isTypeSupported(t)) || '';
    }

    async function start() {
        if (!navigator.mediaDevices || !window.MediaRecorder) {
            Dialog.toast("Votre navigateur ne permet pas l'enregistrement vocal.", 'error');
            return;
        }
        try {
            stream = await navigator.mediaDevices.getUserMedia({ audio: true });
        } catch (e) {
            Dialog.toast('Micro refusé ou indisponible.', 'error');
            return;
        }
        chunks = [];
        const mimeType = pickMimeType();
        mediaRecorder = new MediaRecorder(stream, mimeType ? { mimeType } : undefined);
        mediaRecorder.ondataavailable = e => { if (e.data.size > 0) chunks.push(e.data); };
        mediaRecorder.onstop = () => {
            stream.getTracks().forEach(t => t.stop());
            clearInterval(timerInterval);
            if (timerEl) timerEl.textContent = '';
            buttonEl.classList.remove('recording');
            const durationSec = Math.round((Date.now() - startedAt) / 1000);
            if (durationSec < 1 || !chunks.length) return;
            const blob = new Blob(chunks, { type: mediaRecorder.mimeType || mimeType || 'audio/webm' });
            onDone({ blob, mimeType: blob.type, durationSec });
        };
        mediaRecorder.start();
        startedAt = Date.now();
        buttonEl.classList.add('recording');
        if (timerEl) {
            timerInterval = setInterval(() => {
                timerEl.textContent = formatVoiceDuration(Math.floor((Date.now() - startedAt) / 1000));
            }, 250);
        }
    }

    function stop() {
        if (mediaRecorder && mediaRecorder.state !== 'inactive') mediaRecorder.stop();
    }

    buttonEl.addEventListener('click', () => {
        if (mediaRecorder && mediaRecorder.state === 'recording') stop();
        else start();
    });
}

function voiceExtensionForMime(mime) {
    if (mime.includes('webm')) return 'webm';
    if (mime.includes('mp4')) return 'm4a';
    if (mime.includes('ogg')) return 'ogg';
    if (mime.includes('wav')) return 'wav';
    return 'bin';
}

function formatVoiceDuration(sec) {
    sec = Math.max(0, sec || 0);
    return String(Math.floor(sec / 60)).padStart(2, '0') + ':' + String(sec % 60).padStart(2, '0');
}

/** Bulle de lecture audio pour un message contenant audio_path (déjà en base) ou en attente d'envoi. */
function voiceBubbleHtml(audioUrl, durationSec) {
    const dur = durationSec ? `<span class="voice-duration">${formatVoiceDuration(durationSec)}</span>` : '';
    return `<div class="voice-msg">🎤<audio controls preload="none" src="${audioUrl}"></audio>${dur}</div>`;
}

// ============================================
// Journal d'activité garde partagée (custody + coparent)
// ============================================

function activityLogFormatDateTime(datetime) {
    const iso = datetime.includes('T') ? datetime : datetime.replace(' ', 'T') + 'Z';
    return new Date(iso).toLocaleString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit', timeZone: APP_TIMEZONE });
}

/** Rendu partagé par la modale (custody) et l'onglet (vue co-parent). */
function renderActivityLogHtml(data) {
    if (!data.active) {
        return '<p style="color:var(--text-muted);font-size:.85rem">Le journal d\'activité n\'est pas encore actif : il s\'active automatiquement dès que le co-parent invité se connecte pour la première fois. Toutes les actions des deux côtés seront alors horodatées (fuseau horaire de la famille) et associées à une adresse IP.</p>';
    }
    if (!data.entries || !data.entries.length) {
        return '<p style="color:var(--text-muted);font-size:.85rem">Journal actif — aucune action enregistrée pour l\'instant.</p>';
    }
    return '<div class="activity-log-list">' + data.entries.map(e => `
        <div class="activity-log-item">
            <div><span class="activity-log-user" style="color:${escapeHtml(e.user_color)}">${escapeHtml(e.user_name)}</span> — ${escapeHtml(e.label)}${e.details ? ' : ' + escapeHtml(e.details) : ''}</div>
            <div class="activity-log-meta">
                <span>🕐 ${activityLogFormatDateTime(e.created_at)}</span>
                <span>🌐 ${escapeHtml(e.ip || '—')}</span>
            </div>
        </div>
    `).join('') + '</div>';
}

// Helpers
function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// Colors are stored per family/event/contact and later injected into inline
// style attributes (style="background:${color}") — validate the format so a
// crafted value can't break out of the attribute (CSS/HTML injection).
function safeColor(c, fallback = '#4A90D9') {
    return typeof c === 'string' && /^#[0-9a-fA-F]{3,8}$/.test(c) ? c : fallback;
}

// Month/day-name formatting shared by every month-grid view (calendar, custody, coparent).
const _intlMonth = new Intl.DateTimeFormat('fr-FR', { month: 'long', timeZone: APP_TIMEZONE });
const _intlDay   = new Intl.DateTimeFormat('fr-FR', { weekday: 'short', timeZone: APP_TIMEZONE });

function fmtMonthYear(year, month) {
    const d = new Date(year, month, 1);
    const m = _intlMonth.format(d);
    return m.charAt(0).toUpperCase() + m.slice(1) + ' ' + year;
}

function fmtDayNames() {
    const names = [];
    // Get Mon-Sun: use a known Monday (2024-01-01 was a Monday)
    for (let i = 1; i <= 7; i++) {
        const d = new Date(2024, 0, i);
        const s = _intlDay.format(d);
        names.push(s.charAt(0).toUpperCase() + s.slice(1).replace('.',''));
    }
    return names;
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

// ---- Push notifications ----

// iOS only supports Web Push for an app added to the home screen (iOS 16.4+).
// In a regular Safari tab, permission can be requested but pushes never arrive.
// navigator.platform is unreliable/deprecated — check the UA string first,
// falling back to the "iPadOS reports as Mac" heuristic for iPads.
function isIOS() {
    return /iPhone|iPad|iPod/.test(navigator.userAgent)
        || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
}

function isStandalonePWA() {
    return window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
}

/**
 * Full cross-platform check of whether push notifications can/do work here.
 * Returns { state, message } where state is one of:
 * 'unsupported' | 'ios-not-installed' | 'blocked' | 'not-enabled' | 'enabled'
 */
async function checkPushStatus() {
    if (!('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) {
        return { state: 'unsupported', message: "Les notifications push ne sont pas supportées par ce navigateur." };
    }
    if (isIOS() && !isStandalonePWA()) {
        return {
            state: 'ios-not-installed',
            message: "Sur iPhone/iPad, installez d'abord l'app sur l'écran d'accueil (bouton « Installer l'app ») — sans cela, iOS ne délivre jamais les notifications, même si vous les autorisez.",
        };
    }
    if (Notification.permission === 'denied') {
        return { state: 'blocked', message: "Notifications bloquées dans les réglages du navigateur ou du système." };
    }
    if (Notification.permission === 'granted') {
        const reg = await navigator.serviceWorker.ready;
        const sub = await reg.pushManager.getSubscription();
        if (sub) return { state: 'enabled', message: 'Activées sur cet appareil.' };
        return { state: 'not-enabled', message: "Autorisées par le navigateur mais pas encore activées ici." };
    }
    return { state: 'not-enabled', message: '' };
}

function urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - base64String.length % 4) % 4);
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const rawData = atob(base64);
    return Uint8Array.from([...rawData].map(c => c.charCodeAt(0)));
}

async function subscribeToPush() {
    if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
        Dialog.alert("Les notifications push ne sont pas supportées par ce navigateur.");
        return false;
    }
    if (isIOS() && !isStandalonePWA()) {
        Dialog.alert("Installez d'abord l'app sur l'écran d'accueil (bouton « Installer l'app » dans le menu) avant d'activer les notifications — iOS ne les délivre jamais sinon.");
        return false;
    }
    const permission = await Notification.requestPermission();
    if (permission !== 'granted') return false;

    const reg = await navigator.serviceWorker.ready;
    const { publicKey } = await fetch(BASE_URL + '/api/push/vapid-public-key').then(r => r.json());
    let sub = await reg.pushManager.getSubscription();
    if (!sub) {
        sub = await reg.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: urlBase64ToUint8Array(publicKey),
        });
    }
    await apiFetch(BASE_URL + '/api/push/subscribe', { method: 'POST', body: JSON.stringify(sub.toJSON()) });
    return true;
}

async function unsubscribeFromPush() {
    if (!('serviceWorker' in navigator)) return;
    const reg = await navigator.serviceWorker.ready;
    const sub = await reg.pushManager.getSubscription();
    if (sub) {
        await apiFetch(BASE_URL + '/api/push/unsubscribe', { method: 'POST', body: JSON.stringify({ endpoint: sub.endpoint }) });
        await sub.unsubscribe();
    }
}

// Silently re-attach an already-granted subscription (e.g. after a browser
// update rotated the push endpoint) without prompting the user again.
async function initPushSubscription() {
    if (!('Notification' in window) || Notification.permission !== 'granted') return;
    if (!('serviceWorker' in navigator) || !('PushManager' in window)) return;
    try {
        const reg = await navigator.serviceWorker.ready;
        const existing = await reg.pushManager.getSubscription();
        if (existing) {
            await apiFetch(BASE_URL + '/api/push/subscribe', { method: 'POST', body: JSON.stringify(existing.toJSON()) });
        } else {
            await subscribeToPush();
        }
    } catch { /* ignore — retried on next page load */ }
}

// Auto-dismiss alerts
document.querySelectorAll('.alert').forEach(el => {
    setTimeout(() => { el.style.opacity = '0'; el.style.transition = 'opacity .5s'; setTimeout(() => el.remove(), 500); }, 4000);
});

// ---- Automatic technical-error reporting (continuous monitoring) ----
// Reports uncaught JS errors so the server can open a support ticket in the
// current user's name (see ErrorReportController / App\Core\ErrorReporter).
// No-ops server-side for anonymous visitors — nothing to attribute a ticket to.
const _errorReportSeen = {};
function reportClientError(message, extra = {}) {
    if (!message) return;
    const key = message + '|' + (extra.file || '') + ':' + (extra.line || '');
    const now = Date.now();
    if (_errorReportSeen[key] && now - _errorReportSeen[key] < 10000) return; // debounce identical bursts client-side
    _errorReportSeen[key] = now;

    fetch(BASE_URL + '/api/errors/report', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify({ message, url: location.href, ...extra }),
        keepalive: true,
    }).catch(() => {});
}

window.addEventListener('error', e => {
    reportClientError(e.message || 'Erreur inconnue', { file: e.filename, line: e.lineno });
});
window.addEventListener('unhandledrejection', e => {
    const reason = e.reason;
    reportClientError(reason && reason.message ? reason.message : String(reason));
});

// Many failures (a library logging a failed render, a fetch()'s non-ok
// response handled without throwing) never reach window.onerror — they only
// show up as console.error. Mirror those into the same reporting pipeline
// so "silent" breakage still opens a ticket, without changing what's logged.
const _origConsoleError = console.error.bind(console);
console.error = function (...args) {
    _origConsoleError(...args);
    try {
        reportClientError(args.map(a => (a && a.stack) ? a.stack : String(a)).join(' '));
    } catch { /* never let reporting itself break the app */ }
};

// ---- Manual, one-click problem report ("Signaler un problème") ----
// For users who can't describe a bug technically (or when nothing actually
// throws — e.g. a section just renders empty) : one button, no jargon, a
// diagnostic bundle (browser, PWA cache/service-worker state, page) is
// attached automatically so support can investigate without back-and-forth.
async function collectDiagnostics() {
    const diag = {
        'Page': location.href,
        'Navigateur': navigator.userAgent,
        'Écran': screen.width + 'x' + screen.height + ' (fenêtre ' + window.innerWidth + 'x' + window.innerHeight + ')',
        'En ligne': navigator.onLine ? 'oui' : 'non',
        'Version app chargée': typeof APP_VERSION !== 'undefined' ? APP_VERSION : '?',
    };
    try {
        if ('serviceWorker' in navigator) {
            const reg = await navigator.serviceWorker.getRegistration();
            diag['Service worker'] = reg
                ? ('actif=' + !!reg.active + ' en_attente=' + !!reg.waiting + ' script=' + (reg.active ? reg.active.scriptURL : '?'))
                : 'non enregistré';
        }
    } catch { /* best-effort diagnostic only */ }
    try {
        if ('caches' in window) {
            const keys = await caches.keys();
            diag['Caches PWA'] = keys.join(', ') || '(aucun)';
        }
    } catch { /* best-effort diagnostic only */ }
    try {
        diag['Mode d\'affichage'] = isStandalonePWA() ? 'standalone (installée)' : 'navigateur (non installée)';
        diag['Permission notifications'] = ('Notification' in window) ? Notification.permission : 'API absente';
        if ('serviceWorker' in navigator && 'PushManager' in window) {
            const reg = await navigator.serviceWorker.getRegistration();
            const sub = reg ? await reg.pushManager.getSubscription() : null;
            diag['Abonnement push'] = sub ? 'actif (' + sub.endpoint.slice(0, 40) + '…)' : 'aucun';
        }
    } catch { /* best-effort diagnostic only */ }
    return diag;
}

async function reportManualIssue(description) {
    const diagnostics = await collectDiagnostics();
    return apiFetch(BASE_URL + '/api/errors/report-manual', {
        method: 'POST',
        body: JSON.stringify({ description, diagnostics }),
    });
}

function openReportIssueModal() {
    const el = document.getElementById('report-issue-description');
    if (el) el.value = '';
    openModal('report-issue-modal');
}

async function submitReportIssue() {
    const btn = document.getElementById('report-issue-submit-btn');
    const description = document.getElementById('report-issue-description')?.value.trim() || '';
    if (btn) { btn.disabled = true; btn.textContent = 'Envoi…'; }

    const result = await reportManualIssue(description);

    if (btn) { btn.disabled = false; btn.textContent = 'Envoyer'; }
    if (result && result.success) {
        closeModal('report-issue-modal');
        Dialog.toast('Signalement envoyé, merci ! Le support a été prévenu.', 'success');
    } else {
        Dialog.toast((result && result.error) || "Erreur lors de l'envoi du signalement.", 'error');
    }
}

// BASE_URL is set in layout.php
