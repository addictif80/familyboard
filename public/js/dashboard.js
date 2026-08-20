var Dashboard = (() => {
    // ── Widget toggle state (local copy) ────────────────────────
    let widgetState = (_widgetConfig || []).map(w => ({ slug: w.slug, enabled: w.enabled }));

    function toggleWidget(slug, enabled) {
        const idx = widgetState.findIndex(w => w.slug === slug);
        if (idx >= 0) widgetState[idx].enabled = enabled;
        const el = document.getElementById('widget-' + slug);
        if (el) el.classList.toggle('widget-hidden', !enabled);
    }

    function openWidgetManager() {
        document.getElementById('modal-widget-manager').style.display = 'flex';
    }

    function closeWidgetManager() {
        document.getElementById('modal-widget-manager').style.display = 'none';
    }

    function saveWidgets() {
        // Collect order from DOM
        const grid = document.getElementById('widget-grid');
        const cards = grid.querySelectorAll('.widget-card');
        const ordered = [];
        cards.forEach(card => {
            const slug = card.dataset.slug;
            const w = widgetState.find(x => x.slug === slug);
            ordered.push({ slug, enabled: w ? w.enabled : false });
        });

        fetch(_baseUrl + '/api/dashboard/widgets', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ widgets: ordered }),
        })
        .then(r => { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
        .then(() => location.reload())
        .catch(e => alert('Erreur: ' + e.message));
    }

    // ── Drag-and-drop reordering ─────────────────────────────────
    let dragSrc = null;

    function initDrag() {
        const grid = document.getElementById('widget-grid');
        if (!grid) return;

        grid.querySelectorAll('.widget-card').forEach(card => {
            card.setAttribute('draggable', 'true');

            card.addEventListener('dragstart', e => {
                dragSrc = card;
                card.classList.add('dragging');
                e.dataTransfer.effectAllowed = 'move';
            });

            card.addEventListener('dragend', () => {
                card.classList.remove('dragging');
                grid.querySelectorAll('.widget-card').forEach(c => c.classList.remove('drag-over'));
                dragSrc = null;
            });

            card.addEventListener('dragover', e => {
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';
                if (dragSrc && card !== dragSrc) {
                    grid.querySelectorAll('.widget-card').forEach(c => c.classList.remove('drag-over'));
                    card.classList.add('drag-over');
                }
            });

            card.addEventListener('drop', e => {
                e.preventDefault();
                if (!dragSrc || dragSrc === card) return;
                card.classList.remove('drag-over');

                const allCards = [...grid.querySelectorAll('.widget-card')];
                const srcIdx  = allCards.indexOf(dragSrc);
                const destIdx = allCards.indexOf(card);

                if (srcIdx < destIdx) {
                    card.after(dragSrc);
                } else {
                    card.before(dragSrc);
                }
            });
        });
    }

    // ── Weather ──────────────────────────────────────────────────
    async function loadWeather() {
        const weatherEl = document.getElementById('hero-weather');
        const alertEl   = document.getElementById('hero-alert');
        if (!weatherEl) return;

        const city = (_weatherCity || '').trim();
        if (!city) {
            weatherEl.innerHTML = '';
            return;
        }

        try {
            // Step 1: geocode
            const geoUrl = 'https://geocoding-api.open-meteo.com/v1/search?name=' +
                encodeURIComponent(city) + '&count=1&language=fr&format=json';
            const geoRes = await fetch(geoUrl);
            const geoData = await geoRes.json();
            if (!geoData.results || !geoData.results.length) {
                weatherEl.innerHTML = '<span style="color:var(--text-muted)">Ville introuvable</span>';
                return;
            }
            const loc = geoData.results[0];

            // Step 2: forecast
            const wxUrl = 'https://api.open-meteo.com/v1/forecast' +
                '?latitude=' + loc.latitude +
                '&longitude=' + loc.longitude +
                '&current=temperature_2m,apparent_temperature,weathercode,windspeed_10m,precipitation' +
                '&daily=temperature_2m_max,precipitation_sum,windspeed_10m_max,weathercode' +
                '&timezone=Europe%2FParis&forecast_days=1';
            const wxRes = await fetch(wxUrl);
            const wx = await wxRes.json();
            const cur = wx.current;

            const temp      = Math.round(cur.temperature_2m);
            const feels     = Math.round(cur.apparent_temperature);
            const wind      = Math.round(cur.windspeed_10m);
            const precip    = cur.precipitation;
            const code      = cur.weathercode;

            weatherEl.innerHTML =
                '<span class="weather-icon">' + wmoIcon(code) + '</span>' +
                '<span class="weather-temp">' + temp + '°C</span>' +
                '<span class="weather-feels">ressenti ' + feels + '°C</span>' +
                '<span class="weather-wind">💨 ' + wind + ' km/h</span>' +
                '<span class="weather-city">' + escHtml(loc.name) + '</span>';

            // Alerts
            const alerts = [];
            if (temp >= 38) alerts.push('🔴 Canicule : température ' + temp + '°C');
            else if (temp >= 34) alerts.push('🟠 Chaleur intense : ' + temp + '°C');
            if (precip >= 50) alerts.push('🔵 Risque inondation : précipitations ' + precip + 'mm');
            if (wind >= 80) alerts.push('💨 Vent violent : ' + wind + ' km/h');
            if ([95, 96, 99].includes(code)) alerts.push('⛈️ Orages en cours');

            if (alerts.length) {
                alertEl.innerHTML = alerts.join(' &nbsp;|&nbsp; ');
                alertEl.style.display = 'block';
            } else {
                alertEl.style.display = 'none';
            }
        } catch (err) {
            weatherEl.innerHTML = '';
        }
    }

    function wmoIcon(code) {
        if (code === 0) return '☀️';
        if (code <= 2) return '🌤️';
        if (code <= 3) return '☁️';
        if (code <= 49) return '🌫️';
        if (code <= 67) return '🌧️';
        if (code <= 77) return '❄️';
        if (code <= 82) return '🌦️';
        if (code <= 86) return '🌨️';
        if (code <= 99) return '⛈️';
        return '🌡️';
    }

    function escHtml(s) {
        return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    }

    // ── Init ─────────────────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', () => {
        initDrag();
        loadWeather();
    });

    return { toggleWidget, openWidgetManager, closeWidgetManager, saveWidgets };
})();
