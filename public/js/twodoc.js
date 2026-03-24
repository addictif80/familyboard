// ============================================
// FamilyBoard — 2D-Doc Reader
// Lazy-loads @zxing/library (DataMatrix), parses the French 2D-Doc standard
// and pre-fills the document form.
// ============================================

// ── ZXing lazy loader ─────────────────────────────────────────────────────

const ZXING_CDN = 'https://cdn.jsdelivr.net/npm/@zxing/library@0.18.6/umd/index.min.js';

function loadZXing() {
    return new Promise((resolve, reject) => {
        if (window.ZXing) { resolve(); return; }
        const s = document.createElement('script');
        s.src = ZXING_CDN;
        s.onload  = resolve;
        s.onerror = () => reject(new Error('Impossible de charger ZXing depuis le CDN.'));
        document.head.appendChild(s);
    });
}

// ── 2D-Doc constant tables ────────────────────────────────────────────────

const TWODOC_DOC_TYPES = {
    B0: { label: "Carte Nationale d'Identité",  docType: 'identity', issuer: 'Préfecture' },
    B1: { label: 'Passeport',                   docType: 'identity', issuer: 'Préfecture' },
    B2: { label: 'Permis de conduire',           docType: 'identity', issuer: 'Préfecture' },
    B3: { label: 'Titre de séjour',              docType: 'identity', issuer: 'Préfecture' },
    B4: { label: 'Certificat de nationalité',    docType: 'civil',    issuer: 'Préfecture' },
    C0: { label: 'Justificatif de domicile',     docType: 'address',  issuer: null },
    C1: { label: 'Quittance de loyer',           docType: 'address',  issuer: null },
    C2: { label: "Attestation d'hébergement",    docType: 'address',  issuer: null },
    D0: { label: "Avis d'imposition (IR)",       docType: 'tax',      issuer: 'DGFiP' },
    D1: { label: 'Avis de taxe foncière',        docType: 'tax',      issuer: 'DGFiP' },
    D2: { label: "Avis de taxe d'habitation",    docType: 'tax',      issuer: 'DGFiP' },
    D3: { label: 'Avis de non-imposition',       docType: 'tax',      issuer: 'DGFiP' },
    F0: { label: "Certificat d'immatriculation", docType: 'contract', issuer: 'ANTS' },
    F1: { label: 'Contrôle technique',           docType: 'other',    issuer: null },
    G0: { label: 'Carte Vitale',                 docType: 'medical',  issuer: 'Assurance Maladie' },
    H0: { label: 'Attestation CAF',              docType: 'other',    issuer: 'CAF' },
};

const TWODOC_FIELD_LABELS = {
    // Identity
    AB: "Nom de naissance", AC: "Prénoms", AD: "Date de naissance",
    AE: "Sexe", AF: "Numéro du document", AG: "Date de fin de validité",
    AH: "Numéro de pièce", AI: "Commune de naissance", AJ: "Nom d'usage",
    // Address proof
    DA: "Nom", DB: "Prénom", DC: "Adresse", DD: "Code postal", DE: "Commune",
    DF: "Date de facturation", DG: "Montant TTC", DH: "Numéro de client",
    DI: "Numéro de contrat",
    // Tax
    BA: "Numéro fiscal (décl.1)", BB: "Référence de l'avis", BC: "Nombre de parts",
    BD: "Revenu fiscal de référence", BE: "Montant de l'impôt net",
    BF: "Revenu brut global", BG: "Numéro fiscal (décl.2)",
    BH: "Situation de famille", BI: "Personnes à charge", BJ: "Année des revenus",
    // Car registration
    CA: "Date 1ère immatriculation", CB: "Immatriculation", CC: "VIN",
    CD: "Marque", CE: "Type/variante", CF: "Dénomination commerciale",
    CG: "Énergie", CH: "Puissance (ch)", CI: "PTAC (kg)", CJ: "Numéro de formule",
};

// ── 2D-Doc parser ─────────────────────────────────────────────────────────

function parse2DDoc(raw) {
    const idx = raw.indexOf('DC');
    if (idx === -1) return null;
    const s = raw.substring(idx);

    // Strip cryptographic signature (after GS 0x1D)
    const gsIdx = s.indexOf('\x1D');
    const dataZone = gsIdx !== -1 ? s.substring(0, gsIdx) : s;

    // Need at least 22 header chars
    if (dataZone.length < 22) return null;

    const version   = s.substring(2, 4);
    const caId      = s.substring(4, 8);
    const certId    = s.substring(8, 12);
    const issueDate = _parseDDMMYY6(s.substring(12, 18));
    const signDate  = _parseDDMMYY6(s.substring(18, 24));

    let docTypeCode = null;
    let headerLen   = 22;
    if (version === '03' || version === '04') {
        docTypeCode = s.substring(24, 26);
        headerLen   = 26;
    }

    // Parse fields (US-separated: 0x1F)
    const fields = {};
    const afterHeader = dataZone.substring(headerLen);
    for (const part of afterHeader.split('\x1F')) {
        if (part.length >= 3) {
            const code  = part.substring(0, 2);
            const value = part.substring(2).trim();
            // Only accept 2-uppercase-char codes
            if (/^[A-Z][A-Z0-9]$/.test(code) && value !== '') {
                fields[code] = value;
            }
        }
    }

    const docTypeInfo = docTypeCode ? (TWODOC_DOC_TYPES[docTypeCode] || null) : null;

    return {
        version, caId, certId, issueDate, signDate,
        docTypeCode, docTypeInfo, fields,
    };
}

