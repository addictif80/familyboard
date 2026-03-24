// ============================================================
// FamilyBoard — Family Wall (Écran mural) JS
// ============================================================

// ── Clock ───────────────────────────────────────────────────

const _FR_DAYS   = ['Dimanche','Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi'];
const _FR_MONTHS = ['janvier','février','mars','avril','mai','juin',
                    'juillet','août','septembre','octobre','novembre','décembre'];

function _wallClock() {
    const now = new Date();
    const h   = String(now.getHours()).padStart(2, '0');
    const m   = String(now.getMinutes()).padStart(2, '0');

    const eH = document.getElementById('fwH');
    const eM = document.getElementById('fwM');
    const eD = document.getElementById('fwDate');

    if (eH) eH.textContent = h;
    if (eM) eM.textContent = m;
    if (eD) {
        eD.textContent = _FR_DAYS[now.getDay()] + ' ' +
                         now.getDate() + ' ' +
                         _FR_MONTHS[now.getMonth()] + ' ' +
                         now.getFullYear();
    }
}

// ── Weather (Open-Meteo, no API key) ────────────────────────

function _wmoEmoji(code) {
    if (code === 0)  return ['☀️',  'Ensoleillé'];
    if (code <= 2)   return ['⛅',  'Peu nuageux'];
    if (code === 3)  return ['☁️',  'Couvert'];
    if (code <= 48)  return ['🌫️', 'Brouillard'];
    if (code <= 55)  return ['🌦️', 'Bruine'];
    if (code <= 65)  return ['🌧️', 'Pluie'];
    if (code <= 77)  return ['🌨️', 'Neige'];
    if (code <= 82)  return ['🌦️', 'Averses'];
    if (code <= 86)  return ['🌨️', 'Neige'];
    return                  ['⛈️', 'Orage'];
}

function _showWeather(temp, code, cityLabel) {
    const [icon] = _wmoEmoji(code);
    const el = document.getElementById('fwWeather');
    if (!el) return;
    document.getElementById('fwWIcon').textContent = icon;
    document.getElementById('fwWTemp').textContent = Math.round(temp) + '°C';
    document.getElementById('fwWCity').textContent = cityLabel ? '· ' + cityLabel : '';
    el.style.display = 'flex';
}

async function _fetchWeatherByCoords(lat, lon, cityLabel) {
    try {
        const r = await fetch(
            `https://api.open-meteo.com/v1/forecast` +
            `?latitude=${lat.toFixed(4)}&longitude=${lon.toFixed(4)}` +
            `&current=temperature_2m,weather_code&timezone=auto&forecast_days=1`
        );
        if (!r.ok) return;
        const d = await r.json();
        _showWeather(d.current.temperature_2m, d.current.weather_code, cityLabel);
    } catch (_) {}
}

async function _wallWeather() {
    const city = (typeof WALL_WEATHER_CITY !== 'undefined') ? WALL_WEATHER_CITY.trim() : '';

    if (city) {
        // Geocode city name via Open-Meteo geocoding (free, no key)
        try {
            const geo = await fetch(
                `https://geocoding-api.open-meteo.com/v1/search` +
                `?name=${encodeURIComponent(city)}&count=1&language=fr&format=json`
            );
            if (!geo.ok) return;
            const gd = await geo.json();
            if (gd.results && gd.results.length > 0) {
                const { latitude, longitude, name } = gd.results[0];
                await _fetchWeatherByCoords(latitude, longitude, name);
            }
        } catch (_) {}
        return;
    }

    // Fallback: browser geolocation
    if (!navigator.geolocation) return;
    navigator.geolocation.getCurrentPosition(
        ({ coords }) => _fetchWeatherByCoords(coords.latitude, coords.longitude, ''),
        () => {},
        { timeout: 6000, maximumAge: 10 * 60 * 1000 }
    );
}

// ── Task toggle ─────────────────────────────────────────────

async function wallToggle(id, el) {
    const isDone = el.classList.toggle('done');
    const cb = el.querySelector('.fw-task-cb');
    if (cb) cb.textContent = isDone ? '✓' : '';
    try {
        await apiFetch(`${BASE_URL}/api/tasks/task/${id}/toggle`, { method: 'POST' });
    } catch (_) {
        el.classList.toggle('done');
        if (cb) cb.textContent = isDone ? '' : '✓';
    }
}

// ── Auto-refresh countdown ──────────────────────────────────

let _refreshRemaining = 5 * 60;

function _tickRefresh() {
    _refreshRemaining--;
    const el = document.getElementById('fwRefresh');
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
    _cursorTimer = setTimeout(() => document.body.classList.add('no-cursor'), 10000);
}

// ── Wake Lock ───────────────────────────────────────────────

async function _wakeLock() {
    if ('wakeLock' in navigator) {
        try {
            await navigator.wakeLock.request('screen');
            document.addEventListener('visibilitychange', async () => {
                if (!document.hidden) {
                    try { await navigator.wakeLock.request('screen'); } catch (_) {}
                }
            });
        } catch (_) {}
    }
}

// ── Init ────────────────────────────────────────────────────

_wallClock();
setInterval(_wallClock, 1000);
setInterval(_tickRefresh, 1000);

_wallWeather();
_wakeLock();
_resetCursor();

['mousemove', 'touchstart', 'keydown', 'pointerdown'].forEach(ev =>
    document.addEventListener(ev, _resetCursor, { passive: true })
);
