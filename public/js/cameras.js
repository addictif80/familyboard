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

// ── Brand presets ─────────────────────────────────────────────

const CAM_PRESETS = {
    tapo: {
        label: 'TP-Link Tapo',
        streamType: 'rtsp',
        // {user}/{pass}/{ip} are replaced dynamically
        rtspTemplate: 'rtsp://{user}:{pass}@{ip}:554/stream1',
        subTemplate:  'rtsp://{user}:{pass}@{ip}:554/stream2',
        help: `<strong>TP-Link Tapo</strong> — Pour activer le flux RTSP :<br>
1. Ouvrez l'app Tapo → sélectionnez la caméra<br>
2. ⚙️ Paramètres → Avancé → <strong>Services tiers</strong><br>
3. Activez <strong>RTSP</strong> et notez les identifiants (souvent différents du compte Tapo)<br>
Port par défaut : <strong>554</strong> &nbsp;|&nbsp; Flux principal : <code>/stream1</code> &nbsp;|&nbsp; Flux secondaire : <code>/stream2</code>
<div class="cam-help-warn">⚠️ Les identifiants RTSP Tapo sont définis dans l'app, pas votre compte TP-Link.</div>`,
    },
    hikvision: {
        label: 'Hikvision',
        streamType: 'rtsp',
        rtspTemplate: 'rtsp://{user}:{pass}@{ip}:554/Streaming/Channels/101',
        subTemplate:  'rtsp://{user}:{pass}@{ip}:554/Streaming/Channels/102',
        help: `<strong>Hikvision</strong> — RTSP activé par défaut.<br>
Canal 1 flux principal : <code>/Streaming/Channels/101</code><br>
Canal 1 flux secondaire : <code>/Streaming/Channels/102</code><br>
Port par défaut : <strong>554</strong>`,
    },
    dahua: {
        label: 'Dahua',
        streamType: 'rtsp',
        rtspTemplate: 'rtsp://{user}:{pass}@{ip}:554/cam/realmonitor?channel=1&subtype=0',
        subTemplate:  'rtsp://{user}:{pass}@{ip}:554/cam/realmonitor?channel=1&subtype=1',
        help: `<strong>Dahua</strong> — RTSP activé par défaut.<br>
Flux principal : <code>subtype=0</code> &nbsp;|&nbsp; Flux secondaire : <code>subtype=1</code><br>
Port par défaut : <strong>554</strong>`,
    },
    reolink: {
        label: 'Reolink',
        streamType: 'rtsp',
        rtspTemplate: 'rtsp://{user}:{pass}@{ip}:554/h264Preview_01_main',
        subTemplate:  'rtsp://{user}:{pass}@{ip}:554/h264Preview_01_sub',
        help: `<strong>Reolink</strong> — Activez le RTSP dans l'interface web de la caméra → Réseau → Avancé → Port RTSP.<br>
Flux principal : <code>/h264Preview_01_main</code><br>
Port par défaut : <strong>554</strong>`,
    },
    axis: {
        label: 'Axis',
        streamType: 'rtsp',
        rtspTemplate: 'rtsp://{user}:{pass}@{ip}:554/axis-media/media.amp',
        subTemplate:  'rtsp://{user}:{pass}@{ip}:554/axis-media/media.amp?videocodec=h264&resolution=640x480',
        help: `<strong>Axis</strong> — RTSP activé par défaut.<br>
URL principale : <code>/axis-media/media.amp</code><br>
Port par défaut : <strong>554</strong>`,
    },
    amcrest: {
        label: 'Amcrest / Foscam',
        streamType: 'rtsp',
        rtspTemplate: 'rtsp://{user}:{pass}@{ip}:554/cam/realmonitor?channel=1&subtype=0',
        subTemplate:  'rtsp://{user}:{pass}@{ip}:554/cam/realmonitor?channel=1&subtype=1',
        help: `<strong>Amcrest / Foscam</strong> — Format similaire à Dahua (même OEM pour certains modèles).<br>
Activez le RTSP dans l'interface web → Réglages réseau → Protocoles.<br>
Port par défaut : <strong>554</strong>`,
    },
    uniview: {
        label: 'Uniview (UNV)',
        streamType: 'rtsp',
        rtspTemplate: 'rtsp://{user}:{pass}@{ip}:554/media/video1',
        subTemplate:  'rtsp://{user}:{pass}@{ip}:554/media/video2',
        help: `<strong>Uniview</strong> — RTSP activé par défaut.<br>
Flux principal : <code>/media/video1</code><br>
Port par défaut : <strong>554</strong>`,
    },
    ezviz: {
        label: 'EZVIZ (Hikvision OEM)',
        streamType: 'rtsp',
        rtspTemplate: 'rtsp://{user}:{pass}@{ip}:554/Streaming/Channels/101',
        subTemplate:  'rtsp://{user}:{pass}@{ip}:554/Streaming/Channels/102',
        help: `<strong>EZVIZ</strong> — Basé sur Hikvision, même format d'URL RTSP.<br>
Activez le RTSP dans l'app EZVIZ → Paramètres → Accès LAN RTSP.<br>
Port par défaut : <strong>554</strong>
<div class="cam-help-warn">⚠️ Les identifiants RTSP sont les identifiants de vérification de la caméra, pas votre compte EZVIZ.</div>`,
    },
    annke: {
        label: 'ANNKE / Ctronics',
        streamType: 'rtsp',
        rtspTemplate: 'rtsp://{user}:{pass}@{ip}:554/Streaming/Channels/101',
        subTemplate:  'rtsp://{user}:{pass}@{ip}:554/Streaming/Channels/102',
        help: `<strong>ANNKE / Ctronics</strong> — Basé sur Hikvision pour la plupart des modèles.<br>
Format Hikvision standard. Port par défaut : <strong>554</strong>`,
    },
    onvif: {
        label: 'Générique ONVIF',
        streamType: 'rtsp',
        rtspTemplate: 'rtsp://{user}:{pass}@{ip}:554/onvif1',
        subTemplate:  null,
        help: `<strong>Générique ONVIF</strong> — La plupart des caméras IP supportent ONVIF.<br>
L'URL RTSP exacte varie selon le modèle. Essayez :<br>
<code>/onvif1</code>, <code>/stream1</code>, <code>/live/ch0</code>, <code>/h264</code><br>
Utilisez un outil comme <strong>ONVIF Device Manager</strong> (Windows, gratuit) pour découvrir l'URL exacte automatiquement.`,
    },
    manual: {
        label: 'Saisie manuelle',
        streamType: null,
        rtspTemplate: null,
        help: null,
    },
};