// DD/MM/YY (6 chars) → ISO date  e.g. "020624" → "2024-06-02"
function _parseDDMMYY6(s) {
    if (!s || s.length < 6) return null;
    const d = s.substring(0, 2), m = s.substring(2, 4), y = s.substring(4, 6);
    if (!+d || !+m) return null;
    const year = +y >= 70 ? '19' + y : '20' + y;
    return `${year}-${m}-${d}`;
}

// DD/MM/YYYY (8 chars) → ISO date  e.g. "02062024" → "2024-06-02"
function _parseDDMMYYYY(s) {
    if (!s || s.length < 8) return null;
    const d = s.substring(0, 2), m = s.substring(2, 4), y = s.substring(4, 8);
    if (!+d || !+m || !+y) return null;
    return `${y}-${m}-${d}`;
}

// Cents → "1 234,56 €"
function _centsToEur(s) {
    const n = parseInt(s, 10);
    if (isNaN(n)) return s;
    return (n / 100).toLocaleString('fr-FR', { style: 'currency', currency: 'EUR' });
}

// ── Form pre-filler ───────────────────────────────────────────────────────

function apply2DocToForm(parsed) {
    const { docTypeCode, docTypeInfo, fields, issueDate } = parsed;

    // Set doc type + issuer
    if (docTypeInfo) {
        const sel = document.getElementById('doc-type');
        if (sel) sel.value = docTypeInfo.docType || 'other';
        if (docTypeInfo.issuer) _setIfEmpty('doc-issuer', docTypeInfo.issuer);
    }

    const type = docTypeCode || '';

    // Identity: B0 CNI, B1 Passeport, B2 Permis, B3 Titre séjour
    if (/^B[0-3]$/.test(type)) {
        const prenom = (fields.AC || '').split(/\s+/)[0];
        const nom    = fields.AJ || fields.AB || '';
        _setIfEmpty('doc-title', [docTypeInfo?.label, prenom, nom].filter(Boolean).join(' - '));
        _setIfEmpty('doc-expiry-date', _parseDDMMYYYY(fields.AG) || '');
        _setIfEmpty('doc-issue-date', issueDate || '');
        _addTag(fields.AF ? 'N°' + fields.AF : null);
    }

    // Address proof: C0 domicile, C1 quittance, C2 attestation hébergement
    else if (/^C[0-2]$/.test(type)) {
        const who = [fields.DB, fields.DA].filter(Boolean).join(' ');
        _setIfEmpty('doc-title', [docTypeInfo?.label, who].filter(Boolean).join(' — '));
        _setIfEmpty('doc-issue-date', _parseDDMMYYYY(fields.DF) || issueDate || '');
        if (fields.DD || fields.DE) _addTag([fields.DD, fields.DE].filter(Boolean).join(' '));
        if (fields.DI) _addTag('Contrat: ' + fields.DI);
        if (fields.DG) _setIfEmpty('doc-notes', 'Montant : ' + _centsToEur(fields.DG));
    }

    // Tax: D0 IR, D1 foncière, D2 habitation, D3 non-imposition
    else if (/^D[0-3]$/.test(type)) {
        const year = fields.BJ || new Date().getFullYear();
        _setIfEmpty('doc-title', [docTypeInfo?.label, year].filter(Boolean).join(' '));
        _setIfEmpty('doc-issue-date', issueDate || '');
        const notes = [];
        if (fields.BD) notes.push('Revenu fiscal : '     + _centsToEur(fields.BD));
        if (fields.BE) notes.push('Impôt net : '         + _centsToEur(fields.BE));
        if (fields.BF) notes.push('Revenu brut global : ' + _centsToEur(fields.BF));
        if (fields.BC) notes.push('Parts : '             + fields.BC);
        if (notes.length) _setIfEmpty('doc-notes', notes.join('\n'));
        if (fields.BA) _addTag('Fiscal: ' + fields.BA);
        if (fields.BB) _addTag('Réf: ' + fields.BB);
    }

    // Car registration: F0
    else if (type === 'F0') {
        const plate = fields.CB || '';
        const brand = fields.CD || '';
        const model = fields.CF || fields.CE || '';
        _setIfEmpty('doc-title', ['Carte grise', plate, brand, model].filter(Boolean).join(' — '));
        _setIfEmpty('doc-issue-date', _parseDDMMYYYY(fields.CA) || issueDate || '');
        if (plate) _addTag(plate);
        if (fields.CC) _addTag('VIN: ' + fields.CC);
        if (fields.CG) _addTag(fields.CG);
        const notes = [];
        if (fields.CH) notes.push('Puissance : ' + fields.CH + ' ch');
        if (fields.CI) notes.push('PTAC : ' + fields.CI + ' kg');
        if (notes.length) _setIfEmpty('doc-notes', notes.join('\n'));
    }

    // Fallback: use issueDate from header
    else {
        _setIfEmpty('doc-issue-date', issueDate || '');
    }
}

