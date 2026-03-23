// ============================================
// FamilyBoard - Warranty JS
// ============================================

let _warrantyFile = null; // File object pending upload

// ── Search ────────────────────────────────────────────────────────────────

let _searchTimer = null;
function searchWarranties(q) {
    clearTimeout(_searchTimer);
    _searchTimer = setTimeout(async () => {
        // Client-side filter first (instant feedback)
        const term = q.trim().toLowerCase();
        document.querySelectorAll('#warranty-grid .warranty-card').forEach(el => {
            const haystack = el.dataset.search || '';
            el.style.display = (!term || haystack.includes(term)) ? '' : 'none';
        });

        // Server-side search for OCR text (hidden in data-search)
        if (term.length >= 2) {
            const url = `${BASE_URL}/api/warranties/search?q=${encodeURIComponent(q)}`;
            try {
                const res = await apiFetch(url);
                if (res.success) renderWarrantyGrid(res.items);
            } catch(e) {}
        }
    }, 350);
}

function renderWarrantyGrid(items) {
    // Re-render only when server returns fresh results
    // (avoids complexity of full template duplication in JS — just reload)
    // Instead, update visibility based on IDs returned
    const ids = new Set(items.map(i => String(i.id)));
    document.querySelectorAll('#warranty-grid .warranty-card').forEach(el => {
        el.style.display = ids.has(el.dataset.id) ? '' : 'none';
    });
}

// ── Modal ─────────────────────────────────────────────────────────────────

function openWarrantyModal() {
    _warrantyFile = null;
    document.getElementById('wm-title').textContent = 'Ajouter une garantie';
    document.getElementById('wm-id').value = '';
    document.getElementById('wm-name').value = '';
    document.getElementById('wm-price').value = '';
    document.getElementById('wm-brand').value = '';
    document.getElementById('wm-model').value = '';
    document.getElementById('wm-serial').value = '';
    document.getElementById('wm-store').value = '';
    document.getElementById('wm-purchase-date').value = '';
    document.getElementById('wm-months').value = '24';
    document.getElementById('wm-notes').value = '';
    document.getElementById('wm-ocr-text').value = '';
    document.getElementById('wm-ocr-details').style.display = 'none';
    document.getElementById('wm-ocr-status').style.display = 'none';
    resetFileZone();
    openModal('warranty-modal');
}

function openEditWarrantyModal(item) {
    _warrantyFile = null;
    document.getElementById('wm-title').textContent = 'Modifier la garantie';
    document.getElementById('wm-id').value = item.id;
    document.getElementById('wm-name').value = item.title || '';
    document.getElementById('wm-price').value = item.price || '';
    document.getElementById('wm-brand').value = item.brand || '';
    document.getElementById('wm-model').value = item.model || '';
    document.getElementById('wm-serial').value = item.serial_number || '';
    document.getElementById('wm-store').value = item.store || '';
    document.getElementById('wm-purchase-date').value = item.purchase_date || '';
    document.getElementById('wm-months').value = item.warranty_months || '24';
    document.getElementById('wm-notes').value = item.notes || '';

    if (item.ocr_text) {
        document.getElementById('wm-ocr-text').value = item.ocr_text;
        document.getElementById('wm-ocr-details').style.display = '';
    } else {
        document.getElementById('wm-ocr-details').style.display = 'none';
    }
    document.getElementById('wm-ocr-status').style.display = 'none';

    // Show existing file indicator
    const preview = document.getElementById('wm-file-preview');
    const label   = document.getElementById('wm-upload-label');
    if (item.file_path) {
        label.style.display = 'none';
        preview.style.display = 'flex';
        preview.innerHTML = `<span>📎 ${escapeHtml(item.file_original || 'Document existant')}</span>
            <a href="${BASE_URL}/warranties/file/${item.id}" target="_blank" class="btn btn-sm btn-secondary" style="margin-left:.5rem">Voir</a>
            <button class="btn btn-sm btn-secondary" style="margin-left:.25rem" onclick="resetFileZone()">Remplacer</button>`;
    } else {
        resetFileZone();
    }

    openModal('warranty-modal');
}

function resetFileZone() {
    _warrantyFile = null;
    document.getElementById('wm-file').value = '';
    document.getElementById('wm-upload-label').style.display = '';
    document.getElementById('wm-file-preview').style.display = 'none';
    document.getElementById('wm-file-preview').innerHTML = '';
    document.getElementById('wm-ocr-status').style.display = 'none';
    document.getElementById('wm-drop-zone').classList.remove('drag-over');
}

// ── File handling + OCR ───────────────────────────────────────────────────

function handleWarrantyDrop(e) {
    e.preventDefault();
    document.getElementById('wm-drop-zone').classList.remove('drag-over');
    const file = e.dataTransfer.files[0];
    if (file) handleWarrantyFile(file);
}

async function handleWarrantyFile(file) {
    if (!file) return;
    _warrantyFile = file;

    // Show preview
    const label   = document.getElementById('wm-upload-label');
    const preview = document.getElementById('wm-file-preview');
    label.style.display = 'none';
    preview.style.display = 'flex';
    preview.innerHTML = `<span>📄 ${escapeHtml(file.name)} (${(file.size/1024/1024).toFixed(2)} Mo)</span>
        <button class="btn btn-sm btn-secondary" style="margin-left:.5rem" onclick="resetFileZone()">✕</button>`;

    // If it's an image, show thumbnail
    if (file.type.startsWith('image/')) {
        const thumb = document.createElement('img');
        thumb.src = URL.createObjectURL(file);
        thumb.style.cssText = 'max-height:60px;max-width:120px;border-radius:4px;margin-right:.5rem';
        preview.insertBefore(thumb, preview.firstChild);
    }

    // Run OCR
    await runOcr(file);
}

