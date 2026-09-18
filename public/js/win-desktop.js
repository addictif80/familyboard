// ============================================================
// FamilyBoard — Interface bureau (PC, largeur >= 769px)
// Barre des tâches + menu Démarrer + fenêtres flottantes (chaque fenêtre charge la page du
// module existant via <iframe src="…?embed=1">, voir templates/layout.php ($__embed)).
// Sur mobile/PWA, ce script ne fait rien : la sidebar/bottom-nav classique reste utilisée.
// ============================================================

const DESKTOP_BREAKPOINT = 769;

const winState = {
    windows: {},   // key -> { el, iframeEl, taskbarEl, minimized, maximized, prevRect }
    zCounter: 10,
    activeKey: null,
};

function isDesktopMode() {
    return window.innerWidth >= DESKTOP_BREAKPOINT;
}

document.addEventListener('DOMContentLoaded', function () {
    if (!isDesktopMode()) return;
    initWinDesktop();
});

function initWinDesktop() {
    buildStartCategories();
    wireStartMenu();
    wireTaskbarButtons();
    startClock();
    loadDesktopInfo();
    loadCalendarWidget();
    loadShoppingWidget();

    // Ouvre la page actuellement chargée dans une première fenêtre, pour qu'un lien direct
    // (favori, notification…) affiche bien quelque chose au lieu d'un bureau vide.
    if (typeof DESKTOP_CURRENT_PATH !== 'undefined' && DESKTOP_CURRENT_PATH && DESKTOP_CURRENT_PATH !== '/') {
        openWindow({
            key: 'initial:' + DESKTOP_CURRENT_PATH,
            title: DESKTOP_CURRENT_TITLE || 'FamilyBoard',
            icon: '🏠',
            url: winEmbedUrl(DESKTOP_CURRENT_PATH),
        });
    }
}

// ── Menu Démarrer ────────────────────────────────────────────

function buildStartCategories() {
    const container = document.getElementById('win-start-categories');
    if (!container || typeof DESKTOP_CATEGORIES === 'undefined') return;
    container.innerHTML = '';
    DESKTOP_CATEGORIES.forEach((cat, idx) => {
        const tile = document.createElement('button');
        tile.type = 'button';
        tile.className = 'win-category-tile';
        const preview = cat.modules.slice(0, 4).map(m => `<span>${m.icon}</span>`).join('');
        tile.innerHTML = `<div class="win-category-preview">${preview}</div><div class="win-category-label">${cat.icon} ${escapeWinHtml(cat.name)}</div>`;
        tile.addEventListener('click', () => winShowGrid(idx));
        container.appendChild(tile);
    });
}

function winShowGrid(categoryIndex, modules, gridTitle) {
    const cats = document.getElementById('win-start-categories');
    const grid = document.getElementById('win-start-grid');
    const items = document.getElementById('win-start-grid-items');
    if (!cats || !grid || !items) return;
    const mods = modules || (DESKTOP_CATEGORIES[categoryIndex] ? DESKTOP_CATEGORIES[categoryIndex].modules : []);
    items.innerHTML = '';
    mods.forEach(m => {
        const tile = document.createElement('button');
        tile.type = 'button';
        tile.className = 'win-app-tile';
        tile.innerHTML = `<span class="win-app-icon">${m.icon}</span><span class="win-app-label">${escapeWinHtml(m.label)}</span>`;
        tile.addEventListener('click', () => {
            openWindow({ key: 'module:' + m.slug, title: m.label, icon: m.icon, url: winEmbedUrl(m.route) });
            toggleWinStartMenu(false);
        });
        items.appendChild(tile);
    });
    cats.style.display = 'none';
    grid.style.display = 'flex';
}

function winShowCategories() {
    const cats = document.getElementById('win-start-categories');
    const grid = document.getElementById('win-start-grid');
    if (cats) cats.style.display = 'grid';
    if (grid) grid.style.display = 'none';
    const search = document.getElementById('win-start-search-input');
    if (search) search.value = '';
}

function wireStartMenu() {
    const btn = document.getElementById('win-start-btn');
    if (btn) btn.addEventListener('click', () => toggleWinStartMenu());
    const back = document.getElementById('win-start-back');
    if (back) back.addEventListener('click', winShowCategories);
    const search = document.getElementById('win-start-search-input');
    if (search) search.addEventListener('input', () => {
        const q = search.value.trim().toLowerCase();
        if (!q) { winShowCategories(); return; }
        const all = (DESKTOP_CATEGORIES || []).flatMap(c => c.modules);
        const matches = all.filter(m => m.label.toLowerCase().includes(q));
        winShowGrid(null, matches);
    });
    document.addEventListener('click', (e) => {
        const menu = document.getElementById('win-start-menu');
        const startBtn = document.getElementById('win-start-btn');
        if (!menu || menu.style.display === 'none') return;
        if (menu.contains(e.target) || (startBtn && startBtn.contains(e.target))) return;
        toggleWinStartMenu(false);
    });
}

