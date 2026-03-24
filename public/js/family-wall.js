// ============================================================
// FamilyBoard — Family Wall (Écran mural) JS
// ============================================================

// ── Clock ───────────────────────────────────────────────────

const _FR_DAYS  = ['Dimanche','Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi'];
const _FR_MONTHS = ['janvier','février','mars','avril','mai','juin',
                    'juillet','août','septembre','octobre','novembre','décembre'];

function wallClock() {
    const now = new Date();
    const hm  = String(now.getHours()).padStart(2,'0') + ':' + String(now.getMinutes()).padStart(2,'0');
    const sec = String(now.getSeconds()).padStart(2,'0');

    const eH = document.getElementById('wH');
    const eS = document.getElementById('wS');
    const eD = document.getElementById('wDate');
    if (eH) eH.textContent = hm;
    if (eS) eS.textContent = sec;
    if (eD) {
        eD.textContent = _FR_DAYS[now.getDay()] + ' ' + now.getDate() + ' ' +
                         _FR_MONTHS[now.getMonth()] + ' ' + now.getFullYear();
    }
}

// ── Weather (Open-Meteo, no API key) ────────────────────────

function _wmoEmoji(code) {
    if (code === 0)        return ['☀️', 'Ensoleillé'];
    if (code <= 2)         return ['⛅', 'Peu nuageux'];
    if (code === 3)        return ['☁️', 'Couvert'];
    if (code <= 48)        return ['🌫️', 'Brouillard'];
    if (code <= 55)        return ['🌦️', 'Bruine'];
    if (code <= 65)        return ['🌧️', 'Pluie'];
    if (code <= 77)        return ['🌨️', 'Neige'];
    if (code <= 82)        return ['🌦️', 'Averses'];
    if (code <= 86)        return ['🌨️', 'Neige'];
    return                        ['⛈️', 'Orage'];
}

async function wallWeather() {
    if (!navigator.geolocation) return;
    navigator.geolocation.getCurrentPosition(async ({ coords }) => {
        try {
            const r = await fetch(
                `https://api.open-meteo.com/v1/forecast` +
                `?latitude=${coords.latitude.toFixed(4)}&longitude=${coords.longitude.toFixed(4)}` +
                `&current=temperature_2m,weather_code&timezone=auto&forecast_days=1`
            );
            if (!r.ok) return;
            const d = await r.json();
            const temp = Math.round(d.current.temperature_2m);
            const [icon, desc] = _wmoEmoji(d.current.weather_code);
            document.getElementById('wWIcon').textContent = icon;
            document.getElementById('wWTemp').textContent = temp + '°C';
            document.getElementById('wWDesc').textContent = desc;
            document.getElementById('wWeather').style.display = 'flex';
        } catch (_) {}
    }, () => {}, { timeout: 6000, maximumAge: 10 * 60 * 1000 });
}

// ── Task toggle ─────────────────────────────────────────────

async function wallToggle(id, el) {
    const isDone = el.classList.toggle('done');
    const cb = el.querySelector('.wl-cb');
    if (cb) cb.textContent = isDone ? '✓' : '';
    try {
        await apiFetch(`${BASE_URL}/api/tasks/task/${id}/toggle`, { method: 'POST' });
    } catch (_) {
        // Revert on network error
        el.classList.toggle('done');
        if (cb) cb.textContent = isDone ? '' : '✓';
    }
}

// ── Auto-refresh countdown ──────────────────────────────────

let _refreshRemaining = 5 * 60; // seconds

function _tickRefresh() {
    _refreshRemaining--;
    const el = document.getElementById('wRefresh');
    if (el) {
        const m = Math.floor(_refreshRemaining / 60);
        const s = String(_refreshRemaining % 60).padStart(2, '0');
        el.textContent = `Actualisation dans ${m}:${s}`;
    }
    if (_refreshRemaining <= 0) location.reload();
}

// ── Cursor hide after inactivity ────────────────────────────

let _cursorTimer = null;

function _resetCursor() {
    document.body.classList.remove('no-cursor');
    clearTimeout(_cursorTimer);
    _cursorTimer = setTimeout(() => document.body.classList.add('no-cursor'), 8000);
}

// ── Wake Lock (prevent screen sleep) ───────────────────────

async function _wakeLock() {
    if ('wakeLock' in navigator) {
        try {
            const lock = await navigator.wakeLock.request('screen');
            // Re-acquire after visibility change (some browsers release it)
            document.addEventListener('visibilitychange', async () => {
                if (!document.hidden) {
                    try { await navigator.wakeLock.request('screen'); } catch (_) {}
                }
            });
        } catch (_) {}
    }
}

// ── Init ────────────────────────────────────────────────────

wallClock();
setInterval(wallClock, 1000);
setInterval(_tickRefresh, 1000);

wallWeather();
_wakeLock();
_resetCursor();

['mousemove', 'touchstart', 'keydown', 'pointerdown'].forEach(ev =>
    document.addEventListener(ev, _resetCursor, { passive: true })
);