function _setIfEmpty(id, value) {
    if (!value) return;
    const el = document.getElementById(id);
    if (el && !el.value) el.value = value;
}

function _addTag(tag) {
    if (!tag) return;
    const el = document.getElementById('doc-tags');
    if (!el) return;
    const existing = el.value.trim();
    el.value = existing ? existing + ', ' + tag : tag;
}

// ── ZXing DataMatrix decoder (image file) ─────────────────────────────────

async function decodeDataMatrixFromFile(file) {
    await loadZXing();

    const img = new Image();
    img.src = URL.createObjectURL(file);
    await new Promise((res, rej) => { img.onload = res; img.onerror = rej; });

    // Try at 1× and 2× scale to help detection on small barcodes
    for (const scale of [1, 2, 1.5]) {
        const canvas = document.createElement('canvas');
        canvas.width  = img.naturalWidth  * scale;
        canvas.height = img.naturalHeight * scale;
        canvas.getContext('2d').drawImage(img, 0, 0, canvas.width, canvas.height);

        try {
            const lum    = new ZXing.HTMLCanvasElementLuminanceSource(canvas);
            const bitmap = new ZXing.BinaryBitmap(new ZXing.HybridBinarizer(lum));
            const hints  = new Map([
                [ZXing.DecodeHintType.POSSIBLE_FORMATS, [ZXing.BarcodeFormat.DATA_MATRIX]],
                [ZXing.DecodeHintType.TRY_HARDER, true],
            ]);
            const reader = new ZXing.MultiFormatReader();
            reader.setHints(hints);
            const result = reader.decode(bitmap);
            URL.revokeObjectURL(img.src);
            return result.getText();
        } catch (e) {
            // NotFoundException at this scale — try next
        }
    }
    URL.revokeObjectURL(img.src);
    return null;
}

// ── Camera scanner ────────────────────────────────────────────────────────

let _cameraStopFn = null;

async function startCameraScanner(videoEl, onResult, onError) {
    await loadZXing();

    let stream;
    try {
        stream = await navigator.mediaDevices.getUserMedia({
            video: { facingMode: 'environment', width: { ideal: 1280 }, height: { ideal: 720 } },
        });
    } catch (e) {
        onError('Impossible d\'accéder à la caméra : ' + e.message);
        return;
    }

    videoEl.srcObject = stream;
    videoEl.play().catch(() => {});

    const canvas = document.createElement('canvas');
    const hints  = new Map([
        [ZXing.DecodeHintType.POSSIBLE_FORMATS, [ZXing.BarcodeFormat.DATA_MATRIX]],
        [ZXing.DecodeHintType.TRY_HARDER, true],
    ]);
    const reader = new ZXing.MultiFormatReader();
    reader.setHints(hints);

    let running = true;

    const stop = () => {
        running = false;
        stream.getTracks().forEach(t => t.stop());
        videoEl.srcObject = null;
    };

    const tick = () => {
        if (!running) return;
        if (videoEl.readyState >= 2 && videoEl.videoWidth > 0) {
            canvas.width  = videoEl.videoWidth;
            canvas.height = videoEl.videoHeight;
            canvas.getContext('2d').drawImage(videoEl, 0, 0);
            try {
                const lum    = new ZXing.HTMLCanvasElementLuminanceSource(canvas);
                const bitmap = new ZXing.BinaryBitmap(new ZXing.HybridBinarizer(lum));
                const result = reader.decode(bitmap);
                stop();
                onResult(result.getText());
                return;
            } catch (e) { /* NotFoundException — continue */ }
        }
        requestAnimationFrame(tick);
    };

    requestAnimationFrame(tick);
    return stop;
}

// ── UI glue ───────────────────────────────────────────────────────────────

let _twodocParsed = null; // last parsed 2D-Doc result

