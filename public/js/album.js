// ============================================
// FamilyBoard - Albums photo
// ============================================

function albumOpenCreateModal() {
    document.getElementById('album-create-title').value = '';
    document.getElementById('album-create-description').value = '';
    openModal('album-create-modal');
}

async function albumCreate() {
    const title = document.getElementById('album-create-title').value.trim();
    const description = document.getElementById('album-create-description').value.trim();
    if (!title) { Dialog.toast('Le titre est obligatoire.', 'error'); return; }

    const btn = document.getElementById('album-create-save-btn');
    btn.disabled = true;

    const result = await apiFetch(BASE_URL + '/api/albums', {
        method: 'POST',
        body: JSON.stringify({ title, description }),
    });

    if (result.success) {
        location.href = BASE_URL + '/albums/' + result.id;
    } else {
        Dialog.toast(result.error || "Erreur lors de la création.", 'error');
        btn.disabled = false;
    }
}

function albumOpenEditModal(id, title, description) {
    document.getElementById('album-edit-id').value = id;
    document.getElementById('album-edit-title').value = title;
    document.getElementById('album-edit-description').value = description;
    openModal('album-edit-modal');
}

async function albumSaveEdit() {
    const id = document.getElementById('album-edit-id').value;
    const title = document.getElementById('album-edit-title').value.trim();
    const description = document.getElementById('album-edit-description').value.trim();
    if (!title) { Dialog.toast('Le titre est obligatoire.', 'error'); return; }

    const result = await apiFetch(BASE_URL + '/api/albums/' + id, {
        method: 'POST',
        body: JSON.stringify({ title, description }),
    });

    if (result.success) {
        location.reload();
    } else {
        Dialog.toast(result.error || 'Erreur lors de la modification.', 'error');
    }
}

async function albumDelete(id) {
    const ok = await Dialog.confirm("Supprimer cet album et toutes ses photos ? Cette action est irréversible.");
    if (!ok) return;

    const result = await apiFetch(BASE_URL + '/api/albums/' + id + '/delete', { method: 'POST' });
    if (result.success) {
        location.href = BASE_URL + '/albums';
    } else {
        Dialog.toast(result.error || 'Erreur lors de la suppression.', 'error');
    }
}

function albumOpenShareModal(id, currentScheduleId) {
    document.getElementById('album-share-id').value = id;
    document.getElementById('album-share-schedule').value = currentScheduleId || 0;
    openModal('album-share-modal');
}

async function albumSaveShare() {
    const id = document.getElementById('album-share-id').value;
    const scheduleId = parseInt(document.getElementById('album-share-schedule').value, 10) || 0;

    const result = await apiFetch(BASE_URL + '/api/albums/' + id + '/share', {
        method: 'POST',
        body: JSON.stringify({ schedule_id: scheduleId }),
    });

    if (result.success) {
        location.reload();
    } else {
        Dialog.toast(result.error || 'Erreur lors du partage.', 'error');
    }
}

async function albumDeletePhoto(id, btn) {
    const ok = await Dialog.confirm('Supprimer cette photo ?');
    if (!ok) return;

    const result = await apiFetch(BASE_URL + '/api/albums/photos/' + id + '/delete', { method: 'POST' });
    if (result.success) {
        btn.closest('.album-photo').remove();
    } else {
        Dialog.toast(result.error || 'Erreur lors de la suppression.', 'error');
    }
}

function albumViewPhoto(src) {
    document.getElementById('album-lightbox-img').src = src;
    openModal('album-lightbox');
}

// ---- Public share link (admin only) ----

function albumOpenPublicLinkModal(albumId, link) {
    document.getElementById('album-public-link-album-id').value = albumId;
    const inactive = document.getElementById('album-public-link-inactive');
    const active = document.getElementById('album-public-link-active');

    if (link) {
        inactive.style.display = 'none';
        active.style.display = 'block';
        document.getElementById('album-public-link-url').value = link.url || (BASE_URL + '/album/' + link.token);
        document.getElementById('album-public-link-allow-upload').checked = Number(link.allow_upload) === 1;
    } else {
        inactive.style.display = 'block';
        active.style.display = 'none';
        document.getElementById('album-public-link-allow-upload-new').checked = false;
    }
    openModal('album-public-link-modal');
}

async function albumGeneratePublicLink() {
    const albumId = document.getElementById('album-public-link-album-id').value;
    const allowUpload = document.getElementById('album-public-link-allow-upload-new').checked;

    const result = await apiFetch(BASE_URL + '/api/albums/' + albumId + '/public-link', {
        method: 'POST',
        body: JSON.stringify({ allow_upload: allowUpload }),
    });

    if (result.success) {
        albumOpenPublicLinkModal(albumId, result.link);
        Dialog.toast('Lien public généré.', 'success');
    } else {
        Dialog.toast(result.error || 'Erreur lors de la génération du lien.', 'error');
    }
}

async function albumToggleAllowUpload() {
    const albumId = document.getElementById('album-public-link-album-id').value;
    const allowUpload = document.getElementById('album-public-link-allow-upload').checked;

    const result = await apiFetch(BASE_URL + '/api/albums/' + albumId + '/public-link/update', {
        method: 'POST',
        body: JSON.stringify({ allow_upload: allowUpload }),
    });
    if (!result.success) {
        Dialog.toast(result.error || 'Erreur lors de la mise à jour.', 'error');
    }
}

async function albumRevokePublicLink() {
    const ok = await Dialog.confirm('Révoquer ce lien public ? Il ne fonctionnera plus.');
    if (!ok) return;

    const albumId = document.getElementById('album-public-link-album-id').value;
    const result = await apiFetch(BASE_URL + '/api/albums/' + albumId + '/public-link/revoke', { method: 'POST' });
    if (result.success) {
        albumOpenPublicLinkModal(albumId, null);
        Dialog.toast('Lien révoqué.', 'success');
    } else {
        Dialog.toast(result.error || 'Erreur lors de la révocation.', 'error');
    }
}

function albumCopyPublicLink() {
    const input = document.getElementById('album-public-link-url');
    input.select();
    navigator.clipboard?.writeText(input.value).then(() => Dialog.toast('Lien copié.', 'success'));
}
