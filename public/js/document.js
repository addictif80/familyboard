// ============================================
// FamilyBoard - Document Manager JS
// ============================================

let _docFile      = null;
let _detailItem   = null;
let _searchTimer  = null;

// ── Filters ───────────────────────────────────────────────────────────────

function filterByType(type) {
    const url = new URL(location.href);
    url.searchParams.set('type', type);
    url.searchParams.delete('q');
    location.href = url.toString();
}

function filterByUser(userId) {
    const url = new URL(location.href);
    url.searchParams.set('user', userId || '');
    location.href = url.toString();
}

function searchDocs(q) {
    clearTimeout(_searchTimer);
    _searchTimer = setTimeout(async () => {
        const term = q.trim().toLowerCase();

        // Instant client-side filter
        document.querySelectorAll('#docs-grid .doc-card').forEach(el => {
            const type  = el.dataset.type || '';
            const matchType = !DOC_CURRENT_FILTER_TYPE || type === DOC_CURRENT_FILTER_TYPE;
            el.style.display = matchType ? '' : 'none';
        });

        if (term.length < 2) return;

        // Server-side full-text (finds in OCR text)
        try {
            const res = await apiFetch(
                `${BASE_URL}/api/documents/search?q=${encodeURIComponent(q)}&type=${encodeURIComponent(DOC_CURRENT_FILTER_TYPE)}&user=${DOC_CURRENT_FILTER_USER}`
            );
            if (res.success) {
                const ids = new Set(res.items.map(i => String(i.id)));
                document.querySelectorAll('#docs-grid .doc-card').forEach(el => {
                    el.style.display = ids.has(el.dataset.id) ? '' : 'none';
                });
            }
        } catch(e) {}
    }, 350);
}

// ── Add / Edit Modal ──────────────────────────────────────────────────────

function openDocModal() {
    _docFile = null;
    document.getElementById('doc-modal-title').textContent = 'Ajouter un document';
    document.getElementById('doc-id').value = '';
    document.getElementById('doc-title').value = '';
    document.getElementById('doc-type').value = 'auto';
    document.getElementById('doc-issuer').value = '';
    document.getElementById('doc-issue-date').value = '';
    document.getElementById('doc-expiry-date').value = '';
    document.getElementById('doc-tags').value = '';
    document.getElementById('doc-notes').value = '';
    const custodyToggle = document.getElementById('doc-custody-toggle');
    if (custodyToggle) {
        custodyToggle.checked = false;
        document.getElementById('doc-custody-select-wrap').style.display = 'none';
        document.getElementById('doc-custody-schedule').value = '';
    }
    document.getElementById('doc-ocr-text').value = '';
    document.getElementById('doc-ocr-details').style.display = 'none';
    document.getElementById('doc-ocr-status').style.display = 'none';
    document.getElementById('doc-detected-type').style.display = 'none';
    // Reset member checkboxes: check only current user
    document.querySelectorAll('.doc-member-cb').forEach(cb => {
        cb.checked = (parseInt(cb.value) === DOC_CURRENT_USER_ID);
    });
    resetDocFileZone();
    openModal('doc-modal');
}