let _currentPreset = null;

function applyBrandPreset(brand) {
    const preset = CAM_PRESETS[brand];
    const helpBox = document.getElementById('cam-brand-help');
    const urlHint = document.getElementById('cam-url-hint');

    if (!preset || !preset.rtspTemplate) {
        helpBox.style.display = 'none';
        urlHint.style.display = 'none';
        _currentPreset = null;
        return;
    }

    _currentPreset = preset;

    // Show help
    helpBox.innerHTML = preset.help;
    helpBox.style.display = 'block';

    // Set stream type
    if (preset.streamType) {
        document.getElementById('cam-stream-type').value = preset.streamType;
    }

    // Show URL template hint
    if (preset.subTemplate) {
        urlHint.textContent = `Flux secondaire (qualité réduite) : ${preset.subTemplate.replace('{user}', 'user').replace('{pass}', 'pass').replace('{ip}', 'IP')}`;
        urlHint.style.display = 'block';
    } else {
        urlHint.style.display = 'none';
    }

    rebuildRtspUrl();
}

function rebuildRtspUrl() {
    if (!_currentPreset || !_currentPreset.rtspTemplate) return;
    const ip   = document.getElementById('cam-host').value.trim();
    const user = document.getElementById('cam-user').value.trim();
    const pass = document.getElementById('cam-pass').value;
    if (!ip) return;

    const url = _currentPreset.rtspTemplate
        .replace('{ip}',   ip)
        .replace('{user}', encodeURIComponent(user || 'admin'))
        .replace('{pass}', encodeURIComponent(pass || ''));

    document.getElementById('cam-stream-url').value = url;
}

// ── Camera CRUD ───────────────────────────────────────────────

let _editCamId = null;

function openCameraModal() {
    _editCamId = null;
    _currentPreset = null;
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
    document.getElementById('cam-brand-preset').value = '';
    document.getElementById('cam-brand-help').style.display = 'none';
    document.getElementById('cam-url-hint').style.display = 'none';
    openModal('camera-modal');
}

function openEditCameraModal(cam) {
    _editCamId = cam.id;
    _currentPreset = null;
    document.getElementById('cam-brand-preset').value = '';
    document.getElementById('cam-brand-help').style.display = 'none';
    document.getElementById('cam-url-hint').style.display = 'none';
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