/** Called after file upload — tries to detect a DataMatrix in the image */
async function try2DDocFromFile(file) {
    if (!file || file.type === 'application/pdf') return;

    const btn = document.getElementById('doc-btn-scan-image');
    if (btn) { btn.disabled = true; btn.textContent = '🔲 Lecture…'; }

    try {
        const raw = await decodeDataMatrixFromFile(file);
        if (btn) { btn.disabled = false; btn.textContent = '🔲 Lire le 2D-Doc'; }
        if (raw) {
            show2DDocResult(raw);
        } else {
            // no barcode found silently — user can still click the button manually
        }
    } catch (e) {
        if (btn) { btn.disabled = false; btn.textContent = '🔲 Lire le 2D-Doc'; }
    }
}

/** Manual trigger: read 2D-Doc from the already-uploaded file */
async function scan2DDocFromUpload() {
    const file = _docFile; // from document.js
    if (!file) { Dialog.toast('Chargez d\'abord un fichier.', 'error'); return; }

    const btn = document.getElementById('doc-btn-scan-image');
    if (btn) { btn.disabled = true; btn.textContent = '🔲 Lecture…'; }

    try {
        const raw = await decodeDataMatrixFromFile(file);
        if (raw) {
            show2DDocResult(raw);
        } else {
            Dialog.toast('Aucun code 2D-Doc détecté dans l\'image.', 'error');
        }
    } catch (e) {
        Dialog.toast('Erreur : ' + e.message, 'error');
    } finally {
        if (btn) { btn.disabled = false; btn.textContent = '🔲 Lire le 2D-Doc'; }
    }
}

/** Open camera scanner modal */
async function openCameraScanner() {
    openModal('twodoc-camera-modal');
    const videoEl   = document.getElementById('twodoc-video');
    const statusEl  = document.getElementById('twodoc-camera-status');
    statusEl.textContent = '⏳ Chargement de la caméra…';

    _cameraStopFn = await startCameraScanner(
        videoEl,
        (raw) => {
            closeModal('twodoc-camera-modal');
            show2DDocResult(raw);
        },
        (err) => {
            statusEl.textContent = '⚠️ ' + err;
        }
    );

    if (_cameraStopFn) statusEl.textContent = 'Pointez la caméra vers le code 2D-Doc';
}

function stopCameraScanner() {
    if (_cameraStopFn) { _cameraStopFn(); _cameraStopFn = null; }
    closeModal('twodoc-camera-modal');
}

/** Parse raw 2D-Doc text and display result panel */
function show2DDocResult(raw) {
    const parsed = parse2DDoc(raw);
    _twodocParsed = parsed;

    const panel   = document.getElementById('doc-2ddoc-result');
    const typeEl  = document.getElementById('doc-2ddoc-type');
    const tableEl = document.getElementById('doc-2ddoc-fields');

    if (!parsed) {
        if (panel) {
            panel.style.display = '';
            panel.querySelector('.twodoc-result-head').innerHTML =
                '⚠️ Code 2D-Doc lu mais format non reconnu.';
            if (tableEl) tableEl.innerHTML = '';
        }
        return;
    }

    const info  = parsed.docTypeInfo;
    const color = info ? (DOC_TYPES.find(t => t.key === info.docType)?.color || '#4A90D9') : '#4A90D9';

    if (typeEl) {
        typeEl.textContent  = (info?.label || 'Document 2D-Doc') + ' — Version ' + parsed.version;
        typeEl.style.background = color + '22';
        typeEl.style.borderLeft = '3px solid ' + color;
    }

    // Build fields table
    const rows = [];
    // Header fields
    if (parsed.issueDate) rows.push(['Date d\'émission', parsed.issueDate.split('-').reverse().join('/')]);
    // Document fields
    for (const [code, value] of Object.entries(parsed.fields)) {
        const label = TWODOC_FIELD_LABELS[code] || code;
        let display = value;
        // Format dates (DDMMYYYY)
        if (['AD', 'AG', 'AH', 'CA', 'DF'].includes(code) && /^\d{8}$/.test(value)) {
            display = value.substring(0, 2) + '/' + value.substring(2, 4) + '/' + value.substring(4);
        }
        // Format amounts (cents)
        if (['BD', 'BE', 'BF', 'DG'].includes(code) && /^\d+$/.test(value)) {
            display = _centsToEur(value);
        }
        rows.push([label, display]);
    }

    if (tableEl) {
        tableEl.innerHTML = rows.map(([k, v]) =>
            `<tr><th>${escapeHtml(k)}</th><td>${escapeHtml(v)}</td></tr>`
        ).join('');
    }

    if (panel) panel.style.display = '';
}

/** Apply 2D-Doc data to form and collapse the result panel */
function apply2DocAndCollapse() {
    if (!_twodocParsed) return;
    apply2DocToForm(_twodocParsed);
    const panel = document.getElementById('doc-2ddoc-result');
    if (panel) panel.style.display = 'none';
    Dialog.toast('Formulaire pré-rempli ✓', 'success');
}