function toggleWinStartMenu(force) {
    const menu = document.getElementById('win-start-menu');
    if (!menu) return;
    const show = force !== undefined ? force : menu.style.display === 'none';
    menu.style.display = show ? 'flex' : 'none';
    if (show) winShowCategories();
}

function wireTaskbarButtons() {
    const home = document.getElementById('win-home-btn');
    if (home) home.addEventListener('click', () => openWindow({ key: 'module:dashboard', title: 'Tableau de bord', icon: '🏠', url: winEmbedUrl('/') }));
    const wall = document.getElementById('win-wall-btn');
    if (wall) wall.addEventListener('click', () => openWindow({ key: 'module:family-wall', title: 'Écran mural', icon: '📺', url: winEmbedUrl('/family-wall') }));
}

function winEmbedUrl(path) {
    const sep = path.includes('?') ? '&' : '?';
    return BASE_URL + path + sep + 'embed=1';
}

function escapeWinHtml(s) {
    const d = document.createElement('div');
    d.textContent = s == null ? '' : String(s);
    return d.innerHTML;
}

// ── Fenêtres flottantes ──────────────────────────────────────

let winCascadeOffset = 0;

function openWindow({ key, title, icon, url }) {
    const existing = winState.windows[key];
    if (existing) {
        if (existing.minimized) restoreWindow(key);
        focusWindow(key);
        return;
    }

    const area = document.getElementById('win-desktop-area');
    if (!area) return;

    const win = document.createElement('div');
    win.className = 'win-window';
    const areaRect = area.getBoundingClientRect();
    const width = Math.min(960, areaRect.width - 60);
    const height = Math.min(640, areaRect.height - 60);
    winCascadeOffset = (winCascadeOffset + 28) % 200;
    win.style.left = (20 + winCascadeOffset) + 'px';
    win.style.top = (20 + winCascadeOffset) + 'px';
    win.style.width = width + 'px';
    win.style.height = height + 'px';

    win.innerHTML = `
        <div class="win-window-header">
            <span class="win-window-title">${icon || ''} ${escapeWinHtml(title)}</span>
            <div class="win-window-controls">
                <button type="button" class="win-window-btn win-window-minimize" title="Réduire">━</button>
                <button type="button" class="win-window-btn win-window-maximize" title="Agrandir">▢</button>
                <button type="button" class="win-window-btn win-window-close" title="Fermer">✕</button>
            </div>
        </div>
        <div class="win-window-body">
            <iframe class="win-window-iframe" src="${url}" title="${escapeWinHtml(title)}"></iframe>
        </div>
        <div class="win-resize-handle"></div>
    `;
    area.appendChild(win);

    const taskbarWindows = document.getElementById('win-taskbar-windows');
    const chip = document.createElement('button');
    chip.type = 'button';
    chip.className = 'win-taskbar-chip active';
    chip.innerHTML = `<span>${icon || ''}</span><span class="win-taskbar-chip-label">${escapeWinHtml(title)}</span>`;
    chip.addEventListener('click', () => {
        const w = winState.windows[key];
        if (!w) return;
        if (w.minimized || winState.activeKey !== key) {
            restoreWindow(key);
            focusWindow(key);
        } else {
            minimizeWindow(key);
        }
    });
    if (taskbarWindows) taskbarWindows.appendChild(chip);

    winState.windows[key] = { el: win, taskbarEl: chip, minimized: false, maximized: false, prevRect: null };

    win.addEventListener('mousedown', () => focusWindow(key));
    win.querySelector('.win-window-close').addEventListener('click', (e) => { e.stopPropagation(); closeWindow(key); });
    win.querySelector('.win-window-minimize').addEventListener('click', (e) => { e.stopPropagation(); minimizeWindow(key); });
    win.querySelector('.win-window-maximize').addEventListener('click', (e) => { e.stopPropagation(); toggleMaximizeWindow(key); });
    wireWindowDrag(win, key);
    wireWindowResize(win, key);

    focusWindow(key);
}

