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

// ── Wall camera panel ────────────────────────────────────────
// Each tile: POST go2rtcRegister → if ok, show MJPEG img
// Fullscreen: HLS via hls.js proxied through PHP (with audio)

let _wallCamOpen   = false;
let _wallCamStreams = {}; // camId -> { img }
let _wallFsHls     = null;
let _wallFsImg     = null;

async function _startWallCamTile(camId) {
    const statusEl  = document.getElementById(`fw-status-${camId}`);
    const previewEl = document.getElementById(`fw-preview-${camId}`);
    if (!statusEl || !previewEl) return;

    try {
        const reg = await apiFetch(`${BASE_URL}/api/cameras/${camId}/go2rtc`, { method: 'POST', body: '{}' });
        if (!reg.ok) {
            statusEl.innerHTML = `<span>⚠️</span>${reg.error || 'Erreur go2rtc'}`;
            return;
        }
    } catch (_) {
        statusEl.innerHTML = '<span>⚠️</span>Erreur réseau';
        return;
    }

    const img = document.createElement('img');
    img.style.cssText = 'width:100%;height:100%;object-fit:contain;display:block;';

    img.onerror = () => {
        img.remove();
        if (statusEl) statusEl.innerHTML = '<span>⚠️</span>Flux inaccessible';
    };

    const showImg = () => {
        if (statusEl && statusEl.parentNode) statusEl.remove();
        if (!previewEl.contains(img)) previewEl.appendChild(img);
    };

    img.onload = showImg;
    setTimeout(showImg, 4000);

    img.src = `${BASE_URL}/api/cameras/${camId}/go2rtc`;
    _wallCamStreams[camId] = { img };
}

function _stopWallCamTile(camId) {
    const s = _wallCamStreams[camId];
    if (s) {
        s.img.src = '';
        s.img.remove();
        delete _wallCamStreams[camId];
    }
    const previewEl = document.getElementById(`fw-preview-${camId}`);
    if (previewEl) {
        const st = document.createElement('div');
        st.id = `fw-status-${camId}`;
        st.className = 'fw-cam-tile-status';
        st.innerHTML = '<span>⏹</span>Arrêté';
        previewEl.innerHTML = '';
        previewEl.appendChild(st);
    }
}

function toggleWallCams() {
    _wallCamOpen ? closeWallCams() : openWallCams();
}

function openWallCams() {
    const panel = document.getElementById('fw-cam-panel');
    const btn   = document.getElementById('fwCamBtn');
    if (!panel) return;
    _wallCamOpen = true;
    panel.classList.add('open');
    if (btn) btn.classList.add('active');

    const cameras = (typeof WALL_CAMERAS !== 'undefined') ? WALL_CAMERAS : [];
    cameras.forEach(c => _startWallCamTile(c.id));
}

function closeWallCams() {
    const panel = document.getElementById('fw-cam-panel');
    const btn   = document.getElementById('fwCamBtn');
    if (!panel) return;

    const cameras = (typeof WALL_CAMERAS !== 'undefined') ? WALL_CAMERAS : [];
    cameras.forEach(c => _stopWallCamTile(c.id));

    _wallCamOpen = false;
    panel.classList.remove('open');
    if (btn) btn.classList.remove('active');
}

function openWallCamFs(camId, camName) {
    const modal = document.getElementById('fw-cam-fs');
    const body  = document.getElementById('fw-cam-fs-body');
    const title = document.getElementById('fw-cam-fs-title');
    if (!modal) return;

    title.textContent = camName || 'Caméra';
    body.innerHTML    = '<div class="fw-cam-fs-loading">⏳ Chargement du flux…</div>';
    modal.classList.add('open');

    const hlsSrc = `${BASE_URL}/api/cameras/${camId}/go2rtc/hls`;

    // Try HLS (with audio) first, fallback to MJPEG img
    if (typeof Hls !== 'undefined' && Hls.isSupported()) {
        _launchFsHls(body, hlsSrc, camId);
    } else {
        // Load hls.js on demand
        const s = document.createElement('script');
        s.src = 'https://cdn.jsdelivr.net/npm/hls.js@1/dist/hls.min.js';
        s.onload = () => {
            if (Hls.isSupported()) {
                _launchFsHls(body, hlsSrc, camId);
            } else {
                _launchFsMjpeg(body, camId);
            }
        };
        s.onerror = () => _launchFsMjpeg(body, camId);
        document.head.appendChild(s);
    }
}

function _launchFsHls(body, hlsSrc, camId) {
    const video = document.createElement('video');
    video.controls  = true;
    video.autoplay  = true;
    video.muted     = false;
    video.playsInline = true;
    video.style.cssText = 'max-width:100%;max-height:100%;border-radius:8px;';

    const hls = new Hls({ enableWorker: false });
    hls.loadSource(hlsSrc);
    hls.attachMedia(video);
    _wallFsHls = hls;

    // Fallback to MJPEG if HLS fails after 6s
    const fallbackTimer = setTimeout(() => {
        hls.destroy();
        _wallFsHls = null;
        _launchFsMjpeg(body, camId);
    }, 6000);

    hls.on(Hls.Events.MANIFEST_PARSED, () => {
        clearTimeout(fallbackTimer);
        body.innerHTML = '';
        body.appendChild(video);
        video.play().catch(() => { video.muted = true; video.play(); });
    });

    hls.on(Hls.Events.ERROR, (ev, data) => {
        if (data.fatal) {
            clearTimeout(fallbackTimer);
            hls.destroy();
            _wallFsHls = null;
            _launchFsMjpeg(body, camId);
        }
    });
}

function _launchFsMjpeg(body, camId) {
    const img = document.createElement('img');
    img.alt = 'Flux caméra';
    img.style.cssText = 'max-width:100%;max-height:100%;border-radius:8px;';

    img.onerror = () => {
        body.innerHTML = '<div class="fw-cam-fs-loading">⚠️ Flux inaccessible</div>';
    };
    const show = () => {
        if (body.querySelector('.fw-cam-fs-loading')) {
            body.innerHTML = '';
            body.appendChild(img);
        }
    };
    img.onload = show;
    setTimeout(show, 4000);

    img.src = `${BASE_URL}/api/cameras/${camId}/go2rtc`;
    _wallFsImg = img;
}

function closeWallCamFs() {
    const modal = document.getElementById('fw-cam-fs');
    if (!modal) return;

    if (_wallFsHls) { _wallFsHls.destroy(); _wallFsHls = null; }
    if (_wallFsImg) { _wallFsImg.src = ''; _wallFsImg = null; }

    document.getElementById('fw-cam-fs-body').innerHTML = '';
    modal.classList.remove('open');
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