async function runOcr(file) {
    const status = document.getElementById('wm-ocr-status');
    status.style.display = '';
    status.textContent = '⏳ Extraction OCR en cours…';

    const fd = new FormData();
    fd.append('file', file);

    let data;
    try {
        const resp = await fetch(`${BASE_URL}/api/warranties/ocr`, { method: 'POST', body: fd });
        data = await resp.json();
    } catch(e) {
        status.textContent = '⚠️ Erreur lors de l\'OCR.';
        return;
    }

    if (!data.success || !data.text) {
        status.textContent = data.error || 'OCR non disponible sur ce serveur.';
        return;
    }

    status.textContent = '✅ OCR réalisé — champs pré-remplis si possible.';
    document.getElementById('wm-ocr-text').value = data.text;
    document.getElementById('wm-ocr-details').style.display = '';
    parseOcrIntoFields(data.text);
}

function parseOcrIntoFields(text) {
    if (!text) return;
    const lines = text.split('\n').map(l => l.trim()).filter(Boolean);

    // Date: dd/mm/yyyy or yyyy-mm-dd
    if (!document.getElementById('wm-purchase-date').value) {
        const dm = text.match(/\b(\d{2})[\/\-\.](\d{2})[\/\-\.](\d{4})\b/);
        if (dm) {
            document.getElementById('wm-purchase-date').value = `${dm[3]}-${dm[2]}-${dm[1]}`;
        } else {
            const ym = text.match(/\b(\d{4})[\/\-\.](\d{2})[\/\-\.](\d{2})\b/);
            if (ym) document.getElementById('wm-purchase-date').value = `${ym[1]}-${ym[2]}-${ym[3]}`;
        }
    }

    // Price: number followed by € or preceded by EUR
    if (!document.getElementById('wm-price').value) {
        const pm = text.match(/(\d[\d\s]*[,.]?\d*)\s*€|EUR\s*(\d[\d\s]*[,.]?\d*)/i);
        if (pm) {
            const raw = (pm[1] || pm[2]).replace(/\s/g, '').replace(',', '.');
            const val = parseFloat(raw);
            if (!isNaN(val) && val > 0 && val < 100000) {
                document.getElementById('wm-price').value = val.toFixed(2);
            }
        }
    }

    // Serial number: "N° de série", "S/N", "Serial"
    if (!document.getElementById('wm-serial').value) {
        const sn = text.match(/(?:n[°o]?\s*(?:de\s+s[ée]rie|s[ée]rie)|s\/n|serial\s*(?:no|number)?)[\s:]+([A-Z0-9\-]{5,30})/i);
        if (sn) document.getElementById('wm-serial').value = sn[1].trim();
    }

    // Store: common French store keywords
    const stores = ['fnac', 'darty', 'boulanger', 'amazon', 'cdiscount', 'leclerc', 'carrefour', 'ikea', 'leroy merlin', 'castorama', 'bricorama'];
    if (!document.getElementById('wm-store').value) {
        const lower = text.toLowerCase();
        const found = stores.find(s => lower.includes(s));
        if (found) document.getElementById('wm-store').value = found.charAt(0).toUpperCase() + found.slice(1);
    }
}

// ── Save / Delete ─────────────────────────────────────────────────────────

async function saveWarranty() {
    const title = document.getElementById('wm-name').value.trim();
    if (!title) { Dialog.toast('La désignation est obligatoire.', 'error'); return; }

    const btn = document.getElementById('wm-save-btn');
    btn.disabled = true;
    btn.textContent = 'Enregistrement…';

    const id = document.getElementById('wm-id').value;
    const fd = new FormData();

    fd.append('title',           title);
    fd.append('brand',           document.getElementById('wm-brand').value);
    fd.append('model',           document.getElementById('wm-model').value);
    fd.append('serial_number',   document.getElementById('wm-serial').value);
    fd.append('store',           document.getElementById('wm-store').value);
    fd.append('purchase_date',   document.getElementById('wm-purchase-date').value);
    fd.append('warranty_months', document.getElementById('wm-months').value);
    fd.append('price',           document.getElementById('wm-price').value);
    fd.append('notes',           document.getElementById('wm-notes').value);
    fd.append('ocr_text',        document.getElementById('wm-ocr-text').value);
    if (_warrantyFile) fd.append('file', _warrantyFile);

    const url = id ? `${BASE_URL}/api/warranties/${id}` : `${BASE_URL}/api/warranties`;
    try {
        const res = await fetch(url, { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            closeModal('warranty-modal');
            location.reload();
        } else {
            Dialog.toast(data.error || 'Erreur lors de l\'enregistrement.', 'error');
            btn.disabled = false;
            btn.textContent = 'Enregistrer';
        }
    } catch(e) {
        Dialog.toast('Erreur réseau.', 'error');
        btn.disabled = false;
        btn.textContent = 'Enregistrer';
    }
}

async function deleteWarranty(id) {
    if (!await Dialog.confirm('Supprimer cette garantie et son document ?')) return;
    const result = await apiFetch(`${BASE_URL}/api/warranties/${id}/delete`, { method: 'POST' });
    if (result.success) {
        const el = document.querySelector(`.warranty-card[data-id="${id}"]`);
        if (el) el.remove();
    }
}
