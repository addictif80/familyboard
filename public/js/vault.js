// ============================================
// FamilyBoard - Coffre-fort numérique
// ============================================

function openNewEntryModal() {
    document.getElementById('entry-modal-title').textContent = 'Nouvelle entrée';
    document.getElementById('entry-id').value = '';
    document.getElementById('entry-title').value = '';
    document.getElementById('entry-category').value = 'autre';
    document.getElementById('entry-content').value = '';
    document.getElementById('entry-file').value = '';
    document.getElementById('entry-current-file').innerHTML = '';
    openModal('entry-modal');
}

function openEditEntryModal(entry) {
    document.getElementById('entry-modal-title').textContent = 'Modifier l\'entrée';
    document.getElementById('entry-id').value = entry.id;
    document.getElementById('entry-title').value = entry.title;
    document.getElementById('entry-category').value = entry.category;
    document.getElementById('entry-content').value = entry.content || '';
    document.getElementById('entry-file').value = '';
    document.getElementById('entry-current-file').innerHTML = entry.file_path
        ? `📎 <a href="${BASE_URL}/api/vault/entries/${entry.id}/file" target="_blank">${entry.file_original}</a>`
        : '';
    openModal('entry-modal');
}

async function saveEntry() {
    const title = document.getElementById('entry-title').value.trim();
    if (!title) { Dialog.toast('Le titre est requis.', 'error'); return; }

    const fd = new FormData();
    fd.append('title', title);
    fd.append('category', document.getElementById('entry-category').value);
    fd.append('content', document.getElementById('entry-content').value);
    const fileInput = document.getElementById('entry-file');
    if (fileInput.files[0]) fd.append('file', fileInput.files[0]);

    const id = document.getElementById('entry-id').value;
    const url = id ? `${BASE_URL}/api/vault/entries/${id}` : `${BASE_URL}/api/vault/entries`;
    const res = await fetch(url, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
    const r = await res.json();
    if (r.success) window.location.reload();
    else Dialog.toast(r.error || 'Erreur.', 'error');
}

async function deleteEntry(id) {
    const ok = await Dialog.confirm('Supprimer cette entrée ?');
    if (!ok) return;
    const r = await apiFetch(`${BASE_URL}/api/vault/entries/${id}/delete`, { method: 'POST' });
    if (r.success) window.location.reload();
    else Dialog.toast(r.error || 'Erreur.', 'error');
}

function openNewTrusteeModal() {
    document.getElementById('trustee-name').value = '';
    document.getElementById('trustee-email').value = '';
    document.getElementById('trustee-relationship').value = '';
    document.getElementById('trustee-notes').value = '';
    openModal('trustee-modal');
}

async function saveTrustee() {
    const name = document.getElementById('trustee-name').value.trim();
    const email = document.getElementById('trustee-email').value.trim();
    if (!name || !email) { Dialog.toast('Nom et e-mail requis.', 'error'); return; }
    const payload = {
        name,
        email,
        relationship: document.getElementById('trustee-relationship').value,
        notes: document.getElementById('trustee-notes').value,
    };
    const r = await apiFetch(`${BASE_URL}/api/vault/trustees`, { method: 'POST', body: JSON.stringify(payload) });
    if (r.success) window.location.reload();
    else Dialog.toast(r.error || 'Erreur.', 'error');
}

async function deleteTrustee(id) {
    const ok = await Dialog.confirm('Supprimer cette personne de confiance ? Son lien d\'accès sera invalidé.');
    if (!ok) return;
    const r = await apiFetch(`${BASE_URL}/api/vault/trustees/${id}/delete`, { method: 'POST' });
    if (r.success) window.location.reload();
    else Dialog.toast(r.error || 'Erreur.', 'error');
}

async function decideTrustee(id, approve) {
    const ok = await Dialog.confirm(approve ? 'Approuver cet accès au coffre-fort ?' : 'Refuser cette demande d\'accès ?');
    if (!ok) return;
    const r = await apiFetch(`${BASE_URL}/api/vault/trustees/${id}/decide`, { method: 'POST', body: JSON.stringify({ approve }) });
    if (r.success) window.location.reload();
    else Dialog.toast(r.error || 'Erreur.', 'error');
}

async function revokeTrustee(id) {
    const ok = await Dialog.confirm('Révoquer cet accès au coffre-fort ?');
    if (!ok) return;
    const r = await apiFetch(`${BASE_URL}/api/vault/trustees/${id}/revoke`, { method: 'POST' });
    if (r.success) window.location.reload();
    else Dialog.toast(r.error || 'Erreur.', 'error');
}

function copyTrusteeLink(token) {
    const url = `${window.location.origin}${BASE_URL}/vault-access/${token}`;
    navigator.clipboard.writeText(url).then(() => Dialog.toast('Lien copié.', 'success'));
}