function focusWindow(key) {
    const w = winState.windows[key];
    if (!w) return;
    winState.zCounter += 1;
    w.el.style.zIndex = winState.zCounter;
    Object.entries(winState.windows).forEach(([k, other]) => {
        other.el.classList.toggle('win-window--active', k === key);
        if (other.taskbarEl) other.taskbarEl.classList.toggle('active', k === key && !other.minimized);
    });
    winState.activeKey = key;
}

function closeWindow(key) {
    const w = winState.windows[key];
    if (!w) return;
    w.el.remove();
    if (w.taskbarEl) w.taskbarEl.remove();
    delete winState.windows[key];
    if (winState.activeKey === key) winState.activeKey = null;
}

function minimizeWindow(key) {
    const w = winState.windows[key];
    if (!w) return;
    w.el.style.display = 'none';
    w.minimized = true;
    if (w.taskbarEl) w.taskbarEl.classList.remove('active');
}

function restoreWindow(key) {
    const w = winState.windows[key];
    if (!w) return;
    w.el.style.display = 'flex';
    w.minimized = false;
}

function toggleMaximizeWindow(key) {
    const w = winState.windows[key];
    if (!w) return;
    const area = document.getElementById('win-desktop-area');
    if (!w.maximized) {
        w.prevRect = { left: w.el.style.left, top: w.el.style.top, width: w.el.style.width, height: w.el.style.height };
        w.el.style.left = '0px';
        w.el.style.top = '0px';
        w.el.style.width = area.clientWidth + 'px';
        w.el.style.height = area.clientHeight + 'px';
        w.maximized = true;
    } else if (w.prevRect) {
        Object.assign(w.el.style, w.prevRect);
        w.maximized = false;
    }
}

function wireWindowDrag(win, key) {
    const header = win.querySelector('.win-window-header');
    let dragging = false, startX = 0, startY = 0, startLeft = 0, startTop = 0;
    header.addEventListener('mousedown', (e) => {
        if (e.target.closest('.win-window-btn')) return;
        dragging = true;
        startX = e.clientX; startY = e.clientY;
        startLeft = parseInt(win.style.left, 10) || 0;
        startTop = parseInt(win.style.top, 10) || 0;
        document.body.classList.add('win-dragging');
        focusWindow(key);
        e.preventDefault();
    });
    document.addEventListener('mousemove', (e) => {
        if (!dragging) return;
        win.style.left = Math.max(0, startLeft + (e.clientX - startX)) + 'px';
        win.style.top = Math.max(0, startTop + (e.clientY - startY)) + 'px';
    });
    document.addEventListener('mouseup', () => { dragging = false; document.body.classList.remove('win-dragging'); });
}

function wireWindowResize(win, key) {
    const handle = win.querySelector('.win-resize-handle');
    let resizing = false, startX = 0, startY = 0, startW = 0, startH = 0;
    handle.addEventListener('mousedown', (e) => {
        resizing = true;
        startX = e.clientX; startY = e.clientY;
        startW = win.offsetWidth; startH = win.offsetHeight;
        document.body.classList.add('win-dragging');
        focusWindow(key);
        e.preventDefault();
        e.stopPropagation();
    });
    document.addEventListener('mousemove', (e) => {
        if (!resizing) return;
        win.style.width = Math.max(360, startW + (e.clientX - startX)) + 'px';
        win.style.height = Math.max(240, startH + (e.clientY - startY)) + 'px';
    });
    document.addEventListener('mouseup', () => { resizing = false; document.body.classList.remove('win-dragging'); });
}

// ── Horloge / météo / éphéméride ─────────────────────────────

const WIN_FR_DAYS = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
const WIN_FR_MONTHS = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];

function startClock() {
    tickWinClock();
    setInterval(tickWinClock, 1000 * 30);
}

function tickWinClock() {
    const el = document.getElementById('win-tray-clock');
    if (!el) return;
    const now = new Date();
    const h = String(now.getHours()).padStart(2, '0');
    const m = String(now.getMinutes()).padStart(2, '0');
    el.innerHTML = `<span class="win-tray-time">${h}:${m}</span><span class="win-tray-date">${WIN_FR_DAYS[now.getDay()]} ${now.getDate()} ${WIN_FR_MONTHS[now.getMonth()]}</span>`;
}

