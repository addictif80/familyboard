// ============================================
// FamilyBoard - Cartes de fidélité
// ============================================

function openNewLoyaltyCardModal() {
    document.getElementById('loyalty-card-modal-title').textContent = 'Nouvelle carte';
    document.getElementById('loyalty-card-id').value = '';
    document.getElementById('loyalty-card-store').value = '';
    document.getElementById('loyalty-card-color').value = '#7C5CFF';
    document.getElementById('loyalty-card-barcode').value = '';
    document.getElementById('loyalty-card-format').value = 'code128';
    document.getElementById('loyalty-card-notes').value = '';
    LoyaltyCards.resetColorTouched();
    openModal('loyalty-card-modal');
}

function openEditLoyaltyCardModal(c) {
    document.getElementById('loyalty-card-modal-title').textContent = 'Modifier la carte';
    document.getElementById('loyalty-card-id').value = c.id;
    document.getElementById('loyalty-card-store').value = c.store_name;
    document.getElementById('loyalty-card-color').value = c.card_color || '#7C5CFF';
    document.getElementById('loyalty-card-barcode').value = c.barcode_value;
    document.getElementById('loyalty-card-format').value = c.barcode_format || 'code128';
    document.getElementById('loyalty-card-notes').value = c.notes || '';
    // Une carte existante a déjà sa couleur (choisie manuellement ou déjà reconnue) : on ne la
    // réécrase pas tant que le nom du magasin n'est pas modifié vers une autre enseigne connue.
    LoyaltyCards.markColorTouched();
    openModal('loyalty-card-modal');
}

async function deleteLoyaltyCard(id) {
    const ok = await Dialog.confirm('Supprimer cette carte de fidélité ?');
    if (!ok) return;
    const r = await apiFetch(`${BASE_URL}/api/loyalty-cards/${id}/delete`, { method: 'POST' });
    if (r.success) window.location.href = `${BASE_URL}/loyalty-cards`;
}

