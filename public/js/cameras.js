// ============================================================
// FamilyBoard — Cameras JS
// ============================================================

// ── Snapshot refresh ─────────────────────────────────────────

document.querySelectorAll('.cam-snapshot').forEach(img => {
    setInterval(() => {
        const base = img.dataset.src;
        img.src = base + (base.includes('?') ? '&' : '?') + '_t=' + Date.now();
    }, 5000);
});

// ── HLS player ───────────────────────────────────────────────

(function initHls() {
    const videos = document.querySelectorAll('.cam-hls');
    if (!videos.length) return;

    const script = document.createElement('script');
    script.src = 'https://cdn.jsdelivr.net/npm/hls.js@1/dist/hls.min.js';
    script.onload = () => {
        videos.forEach(video => {
            const src = video.dataset.src;
            if (Hls.isSupported()) {
                const hls = new Hls();
                hls.loadSource(src);
                hls.attachMedia(video);
            } else if (video.canPlayType('application/vnd.apple.mpegurl')) {
                video.src = src;
            }
        });
    };
    document.head.appendChild(script);
})();

// ── Image error handler ───────────────────────────────────────

function camImgError(img) {
    const wrap = img.closest('.cam-preview');
    if (!wrap) return;
    img.remove();
    const ph = document.createElement('div');
    ph.className = 'cam-placeholder';
    ph.innerHTML = '<span>⚠️</span><small>Flux inaccessible</small>';
    wrap.prepend(ph);
}

// ── Camera CRUD ───────────────────────────────────────────────

let _editCamId = null;

function openCameraModal() {
    _editCamId = null;
    document.getElementById('cam-modal-title').textContent = 'Nouvelle caméra';
    document.getElementById('cam-id').value = '';
    document.getElementById('cam-name').value = '';
    document.getElementById('cam-host').value = '';
    document.getElementById('cam-user').value = '';
    document.getElementById('cam-pass').value = '';
    document.getElementById('cam-model').value = '';
    document.getElementById('cam-stream-url').value = '';
    document.getElementById('cam-stream-type').value = 'other';
    document.getElementById('cam-notes').value = '';
    document.getElementById('cam-order').value = '0';
    openModal('camera-modal');
}

function openEditCameraModal(cam) {
    _editCamId = cam.id;
    document.getElementById('cam-modal-title').textContent = 'Modifier la caméra';
    document.getElementById('cam-id').value = cam.id;
    document.getElementById('cam-name').value = cam.name;
    document.getElementById('cam-host').value = cam.host;
    document.getElementById('cam-user').value = cam.username || '';
    document.getElementById('cam-pass').value = cam.password || '';
    document.getElementById('cam-model').value = cam.model || '';
    document.getElementById('cam-stream-url').value = cam.stream_url || '';
    document.getElementById('cam-stream-type').value = cam.stream_type || 'other';
    document.getElementById('cam-notes').value = cam.notes || '';
    document.getElementById('cam-order').value = cam.sort_order ?? 0;
    openModal('camera-modal');
}

async function saveCamera() {
    const name = document.getElementById('cam-name').value.trim();
    const host = document.getElementById('cam-host').value.trim();
    if (!name || !host) { Dialog.toast('Nom et adresse IP requis.', 'error'); return; }

    const data = {
        name,
        host,
        username:    document.getElementById('cam-user').value.trim() || null,
        password:    document.getElementById('cam-pass').value || null,
        model:       document.getElementById('cam-model').value.trim() || null,
        stream_url:  document.getElementById('cam-stream-url').value.trim() || null,
        stream_type: document.getElementById('cam-stream-type').value,
        notes:       document.getElementById('cam-notes').value.trim() || null,
        sort_order:  parseInt(document.getElementById('cam-order').value) || 0,
    };

    const id  = document.getElementById('cam-id').value;
    const url = id ? `${BASE_URL}/api/cameras/${id}` : `${BASE_URL}/api/cameras`;
    const res = await apiFetch(url, { method: 'POST', body: JSON.stringify(data) });
    if (res.success) { closeModal('camera-modal'); location.reload(); }
}