async function loadDesktopInfo() {
    try {
        const r = await fetch(`${BASE_URL}/api/desktop/info`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const data = await r.json();
        const ndEl = document.getElementById('win-tray-nameday');
        if (ndEl && data.nameday) ndEl.textContent = 'Ste/St ' + data.nameday;
        if (data.weather_city) loadWinWeather(data.weather_city);
    } catch (_) {}
}

function winWmoEmoji(code) {
    if (code === 0) return '☀️';
    if (code <= 2) return '⛅';
    if (code === 3) return '☁️';
    if (code <= 48) return '🌫️';
    if (code <= 55) return '🌦️';
    if (code <= 65) return '🌧️';
    if (code <= 77) return '🌨️';
    if (code <= 82) return '🌦️';
    if (code <= 86) return '🌨️';
    return '⛈️';
}

async function loadWinWeather(city) {
    const el = document.getElementById('win-tray-weather');
    if (!el || !city) return;
    try {
        const geo = await fetch(`https://geocoding-api.open-meteo.com/v1/search?name=${encodeURIComponent(city)}&count=1&language=fr&format=json`);
        const gd = await geo.json();
        if (!gd.results || !gd.results.length) return;
        const { latitude, longitude } = gd.results[0];
        const r = await fetch(`https://api.open-meteo.com/v1/forecast?latitude=${latitude.toFixed(4)}&longitude=${longitude.toFixed(4)}&current=temperature_2m,weather_code&timezone=auto&forecast_days=1`);
        const d = await r.json();
        el.textContent = `${winWmoEmoji(d.current.weather_code)} ${Math.round(d.current.temperature_2m)}°C`;
    } catch (_) {}
}

// ── Widgets (calendrier / courses) ───────────────────────────

const winCalendarEvents = {}; // id -> event, mémorisé pour l'ouverture du détail au clic

function winFormatEventDate(ev) {
    const d = new Date(ev.start.replace(' ', 'T'));
    return ev.is_all_day
        ? d.toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long' })
        : d.toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long' }) + ' à ' + d.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
}

async function loadCalendarWidget() {
    const body = document.getElementById('win-widget-calendar-body');
    if (!body) return;
    try {
        const r = await fetch(`${BASE_URL}/api/desktop/widgets/calendar`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const events = await r.json();
        if (!Array.isArray(events) || !events.length) {
            body.innerHTML = '<p class="win-widget-empty">Rien à venir dans les 30 prochains jours.</p>';
            return;
        }
        body.innerHTML = events.slice(0, 8).map(ev => {
            winCalendarEvents[ev.id] = ev;
            const d = new Date(ev.start.replace(' ', 'T'));
            const dateLabel = ev.is_all_day
                ? d.toLocaleDateString('fr-FR', { day: 'numeric', month: 'short' })
                : d.toLocaleDateString('fr-FR', { day: 'numeric', month: 'short' }) + ' ' + d.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
            return `<button type="button" class="win-widget-row win-widget-row-clickable" onclick="showEventDetail(${ev.id})"><span class="win-widget-dot" style="background:${ev.color || '#4A90D9'}"></span><span class="win-widget-row-title">${escapeWinHtml(ev.title)}</span><span class="win-widget-row-meta">${dateLabel}</span></button>`;
        }).join('');
    } catch (_) {
        body.innerHTML = '<p class="win-widget-empty">Indisponible.</p>';
    }
}

function showEventDetail(id) {
    const ev = winCalendarEvents[id];
    if (!ev || typeof openModal !== 'function') return;
    document.getElementById('win-event-detail-title').textContent = ev.title;
    document.getElementById('win-event-detail-when').textContent = winFormatEventDate(ev) + (ev.user_name ? ' · ' + ev.user_name : '');
    const locEl = document.getElementById('win-event-detail-location');
    locEl.textContent = ev.location ? '📍 ' + ev.location : '';
    locEl.style.display = ev.location ? 'block' : 'none';
    document.getElementById('win-event-detail-description').textContent = ev.description || '';
    openModal('win-event-detail-modal');
}

function winOpenCalendarFromDetail() {
    closeModal('win-event-detail-modal');
    openWindow({ key: 'module:calendar', title: 'Calendrier', icon: '📅', url: winEmbedUrl('/calendar') });
}

async function loadShoppingWidget() {
    const body = document.getElementById('win-widget-shopping-body');
    if (!body) return;
    try {
        const r = await fetch(`${BASE_URL}/api/desktop/widgets/shopping`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const items = await r.json();
        if (!Array.isArray(items) || !items.length) {
            body.innerHTML = '<p class="win-widget-empty">Liste de courses vide.</p>';
            return;
        }
        body.innerHTML = items.slice(0, 10).map(it =>
            `<div class="win-widget-row"><span class="win-widget-dot" style="background:${it.list_color || '#4A90D9'}"></span><span class="win-widget-row-title">${escapeWinHtml(it.title)}</span></div>`
        ).join('');
    } catch (_) {
        body.innerHTML = '<p class="win-widget-empty">Indisponible.</p>';
    }
}
