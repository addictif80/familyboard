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
    });

    return { save, renderBarcode, openScanner, closeScanner };
})();
