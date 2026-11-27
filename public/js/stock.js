// ============================================
// FamilyBoard - Stock frigo/congélo
// ============================================

function openStockModal() {
    document.getElementById('stock-modal-title').textContent = 'Nouvel article';
    document.getElementById('stock-id').value = '';
    document.getElementById('stock-name').value = '';
    document.getElementById('stock-location').value = 'fridge';
    document.getElementById('stock-category').value = '';
    document.getElementById('stock-quantity').value = '1';
    document.getElementById('stock-unit').value = '';
    document.getElementById('stock-expiry').value = '';
    document.getElementById('stock-notes').value = '';
    openModal('stock-modal');
}

function openEditStockModal(item) {
    document.getElementById('stock-modal-title').textContent = 'Modifier l\'article';
    document.getElementById('stock-id').value = item.id;
    document.getElementById('stock-name').value = item.name;
    document.getElementById('stock-location').value = item.location;
    document.getElementById('stock-category').value = item.category || '';
    document.getElementById('stock-quantity').value = item.quantity;
    document.getElementById('stock-unit').value = item.unit || '';
    document.getElementById('stock-expiry').value = item.expiry_date || '';
    document.getElementById('stock-notes').value = item.notes || '';
    openModal('stock-modal');
}

async function saveStockItem() {
    const name = document.getElementById('stock-name').value.trim();
    if (!name) { Dialog.toast('Le nom est requis.', 'error'); return; }
    const payload = {
        name,
        location: document.getElementById('stock-location').value,
        category: document.getElementById('stock-category').value,
        quantity: document.getElementById('stock-quantity').value,
        unit: document.getElementById('stock-unit').value,
        expiry_date: document.getElementById('stock-expiry').value,
        notes: document.getElementById('stock-notes').value,
    };
    const id = document.getElementById('stock-id').value;
    const url = id ? `${BASE_URL}/api/stock/items/${id}` : `${BASE_URL}/api/stock/items`;
    const r = await apiFetch(url, { method: 'POST', body: JSON.stringify(payload) });
    if (r.success) window.location.reload();
    else Dialog.toast(r.error || 'Erreur.', 'error');
}

async function deleteStockItem(id) {
    const ok = await Dialog.confirm('Supprimer cet article ?');
    if (!ok) return;
    const r = await apiFetch(`${BASE_URL}/api/stock/items/${id}/delete`, { method: 'POST' });
    if (r.success) window.location.reload();
    else Dialog.toast(r.error || 'Erreur.', 'error');
}

async function adjustStockQuantity(id, delta) {
    const r = await apiFetch(`${BASE_URL}/api/stock/items/${id}/adjust`, {
        method: 'POST',
        body: JSON.stringify({ delta }),
    });
    if (!r.success) { Dialog.toast(r.error || 'Erreur.', 'error'); return; }
    const row = document.querySelector(`tr[data-id="${id}"]`);
    const qtyEl = row?.querySelector('.stock-qty-value');
    if (qtyEl) {
        const unit = qtyEl.textContent.trim().split(' ').slice(1).join(' ');
        qtyEl.textContent = unit ? `${r.quantity} ${unit}` : String(r.quantity);
    }
    // Sans ça, le bouton "Modifier" garde la quantité telle qu'elle était au chargement de la
    // page : un simple clic +/- resterait invisible pour lui, et enregistrer une modif sans
    // toucher la quantité l'écraserait silencieusement avec l'ancienne valeur.
    const editBtn = row?.querySelector('button[title="Modifier"]');
    if (editBtn) {
        try {
            const item = JSON.parse(editBtn.dataset.item);
            item.quantity = r.quantity;
            editBtn.dataset.item = JSON.stringify(item);
        } catch { /* ignore — le prochain rechargement de page resynchronisera de toute façon */ }
    }
}