function openEditDocModal(item) {
    _docFile = null;
    document.getElementById('doc-modal-title').textContent = 'Modifier le document';
    document.getElementById('doc-id').value = item.id;
    document.getElementById('doc-title').value = item.title || '';
    document.getElementById('doc-type').value = item.doc_type || 'other';
    document.getElementById('doc-issuer').value = item.issuer || '';
    document.getElementById('doc-issue-date').value = item.issue_date || '';
    document.getElementById('doc-expiry-date').value = item.expiry_date || '';
    document.getElementById('doc-tags').value = item.tags || '';
    document.getElementById('doc-notes').value = item.notes || '';
    const custodyToggle = document.getElementById('doc-custody-toggle');
    if (custodyToggle) {
        const wrap = document.getElementById('doc-custody-select-wrap');
        custodyToggle.checked = !!item.custody_schedule_id;
        wrap.style.display = item.custody_schedule_id ? '' : 'none';
        document.getElementById('doc-custody-schedule').value = item.custody_schedule_id || '';
    }
    // Check member checkboxes based on item.members array
    const memberIds = new Set((item.members || []).map(m => parseInt(m.id)));
    if (memberIds.size === 0 && item.user_id) memberIds.add(parseInt(item.user_id));
    document.querySelectorAll('.doc-member-cb').forEach(cb => {
        cb.checked = memberIds.has(parseInt(cb.value));
    });
    document.getElementById('doc-detected-type').style.display = 'none';
    document.getElementById('doc-ocr-status').style.display = 'none';

    if (item.ocr_text) {
        document.getElementById('doc-ocr-text').value = item.ocr_text;
        document.getElementById('doc-ocr-details').style.display = '';
    } else {
        document.getElementById('doc-ocr-details').style.display = 'none';
    }

    const preview = document.getElementById('doc-file-preview');
    const label   = document.getElementById('doc-upload-label');
    if (item.file_path) {
        label.style.display = 'none';
        preview.style.display = 'flex';
        preview.innerHTML = `<span>📎 ${escapeHtml(item.file_original || 'Document existant')}</span>
            <a href="${BASE_URL}/documents/file/${item.id}" target="_blank" class="btn btn-sm btn-secondary" style="margin-left:.5rem">Voir</a>
            <button class="btn btn-sm btn-secondary" style="margin-left:.25rem" onclick="resetDocFileZone()">Remplacer</button>`;
    } else {
        resetDocFileZone();
    }

    closeModal('doc-detail-modal');
    openModal('doc-modal');
}

function resetDocFileZone() {
    _docFile = null;
    const inp = document.getElementById('doc-file');
    if (inp) inp.value = '';
    document.getElementById('doc-upload-label').style.display = '';
    document.getElementById('doc-file-preview').style.display = 'none';
    document.getElementById('doc-file-preview').innerHTML = '';
    document.getElementById('doc-drop-zone').classList.remove('drag-over');
    const scanBtn = document.getElementById('doc-btn-scan-image');
    if (scanBtn) scanBtn.style.display = 'none';
    const result = document.getElementById('doc-2ddoc-result');
    if (result) result.style.display = 'none';
}

// ── File handling + OCR ───────────────────────────────────────────────────

function handleDocDrop(e) {
    e.preventDefault();
    document.getElementById('doc-drop-zone').classList.remove('drag-over');
    const file = e.dataTransfer.files[0];
    if (file) handleDocFile(file);
}

async function handleDocFile(file) {
    if (!file) return;
    _docFile = file;

    const label   = document.getElementById('doc-upload-label');
    const preview = document.getElementById('doc-file-preview');
    label.style.display = 'none';
    preview.style.display = 'flex';
    preview.innerHTML = `<span>📄 ${escapeHtml(file.name)} (${(file.size/1024/1024).toFixed(2)} Mo)</span>
        <button class="btn btn-sm btn-secondary" style="margin-left:.5rem" onclick="resetDocFileZone()">✕</button>`;

    if (file.type.startsWith('image/')) {
        const thumb = document.createElement('img');
        thumb.src = URL.createObjectURL(file);
        thumb.style.cssText = 'max-height:60px;max-width:120px;border-radius:4px;margin-right:.5rem;object-fit:cover';
        preview.insertBefore(thumb, preview.firstChild);
    }

    // Show the 2D-Doc scan button
    const scanBtn = document.getElementById('doc-btn-scan-image');
    if (scanBtn && file.type !== 'application/pdf') scanBtn.style.display = '';

    // Hide any previous 2D-Doc result
    const prev = document.getElementById('doc-2ddoc-result');
    if (prev) prev.style.display = 'none';

    // Run OCR and 2D-Doc detection in parallel
    await Promise.all([
        runDocOcr(file),
        typeof try2DDocFromFile === 'function' ? try2DDocFromFile(file) : Promise.resolve(),
    ]);
}

