// ============================================
// FamilyBoard - Core JS
// ============================================

// Modal management
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
