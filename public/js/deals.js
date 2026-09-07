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
    openModal('deal-modal');
}

async function saveDeal() {
    const title = document.getElementById('deal-title').value.trim();
    if (!title) { Dialog.toast('Le titre est requis.', 'error'); return; }
    const payload = {
        title,
        description: document.getElementById('deal-description').value,
        category: document.getElementById('deal-category').value,
        expires_at: document.getElementById('deal-expires').value,
        discount: document.getElementById('deal-discount').value,
        promo_code: document.getElementById('deal-promo-code').value,
        url: document.getElementById('deal-url').value,
    };
    const id = document.getElementById('deal-id').value;
    const url = id ? `${BASE_URL}/api/deals/${id}` : `${BASE_URL}/api/deals`;
    const r = await apiFetch(url, { method: 'POST', body: JSON.stringify(payload) });
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