async function runDocOcr(file) {
    const status = document.getElementById('doc-ocr-status');
    status.style.display = '';
    status.textContent = '⏳ OCR en cours…';

    const fd = new FormData();
    fd.append('file', file);

    let data;
    try {
        const resp = await fetch(`${BASE_URL}/api/documents/ocr`, { method: 'POST', body: fd });
        data = await resp.json();
    } catch(e) {
        status.textContent = '⚠️ Erreur lors de l\'OCR.';
        return;
    }

    if (!data.success || !data.text) {
        status.textContent = '⚠️ ' + (data.error || 'OCR indisponible.');
        return;
    }

    status.textContent = '✅ OCR réalisé.';
    document.getElementById('doc-ocr-text').value = data.text;
    document.getElementById('doc-ocr-details').style.display = '';

    // Show detected type
    if (data.classified && data.classified.type !== 'other') {
        const c = data.classified;
        const banner = document.getElementById('doc-detected-type');
        banner.style.display = '';
        banner.style.background = c.color + '22';
        banner.style.borderLeft = '3px solid ' + c.color;
        banner.innerHTML = `${c.icon} Type détecté : <strong>${escapeHtml(c.label)}</strong>
            <button class="btn btn-sm btn-secondary" style="margin-left:.75rem"
                    onclick="applyDetectedType('${c.type}')">Appliquer</button>`;
    }

    // Auto-fill fields from OCR text
    parseDocOcrFields(data.text, data.classified);

    // Auto-apply detected type if selector is on 'auto'
    if (document.getElementById('doc-type').value === 'auto' && data.classified?.type) {
        document.getElementById('doc-type').value = data.classified.type;
    }
}

function applyDetectedType(type) {
    document.getElementById('doc-type').value = type;
    document.getElementById('doc-detected-type').style.display = 'none';
}