async function deleteCamera(id) {
    if (!await Dialog.confirm('Supprimer cette caméra ?')) return;
    const res = await apiFetch(`${BASE_URL}/api/cameras/${id}/delete`, { method: 'POST' });
    if (res.success) {
        const el = document.querySelector(`.cam-card[data-id="${id}"]`);
        if (el) el.remove();
    }
}

// ── Strix Discovery ───────────────────────────────────────────

let _discCamId  = null;
let _discCamObj = null; // full camera object for save
let _discEvSource = null;
let _discFoundStreams = [];

function openDiscoverModal(cam) {
    _discCamId  = cam.id;
    _discCamObj = cam;
    _discFoundStreams = [];
    document.getElementById('disc-cam-id').value = cam.id;
    document.getElementById('disc-target').value  = cam.host;
    document.getElementById('disc-user').value    = cam.username || 'admin';
    document.getElementById('disc-pass').value    = cam.password || '';
    document.getElementById('disc-model').value   = cam.model || '';
    document.getElementById('disc-channel').value = '1';
    _resetDiscoverUI();
    openModal('discover-modal');
}

function closeDiscoverModal() {
    if (_discEvSource) { _discEvSource.close(); _discEvSource = null; }
    closeModal('discover-modal');
}

function _resetDiscoverUI() {
    document.getElementById('disc-progress-wrap').style.display = 'none';
    document.getElementById('disc-progress-fill').style.width   = '0%';
    document.getElementById('disc-progress-label').textContent  = 'Démarrage…';
    document.getElementById('disc-progress-pct').textContent    = '0%';
    document.getElementById('disc-streams').style.display       = 'none';
    document.getElementById('disc-streams-list').innerHTML      = '';
    document.getElementById('disc-empty').style.display         = 'none';
    document.getElementById('disc-start-btn').disabled          = false;
    document.getElementById('disc-start-btn').textContent       = 'Lancer la découverte';
    document.getElementById('disc-model-results').style.display = 'none';
    _discFoundStreams = [];
}

function startDiscovery() {
    if (_discEvSource) { _discEvSource.close(); }
    _resetDiscoverUI();

    const target  = document.getElementById('disc-target').value.trim();
    const user    = document.getElementById('disc-user').value.trim();
    const pass    = document.getElementById('disc-pass').value;
    const model   = document.getElementById('disc-model').value.trim() || 'auto';
    const channel = document.getElementById('disc-channel').value || '1';

    if (!target) { Dialog.toast('Adresse IP requise.', 'error'); return; }

    const btn = document.getElementById('disc-start-btn');
    btn.disabled = true;
    btn.textContent = 'Découverte en cours…';
    document.getElementById('disc-progress-wrap').style.display = 'block';

    const params = new URLSearchParams({ target, username: user, password: pass, model, channel });
    _discEvSource = new EventSource(`${BASE_URL}/api/cameras/strix-discover?${params}`);

    _discEvSource.addEventListener('progress', e => {
        const d = JSON.parse(e.data);
        const pct = d.progress ?? 0;
        document.getElementById('disc-progress-fill').style.width = `${pct}%`;
        document.getElementById('disc-progress-pct').textContent  = `${pct}%`;
        document.getElementById('disc-progress-label').textContent = d.message || `Analyse en cours…`;
    });

    _discEvSource.addEventListener('stream_found', e => {
        const s = JSON.parse(e.data);
        _discFoundStreams.push(s);
        document.getElementById('disc-streams').style.display = 'block';

        const meta = [
            s.resolution || '',
            s.fps ? `${s.fps} FPS` : '',
            s.codec || '',
        ].filter(Boolean).join(' · ');

        const item = document.createElement('div');
        item.className = 'strix-stream-item';
        item.innerHTML = `
            <span class="strix-proto-badge">${(s.protocol || 'stream').toUpperCase()}</span>
            <div class="strix-stream-info">
                <span class="strix-stream-url" title="${_esc(s.url)}">${_esc(s.url)}</span>
                ${meta ? `<span class="strix-stream-meta">${_esc(meta)}</span>` : ''}
            </div>
            <button class="btn btn-primary btn-sm" onclick="applyStream(${JSON.stringify(s).replace(/"/g, '&quot;')})">
                Utiliser
            </button>`;
        document.getElementById('disc-streams-list').appendChild(item);
    });

    _discEvSource.addEventListener('complete', () => {
        _discEvSource.close(); _discEvSource = null;
        btn.textContent = 'Relancer';
        btn.disabled = false;
        document.getElementById('disc-progress-fill').style.width = '100%';
        document.getElementById('disc-progress-pct').textContent  = '100%';
        document.getElementById('disc-progress-label').textContent = 'Terminé.';
        if (_discFoundStreams.length === 0) {
            document.getElementById('disc-empty').style.display = 'block';
        }
    });

    _discEvSource.onerror = () => {
        _discEvSource.close(); _discEvSource = null;
        btn.textContent = 'Relancer';
        btn.disabled = false;
        document.getElementById('disc-progress-label').textContent = 'Erreur lors de la découverte.';
    };
}

