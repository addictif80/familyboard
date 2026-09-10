// ============================================
// FamilyBoard - Bons plans
// ============================================

function filterDeals() {
    const category = document.getElementById('deal-filter-category').value;
    const hideExpired = document.getElementById('deal-filter-hide-expired').checked;
    document.querySelectorAll('.deal-card').forEach(card => {
        const matchesCategory = !category || card.dataset.category === category;
        const isExpired = card.dataset.expired === '1';
        card.style.display = (matchesCategory && !(hideExpired && isExpired)) ? '' : 'none';
    });
}

function resetDealFileField() {
    document.getElementById('deal-file').value = '';
    document.getElementById('deal-remove-file').checked = false;
    document.getElementById('deal-current-file').style.display = 'none';
}

function openNewDealModal() {
    document.getElementById('deal-modal-title').textContent = 'Nouveau bon plan';
    document.getElementById('deal-id').value = '';
    document.getElementById('deal-title').value = '';
    document.getElementById('deal-description').value = '';
    document.getElementById('deal-category').value = 'autre';
    document.getElementById('deal-expires').value = '';
    document.getElementById('deal-discount').value = '';
    document.getElementById('deal-promo-code').value = '';
    document.getElementById('deal-url').value = '';
    resetDealFileField();
    openModal('deal-modal');
}

function openEditDealModal(deal) {
    document.getElementById('deal-modal-title').textContent = 'Modifier le bon plan';
    document.getElementById('deal-id').value = deal.id;
    document.getElementById('deal-title').value = deal.title;
    document.getElementById('deal-description').value = deal.description || '';
    document.getElementById('deal-category').value = deal.category;
    document.getElementById('deal-expires').value = deal.expires_at || '';
    document.getElementById('deal-discount').value = deal.discount || '';
    document.getElementById('deal-promo-code').value = deal.promo_code || '';
    document.getElementById('deal-url').value = deal.url || '';
    resetDealFileField();
    if (deal.file_path) {
        document.getElementById('deal-current-file').style.display = '';
        document.getElementById('deal-current-file-link').href = `${BASE_URL}/deals/${deal.id}/file`;
    }
    openModal('deal-modal');
}

async function saveDeal() {
    const title = document.getElementById('deal-title').value.trim();
    if (!title) { Dialog.toast('Le titre est requis.', 'error'); return; }

    const fd = new FormData();
    fd.append('title', title);
    fd.append('description', document.getElementById('deal-description').value);
    fd.append('category', document.getElementById('deal-category').value);
    fd.append('expires_at', document.getElementById('deal-expires').value);
    fd.append('discount', document.getElementById('deal-discount').value);
    fd.append('promo_code', document.getElementById('deal-promo-code').value);
    fd.append('url', document.getElementById('deal-url').value);
    if (document.getElementById('deal-remove-file').checked) fd.append('remove_file', '1');
    const fileInput = document.getElementById('deal-file');
    if (fileInput.files[0]) fd.append('file', fileInput.files[0]);

    const id = document.getElementById('deal-id').value;
    const url = id ? `${BASE_URL}/api/deals/${id}` : `${BASE_URL}/api/deals`;
    // Ne pas passer par apiFetch() : son Content-Type: application/json par défaut casserait
    // le multipart/form-data nécessaire à l'envoi du fichier. X-Requested-With reste nécessaire
    // pour passer le contrôle CSRF (voir BaseController::requireAuth()) — un FormData ne peut
    // pas porter le jeton CSRF comme un <form> classique le ferait.
    const res = await fetch(url, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
    const r = await res.json();
    if (r.success) {
        window.location.reload();
    } else {
        Dialog.toast(r.error || 'Erreur.', 'error');
    }
}

async function deleteDeal(id) {
    const ok = await Dialog.confirm('Supprimer ce bon plan ?');
    if (!ok) return;
    const r = await apiFetch(`${BASE_URL}/api/deals/${id}/delete`, { method: 'POST' });
    if (r.success) window.location.reload();
    else Dialog.toast(r.error || 'Erreur.', 'error');
}