function parseDocOcrFields(text) {
    if (!text) return;

    // Date d'émission: dd/mm/yyyy
    if (!document.getElementById('doc-issue-date').value) {
        const m = text.match(/\b(\d{2})[\/\-\.](\d{2})[\/\-\.](\d{4})\b/);
        if (m) document.getElementById('doc-issue-date').value = `${m[3]}-${m[2]}-${m[1]}`;
    }

    // Issuer heuristics: line with "Direction", "Mairie", "Ministère", etc.
    if (!document.getElementById('doc-issuer').value) {
        const issuers = [
            /direction\s+générale\s+[\w\s]+/i,
            /mairie\s+(?:de|d')\s*[\w\-]+/i,
            /ministère\s+(?:de|d'|des)\s*[\w\s]+/i,
            /préfecture\s+(?:de|d')\s*[\w\-]+/i,
            /université\s+(?:de|d')\s*[\w\s]+/i,
            /caisse\s+\w+/i,
        ];
        for (const re of issuers) {
            const match = text.match(re);
            if (match) {
                document.getElementById('doc-issuer').value = match[0].trim().slice(0, 100);
                break;
            }
        }
    }

    // Auto-title suggestion if empty
    if (!document.getElementById('doc-title').value) {
        const lines = text.split('\n').map(l => l.trim()).filter(l => l.length > 4 && l.length < 80);
        if (lines[0]) document.getElementById('doc-title').value = lines[0];
    }
}

// ── Save / Delete ─────────────────────────────────────────────────────────

async function saveDoc() {
    const title = document.getElementById('doc-title').value.trim();
    if (!title) { Dialog.toast('L\'intitulé est obligatoire.', 'error'); return; }

    const btn = document.getElementById('doc-save-btn');
    btn.disabled = true;
    btn.textContent = 'Enregistrement…';

    const id = document.getElementById('doc-id').value;
    const fd = new FormData();
    fd.append('title',       title);
    fd.append('doc_type',    document.getElementById('doc-type').value);
    fd.append('issuer',      document.getElementById('doc-issuer').value);
    fd.append('issue_date',  document.getElementById('doc-issue-date').value);
    fd.append('expiry_date', document.getElementById('doc-expiry-date').value);
    fd.append('tags',        document.getElementById('doc-tags').value);
    fd.append('notes',       document.getElementById('doc-notes').value);
    fd.append('ocr_text',    document.getElementById('doc-ocr-text').value);
    const custodyToggle = document.getElementById('doc-custody-toggle');
    if (custodyToggle && custodyToggle.checked) {
        fd.append('custody_schedule_id', document.getElementById('doc-custody-schedule').value);
    }
    document.querySelectorAll('.doc-member-cb:checked').forEach(cb => {
        fd.append('member_ids[]', cb.value);
    });
    if (_docFile) fd.append('file', _docFile);

    const url = id ? `${BASE_URL}/api/documents/${id}` : `${BASE_URL}/api/documents`;
    try {
        const res  = await fetch(url, { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) { closeModal('doc-modal'); location.reload(); }
        else { Dialog.toast(data.error || 'Erreur.', 'error'); btn.disabled = false; btn.textContent = 'Enregistrer'; }
    } catch(e) {
        Dialog.toast('Erreur réseau.', 'error');
        btn.disabled = false; btn.textContent = 'Enregistrer';
    }
}

async function deleteDoc(id) {
    if (!await Dialog.confirm('Supprimer ce document et son fichier ?')) return;
    const res = await apiFetch(`${BASE_URL}/api/documents/${id}/delete`, { method: 'POST' });
    if (res.success) {
        const el = document.querySelector(`.doc-card[data-id="${id}"]`);
        if (el) el.remove();
    }
}

// ── Detail Modal ──────────────────────────────────────────────────────────

function openDocDetail(item) {
    _detailItem = item;

    const header = document.getElementById('doc-detail-header');
    header.style.background = item.type_color || '#4A90D9';
    header.style.color = '#fff';
    header.querySelector('h3').textContent = item.type_icon + ' ' + item.title;
    header.querySelector('button').style.color = '#fff';

    const body   = document.getElementById('doc-detail-body');
    const rows = [
        item.type_label  && ['Type',       item.type_icon + ' ' + item.type_label],
        item.issuer      && ['Émetteur',    escapeHtml(item.issuer)],
        item.issue_date  && ['Émis le',     fmtDate(item.issue_date)],
        item.expiry_date && ['Expire le',   fmtDate(item.expiry_date) + expiryBadge(item)],
        (item.members && item.members.length) && ['Appartient à', item.members.map(m =>
            `${avatarHtml(m.avatar, m.color, m.name, 'user-avatar-xs', m.name)} ${escapeHtml(m.name)}`
        ).join('&nbsp; ')],
        item.tags        && ['Tags',        escapeHtml(item.tags)],
        item.notes       && ['Notes',       escapeHtml(item.notes)],
    ].filter(Boolean);

    body.innerHTML = `
        <table class="doc-detail-table">
            ${rows.map(([k,v]) => `<tr><th>${escapeHtml(k)}</th><td>${v}</td></tr>`).join('')}
        </table>
        ${item.file_path ? `
        <div style="margin-top:1rem;border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;max-height:500px">
            ${item.file_mime && item.file_mime.startsWith('image/')
                ? `<img src="${BASE_URL}/documents/file/${item.id}" style="width:100%;max-height:480px;object-fit:contain">`
                : `<iframe src="${BASE_URL}/documents/file/${item.id}" style="width:100%;height:480px;border:none"></iframe>`
            }
        </div>` : ''}
        ${item.ocr_text ? `
        <details style="margin-top:.75rem">
            <summary style="cursor:pointer;font-size:.8rem;color:var(--text-muted)">Texte OCR extrait</summary>
            <pre style="font-size:.72rem;white-space:pre-wrap;margin-top:.4rem;background:var(--bg);padding:.75rem;border-radius:var(--radius)">${escapeHtml(item.ocr_text)}</pre>
        </details>` : ''}
    `;

    document.getElementById('doc-detail-edit-btn').onclick = () => openEditDocModal(item);
    openModal('doc-detail-modal');
}

function fmtDate(d) {
    if (!d) return '';
    const [y,m,day] = d.split('-');
    return `${day}/${m}/${y}`;
}

function expiryBadge(item) {
    if (!item.days_until_expiry && item.days_until_expiry !== 0) return '';
    if (item.days_until_expiry < 0) return ' <span style="color:var(--danger);font-size:.75rem">(expirée)</span>';
    if (item.days_until_expiry <= 90) return ` <span style="color:#E67E22;font-size:.75rem">(${item.days_until_expiry}j restants)</span>`;
    return '';
}