async function applyStream(stream) {
    if (!_discCamId || !_discCamObj) return;

    const typeMap = { rtsp: 'rtsp', http_mjpeg: 'mjpeg', hls: 'hls', jpeg: 'snapshot' };
    const type = typeMap[stream.protocol] || 'other';

    const data = {
        name:        _discCamObj.name,
        host:        _discCamObj.host,
        username:    _discCamObj.username || null,
        password:    _discCamObj.password || null,
        model:       _discCamObj.model    || null,
        notes:       _discCamObj.notes    || null,
        sort_order:  _discCamObj.sort_order ?? 0,
        stream_url:  stream.url,
        stream_type: type,
    };

    const res = await apiFetch(`${BASE_URL}/api/cameras/${_discCamId}`, {
        method: 'POST', body: JSON.stringify(data)
    });
    if (res.success) {
        Dialog.toast('Flux appliqué !', 'success');
        setTimeout(() => location.reload(), 800);
    }
}

// ── Strix model autocomplete ──────────────────────────────────

let _strixSearchTimer = null;

async function strixModelSearch(q) {
    const resultsEl = document.getElementById('disc-model-results');
    clearTimeout(_strixSearchTimer);
    if (q.length < 2) { resultsEl.style.display = 'none'; return; }

    _strixSearchTimer = setTimeout(async () => {
        const res = await apiFetch(`${BASE_URL}/api/cameras/strix-search`, {
            method: 'POST', body: JSON.stringify({ query: q })
        });
        const items = Array.isArray(res) ? res : (res.results ?? []);
        if (!items.length) { resultsEl.style.display = 'none'; return; }

        resultsEl.innerHTML = items.map(m =>
            `<div class="strix-sug-item" onclick="selectModel(${JSON.stringify(m.name ?? m).replace(/"/g,'&quot;')})">${_esc(m.name ?? m)}</div>`
        ).join('');
        resultsEl.style.display = 'block';
    }, 280);
}

function selectModel(name) {
    document.getElementById('disc-model').value = name;
    document.getElementById('disc-model-results').style.display = 'none';
}

// ── Helpers ───────────────────────────────────────────────────

function _esc(s) {
    return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

// Close autocomplete on outside click
document.addEventListener('click', e => {
    if (!e.target.closest('#disc-model') && !e.target.closest('#disc-model-results')) {
        const el = document.getElementById('disc-model-results');
        if (el) el.style.display = 'none';
    }
});
