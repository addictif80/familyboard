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

// ── RTSP live stream (FFmpeg MJPEG proxy) ────────────────────

function startRtspStream(el, camId) {
    const preview = el.closest('.cam-preview');
    el.onclick = null;
    el.innerHTML = '<span>⏳</span><small>Connexion…</small>';

    const img = document.createElement('img');
    img.className = 'cam-img';

    const addStopBtn = () => {
        if (!el.parentNode) return; // déjà retiré
        el.remove();
        preview.classList.add('cam-rtsp-live');
        const stop = document.createElement('button');
        stop.className = 'cam-rtsp-stop';
        stop.textContent = '■ Stop';
        stop.onclick = () => stopRtspStream(img, stop, camId);
        preview.appendChild(stop);
    };

    img.onerror = () => {
        clearTimeout(startTimer);
        img.remove();
        el.innerHTML = '<span>⚠️</span><small>Flux inaccessible</small>';
        el.onclick = () => startRtspStream(el, camId);
    };

    // img.onload ne se déclenche pas toujours sur un stream multipart/x-mixed-replace ;
    // on ajoute un timeout de 4s pour laisser FFmpeg démarrer et envoyer la 1re frame.
    img.onload = () => { clearTimeout(startTimer); addStopBtn(); };
    const startTimer = setTimeout(addStopBtn, 4000);

    img.src = `${BASE_URL}/api/cameras/${camId}/stream`;
    preview.prepend(img);
}

function stopRtspStream(img, stopBtn, camId) {
    const preview = img.closest('.cam-preview');
    img.src = ''; // coupe la connexion HTTP → PHP/FFmpeg se terminent
    img.remove();
    stopBtn.remove();
    if (preview) preview.classList.remove('cam-rtsp-live');

    // Restaure le placeholder "▶ Voir en direct"
    const ph = document.createElement('div');
    ph.className = 'cam-placeholder cam-rtsp-trigger';
    ph.id = `cam-rtsp-${camId}`;
    ph.innerHTML = '<span class="cam-play-icon">▶</span><small>Voir en direct</small>';
    ph.onclick = () => startRtspStream(ph, camId);
    if (preview) preview.prepend(ph);
}

// ── Helpers ───────────────────────────────────────────────────

function _esc(s) {
    return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}
