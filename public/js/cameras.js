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

// ── RTSP live stream via go2rtc (proxy MJPEG côté PHP) ───────
// Étape 1 POST : enregistre le stream dans go2rtc (retourne JSON ok/error)
// Étape 2 GET  : proxifie le flux MJPEG de go2rtc vers <img>

async function startRtspStream(el, camId, camName) {
    const preview = el.closest('.cam-preview');
    el.onclick = null;
    el.innerHTML = '<span>⏳</span><small>Connexion…</small>';

    // Étape 1 : enregistrement + vérification go2rtc
    const reg = await apiFetch(`${BASE_URL}/api/cameras/${camId}/go2rtc`, { method: 'POST', body: '{}' });
    if (!reg.ok) {
        el.innerHTML = `<span>⚠️</span><small>${reg.error || 'Erreur go2rtc'}</small>`;
        el.onclick = () => startRtspStream(el, camId, camName);
        return;
    }

    // Étape 2 : charge le flux MJPEG proxifié
    const img = document.createElement('img');
    img.className = 'cam-img';

    const showStream = () => {
        if (!el.parentNode) return;
        el.remove();
        preview.style.position = 'relative';

        const expand = document.createElement('button');
        expand.className = 'cam-rtsp-expand';
        expand.title = 'Agrandir';
        expand.textContent = '⛶';
        expand.onclick = (e) => { e.stopPropagation(); openCamFullscreen(camId, camName || 'Caméra'); };

        const stop = document.createElement('button');
        stop.className = 'cam-rtsp-stop';
        stop.textContent = '■ Stop';
        stop.onclick = () => stopRtspStream(img, expand, stop, camId, camName, preview);

        preview.appendChild(expand);
        preview.appendChild(stop);
    };

    img.onerror = () => {
        clearTimeout(t);
        img.remove();
        el.innerHTML = '<span>⚠️</span><small>go2rtc ne joindre pas la caméra RTSP — vérifiez l\'URL et que la caméra est allumée</small>';
        el.onclick = () => startRtspStream(el, camId, camName);
    };

    img.onload = () => { clearTimeout(t); showStream(); };
    const t = setTimeout(showStream, 4000);

    img.src = `${BASE_URL}/api/cameras/${camId}/go2rtc`;
    preview.prepend(img);
}

function stopRtspStream(img, expandBtn, stopBtn, camId, camName, preview) {
    img.src = '';
    img.remove();
    expandBtn.remove();
    stopBtn.remove();

    const ph = document.createElement('div');
    ph.className = 'cam-placeholder cam-rtsp-trigger';
    ph.innerHTML = '<span class="cam-play-icon">▶</span><small>Voir en direct</small>';
    ph.onclick = () => startRtspStream(ph, camId, camName);
    preview.prepend(ph);
}

function openCamFullscreen(camId, camName) {
    const modal = document.getElementById('cam-fullscreen-modal');
    const body  = document.getElementById('cam-fs-body');
    const title = document.getElementById('cam-fs-title');

    title.textContent = camName;
    body.innerHTML = '<div class="cam-fs-loading">⏳ Chargement du flux…</div>';
    modal.classList.add('open');

    const img = document.createElement('img');
    img.alt = camName;

    img.onerror = () => {
        body.innerHTML = '<div class="cam-fs-loading">⚠️ Flux inaccessible</div>';
    };
    img.onload = () => {
        body.innerHTML = '';
        body.appendChild(img);
    };
    setTimeout(() => {
        if (body.querySelector('.cam-fs-loading')) {
            body.innerHTML = '';
            body.appendChild(img);
        }
    }, 4000);

    img.src = `${BASE_URL}/api/cameras/${camId}/go2rtc`;
    modal._fsImg = img;
}

function closeCamFullscreen(e) {
    if (e && e.target !== e.currentTarget) return;
    const modal = document.getElementById('cam-fullscreen-modal');
    if (modal._fsImg) { modal._fsImg.src = ''; modal._fsImg = null; }
    document.getElementById('cam-fs-body').innerHTML = '';
    modal.classList.remove('open');
}

// ── Helpers ───────────────────────────────────────────────────

function _esc(s) {
    return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}