var LoyaltyCards = (function () {
    let scannerStream = null;
    let scannerTimer = null;
    let colorTouched = false;

    // Reconnaissance d'enseigne façon Google Wallet : couleur de marque connue, pas de logo
    // (éviterait d'héberger des marques déposées) — liste volontairement limitée aux enseignes
    // courantes en France, facile à étendre au besoin.
    const BRANDS = {
        'carrefour': '#004E9F', 'leclerc': '#0054A6', 'intermarche': '#E2001A',
        'intermarché': '#E2001A', 'auchan': '#E2001A', 'monoprix': '#FF3366',
        'super u': '#E2001A', 'u express': '#E2001A', 'casino': '#00843D',
        'lidl': '#0050AA', 'aldi': '#00549F', 'cora': '#E2231A',
        'fnac': '#CCCC00', 'darty': '#FF6600', 'boulanger': '#E2001A',
        'decathlon': '#0082C3', 'cultura': '#E2001A', 'cdiscount': '#4DB7FE',
        'sephora': '#000000', 'nocibe': '#E6007E', 'nocibé': '#E6007E',
        'yves rocher': '#00884A', 'marionnaud': '#B4005A',
        'zara': '#000000', 'h&m': '#E50010', 'c&a': '#DB0A24',
        'ikea': '#0058A3', 'but': '#E2001A', 'conforama': '#FF6600',
        'leroy merlin': '#78BE20', 'castorama': '#0066B3', 'brico depot': '#E2001A',
        'total': '#EF2E2E', 'totalenergies': '#EF2E2E', 'leclerc drive': '#0054A6',
        'picard': '#E2001A', 'biocoop': '#6FB245', 'naturalia': '#6FB245',
        'la poste': '#FFD200', 'sncf': '#A1006B', 'pharmacie': '#00A651',
    };

    function normalize(str) {
        return (str || '').toLowerCase().trim().normalize('NFD').replace(/[̀-ͯ]/g, '');
    }

    function markColorTouched() { colorTouched = true; }
    function resetColorTouched() { colorTouched = false; }

    // Appelé à chaque frappe dans le champ magasin : dès que le nom tapé correspond (ou
    // commence à correspondre) à une enseigne connue, on propose sa couleur de marque —
    // jamais si l'utilisateur a déjà choisi sa propre couleur pour cette carte.
    function matchBrandColor(typedName) {
        if (colorTouched) return;
        const n = normalize(typedName);
        if (n.length < 3) return;
        const key = Object.keys(BRANDS).find(k => normalize(k) === n) ||
                    Object.keys(BRANDS).find(k => normalize(k).startsWith(n) || n.startsWith(normalize(k)));
        if (key) document.getElementById('loyalty-card-color').value = BRANDS[key];
    }

    function fillBrandDatalist() {
        const list = document.getElementById('loyalty-brand-list');
        if (!list) return;
        list.innerHTML = Object.keys(BRANDS)
            .map(name => `<option value="${name.replace(/\b\w/g, c => c.toUpperCase())}"></option>`)
            .join('');
    }

    async function save() {
        const storeName = document.getElementById('loyalty-card-store').value.trim();
        const barcode = document.getElementById('loyalty-card-barcode').value.trim();
        if (!storeName || !barcode) { Dialog.toast('Le magasin et le numéro de carte sont requis.', 'error'); return; }
        const payload = {
            store_name: storeName,
            card_color: document.getElementById('loyalty-card-color').value,
            barcode_value: barcode,
            barcode_format: document.getElementById('loyalty-card-format').value,
            notes: document.getElementById('loyalty-card-notes').value,
        };
        const id = document.getElementById('loyalty-card-id').value;
        const url = id ? `${BASE_URL}/api/loyalty-cards/${id}` : `${BASE_URL}/api/loyalty-cards`;
        const r = await apiFetch(url, { method: 'POST', body: JSON.stringify(payload) });
        if (r.success) {
            window.location.href = `${BASE_URL}/loyalty-cards?id=${id || r.id}`;
        } else {
            Dialog.toast(r.error || 'Erreur.', 'error');
        }
    }

    // Affiche le code-barres/QR de la carte sélectionnée, généré localement (aucun service
    // tiers ne voit jamais le numéro de carte).
    function renderBarcode(value, format) {
        if (format === 'qrcode') {
            const el = document.getElementById('loyalty-qrcode');
            if (!el || typeof qrcode !== 'function') return;
            const qr = qrcode(0, 'M');
            qr.addData(value);
            qr.make();
            el.innerHTML = qr.createSvgTag(6);
            return;
        }
        const svg = document.getElementById('loyalty-barcode');
        if (!svg || typeof JsBarcode !== 'function') return;
        try {
            JsBarcode(svg, value, {
                format: format === 'ean13' ? 'EAN13' : 'CODE128',
                lineColor: '#1a1030',
                width: 2,
                height: 90,
                displayValue: false,
                margin: 4,
            });
        } catch (e) {
            // Numéro incompatible avec le format choisi (ex: EAN-13 non numérique à 13 chiffres) —
            // on bascule sur Code 128, qui accepte n'importe quelle chaîne.
            JsBarcode(svg, value, { format: 'CODE128', lineColor: '#1a1030', width: 2, height: 90, displayValue: false, margin: 4 });
        }
    }

    // ── Scan caméra (API native BarcodeDetector — pas de librairie tierce) ──
    function scannerSupported() {
        return 'BarcodeDetector' in window && 'mediaDevices' in navigator;
    }

    async function openScanner() {
        if (!scannerSupported()) return;
        const modal = document.getElementById('loyalty-scanner-modal');
        const video = document.getElementById('loyalty-scanner-video');
        try {
            scannerStream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
        } catch (e) {
            Dialog.toast("Impossible d'accéder à la caméra.", 'error');
            return;
        }
        video.srcObject = scannerStream;
        modal.style.display = 'flex';

        const detector = new BarcodeDetector({ formats: ['ean_13', 'code_128', 'qr_code', 'upc_a', 'ean_8'] });
        scannerTimer = setInterval(async () => {
            try {
                const codes = await detector.detect(video);
                if (codes.length > 0) {
                    document.getElementById('loyalty-card-barcode').value = codes[0].rawValue;
                    if (codes[0].format === 'qr_code') document.getElementById('loyalty-card-format').value = 'qrcode';
                    else if (codes[0].format === 'ean_13') document.getElementById('loyalty-card-format').value = 'ean13';
                    else document.getElementById('loyalty-card-format').value = 'code128';
                    closeScanner();
                }
            } catch (e) { /* frame illisible, on retente */ }
        }, 400);
    }

    function closeScanner() {
        document.getElementById('loyalty-scanner-modal').style.display = 'none';
        if (scannerTimer) { clearInterval(scannerTimer); scannerTimer = null; }
        if (scannerStream) { scannerStream.getTracks().forEach(t => t.stop()); scannerStream = null; }
    }

    document.addEventListener('DOMContentLoaded', () => {
        const btn = document.getElementById('loyalty-card-scan-btn');
        if (btn && scannerSupported()) btn.style.display = 'inline-flex';
        if (typeof LOYALTY_SELECTED !== 'undefined' && LOYALTY_SELECTED) {
            renderBarcode(LOYALTY_SELECTED.value, LOYALTY_SELECTED.format);
        }
        fillBrandDatalist();
    });

    return { save, renderBarcode, openScanner, closeScanner, matchBrandColor, markColorTouched, resetColorTouched };
})();
