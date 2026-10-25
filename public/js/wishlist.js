// ---- Liste de cadeaux ----

function openWishlistModal(item, familyChildId) {
    document.getElementById('wishlist-modal-title').textContent = item ? 'Modifier le souhait' : 'Ajouter un souhait';
    document.getElementById('wl-id').value = item ? item.id : '';
    document.getElementById('wl-family-child-id').value = familyChildId || '';
    document.getElementById('wl-title').value = item ? item.title : '';
    document.getElementById('wl-description').value = item ? (item.description || '') : '';
    document.getElementById('wl-url').value = item ? (item.url || '') : '';
    document.getElementById('wl-price').value = item ? (item.price || '') : '';
    document.getElementById('wl-priority').value = item ? item.priority : 'medium';
    document.getElementById('wl-image-file').value = '';
    document.getElementById('wl-remove-image').checked = false;

    const preview = document.getElementById('wl-image-preview');
    const previewImg = document.getElementById('wl-image-preview-img');
    if (item && item.image_path) {
        previewImg.src = BASE_URL + item.image_path;
        preview.style.display = '';
    } else {
        preview.style.display = 'none';
        previewImg.src = '';
    }
    openModal('wishlist-modal');
}

async function saveWishlistItem() {
    const id = document.getElementById('wl-id').value;
    const familyChildId = document.getElementById('wl-family-child-id').value;
    const title = document.getElementById('wl-title').value.trim();
    const url = document.getElementById('wl-url').value.trim();
    if (!title && !url) { Dialog.toast('Indiquez un titre ou collez un lien.', 'error'); return; }

    const fd = new FormData();
    fd.append('title', title);
    fd.append('description', document.getElementById('wl-description').value.trim());
    fd.append('url', url);
    fd.append('price', document.getElementById('wl-price').value);
    fd.append('priority', document.getElementById('wl-priority').value);
    if (familyChildId) fd.append('family_child_id', familyChildId);
    if (document.getElementById('wl-remove-image').checked) fd.append('remove_image', '1');
    const fileInput = document.getElementById('wl-image-file');
    if (fileInput.files[0]) fd.append('image', fileInput.files[0]);

    const apiUrl = id ? BASE_URL + '/api/wishlist/' + id : BASE_URL + '/api/wishlist';
    const r = await fetch(apiUrl, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd }).then(res => res.json());
    if (r.success) {
        closeModal('wishlist-modal');
        window.location.reload();
    } else {
        Dialog.toast(r.error || 'Erreur.', 'error');
    }
}

async function deleteWishlistItem(id) {
    const ok = await Dialog.confirm('Supprimer ce souhait ?');
    if (!ok) return;
    const r = await apiFetch(BASE_URL + '/api/wishlist/' + id + '/delete', { method: 'POST', body: '{}' });
    if (r.success) window.location.reload();
    else Dialog.toast(r.error || 'Erreur.', 'error');
}

async function reserveWishlistItem(id) {
    const r = await apiFetch(BASE_URL + '/api/wishlist/' + id + '/reserve', { method: 'POST', body: '{}' });
    if (r.success) window.location.reload();
    else Dialog.toast(r.error || 'Erreur.', 'error');
}

async function unreserveWishlistItem(id) {
    const ok = await Dialog.confirm('Annuler votre réservation sur ce cadeau ?');
    if (!ok) return;
    const r = await apiFetch(BASE_URL + '/api/wishlist/' + id + '/unreserve', { method: 'POST', body: '{}' });
    if (r.success) window.location.reload();
    else Dialog.toast(r.error || 'Erreur.', 'error');
}

async function setWishlistPurchased(id, purchased) {
    const r = await apiFetch(BASE_URL + '/api/wishlist/' + id + '/purchased', { method: 'POST', body: JSON.stringify({ purchased }) });
    if (r.success) window.location.reload();
    else Dialog.toast(r.error || 'Erreur.', 'error');
}

// ---- Listes des enfants (pas de compte) ----

function openNewWishlistChildModal() {
    document.getElementById('wlc-name').value = '';
    openModal('wishlist-child-modal');
}

async function saveWishlistChild() {
    const name = document.getElementById('wlc-name').value.trim();
    if (!name) { Dialog.toast('Le prénom est requis.', 'error'); return; }
    const r = await apiFetch(BASE_URL + '/api/children', { method: 'POST', body: JSON.stringify({ name }) });
    if (r.success) {
        closeModal('wishlist-child-modal');
        window.location.reload();
    } else {
        Dialog.toast(r.error || 'Erreur.', 'error');
    }
}

function copyChildWishlistLink(token) {
    copyCode(`${window.location.origin}${BASE_URL}/liste-cadeaux/${token}`);
}
