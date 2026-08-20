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

// ── Minuteurs ────────────────────────────────────────────────
// L'échéance ("alarming") n'est jamais un état serveur : chaque tick la déduit lui-même de
// data-ends-at, ce qui garde tous les écrans (mural + kiosque) cohérents sans synchronisation
// particulière. L'alarme est un bip généré via Web Audio API (pas de fichier audio à charger).

let _timerAlarmCtx = null;
let _timerAlarmInterval = null;

function _timerBeep() {
    try {
        _timerAlarmCtx = _timerAlarmCtx || new (window.AudioContext || window.webkitAudioContext)();
        const osc = _timerAlarmCtx.createOscillator();
        const gain = _timerAlarmCtx.createGain();
        osc.type = 'sine';
        osc.frequency.value = 880;
        gain.gain.setValueAtTime(0.25, _timerAlarmCtx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.001, _timerAlarmCtx.currentTime + 0.4);
        osc.connect(gain).connect(_timerAlarmCtx.destination);
        osc.start();
        osc.stop(_timerAlarmCtx.currentTime + 0.4);
    } catch (_) { /* contexte audio indisponible (pas d'interaction utilisateur encore) */ }
}

function _timerStartAlarmLoop() {
    if (_timerAlarmInterval) return;
    _timerBeep();
    _timerAlarmInterval = setInterval(_timerBeep, 1200);
}

function _timerStopAlarmLoop() {
    clearInterval(_timerAlarmInterval);
    _timerAlarmInterval = null;
}

function _timersTick() {
    const els = document.querySelectorAll('.fw-timer[data-run-id]');
    let anyAlarming = false;
    els.forEach(el => {
        const endsAt = new Date(el.dataset.endsAt.replace(' ', 'T') + 'Z').getTime();
        const remaining = Math.round((endsAt - Date.now()) / 1000);
        const valueEl = el.querySelector('.fw-timer-value');
        if (remaining > 0) {
            const m = String(Math.floor(remaining / 60)).padStart(2, '0');
            const s = String(remaining % 60).padStart(2, '0');
            if (valueEl) valueEl.textContent = `${m}:${s}`;
            el.classList.remove('alarming');
        } else {
            if (valueEl) valueEl.textContent = '00:00';
            el.classList.add('alarming');
            anyAlarming = true;
        }
    });
    if (anyAlarming) _timerStartAlarmLoop();
    else _timerStopAlarmLoop();
}

async function wallStartTimer(id) {
    const data = await apiFetch(`${BASE_URL}/api/family-wall/timers/${id}/start`, { method: 'POST' });
    if (!data.success) return;
    const el = document.getElementById('fw-timer-' + id);
    if (!el) return;
    el.dataset.runId = data.run_id;
    el.dataset.endsAt = data.ends_at;
    el.classList.add('running');
    el.querySelector('.fw-timer-start').style.display = 'none';
    el.querySelector('.fw-timer-stop').style.display = '';
    _timersTick();
}

async function wallStopTimer(id) {
    await apiFetch(`${BASE_URL}/api/family-wall/timers/${id}/stop`, { method: 'POST' });
    const el = document.getElementById('fw-timer-' + id);
    if (!el) return;
    delete el.dataset.runId;
    delete el.dataset.endsAt;
    el.classList.remove('running', 'alarming');
    el.querySelector('.fw-timer-value').textContent = el.dataset.durationMin + ' min';
    el.querySelector('.fw-timer-start').style.display = '';
    el.querySelector('.fw-timer-stop').style.display = 'none';
    _timersTick();
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
_timersTick();
setInterval(_wallClock, 1000);
setInterval(_tickRefresh, 1000);
setInterval(_timersTick, 1000);

_wallWeather();
_wakeLock();
_resetCursor();

['mousemove', 'touchstart', 'keydown', 'pointerdown'].forEach(ev =>
    document.addEventListener(ev, _resetCursor, { passive: true })
);
