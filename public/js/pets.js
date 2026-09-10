// ============================================
// FamilyBoard - Animaux de compagnie
// ============================================

function openNewPetModal() {
    document.getElementById('pet-modal-title').textContent = 'Nouvel animal';
    document.getElementById('pet-id').value = '';
    document.getElementById('pet-name').value = '';
    document.getElementById('pet-species').value = '';
    document.getElementById('pet-breed').value = '';
    document.getElementById('pet-birthdate').value = '';
    document.getElementById('pet-vet-name').value = '';
    document.getElementById('pet-vet-phone').value = '';
    document.getElementById('pet-notes').value = '';
    openModal('pet-modal');
}

function openEditPetModal(p) {
    document.getElementById('pet-modal-title').textContent = "Modifier l'animal";
    document.getElementById('pet-id').value = p.id;
    document.getElementById('pet-name').value = p.name;
    document.getElementById('pet-species').value = p.species || '';
    document.getElementById('pet-breed').value = p.breed || '';
    document.getElementById('pet-birthdate').value = p.birth_date || '';
    document.getElementById('pet-vet-name').value = p.vet_name || '';
    document.getElementById('pet-vet-phone').value = p.vet_phone || '';
    document.getElementById('pet-notes').value = p.notes || '';
    openModal('pet-modal');
}

async function savePet() {
    const name = document.getElementById('pet-name').value.trim();
    if (!name) { Dialog.toast('Le nom est requis.', 'error'); return; }
    const payload = {
        name,
        species: document.getElementById('pet-species').value,
        breed: document.getElementById('pet-breed').value,
        birth_date: document.getElementById('pet-birthdate').value,
        vet_name: document.getElementById('pet-vet-name').value,
        vet_phone: document.getElementById('pet-vet-phone').value,
        notes: document.getElementById('pet-notes').value,
    };
    const id = document.getElementById('pet-id').value;
    const url = id ? `${BASE_URL}/api/pets/${id}` : `${BASE_URL}/api/pets`;
    const r = await apiFetch(url, { method: 'POST', body: JSON.stringify(payload) });
    if (r.success) {
        window.location.href = `${BASE_URL}/pets?id=${id || r.id}`;
    } else {
        Dialog.toast(r.error || 'Erreur.', 'error');
    }
}

async function deletePet(id) {
    const ok = await Dialog.confirm('Supprimer cet animal et son carnet de soins ?');
    if (!ok) return;
    const r = await apiFetch(`${BASE_URL}/api/pets/${id}/delete`, { method: 'POST' });
    if (r.success) window.location.href = `${BASE_URL}/pets`;
}

async function addCareEntry() {
    const title = document.getElementById('care-title').value.trim();
    const date = document.getElementById('care-date').value;
    if (!title || !date) { Dialog.toast('Titre et date requis.', 'error'); return; }
    const payload = {
        entry_type: document.getElementById('care-type').value,
        title,
        done_at: date,
        reminder_date: document.getElementById('care-reminder').value,
    };
    const r = await apiFetch(`${BASE_URL}/api/pets/${PET_ID}/care`, { method: 'POST', body: JSON.stringify(payload) });
    if (r.success) window.location.reload();
    else Dialog.toast(r.error || 'Erreur.', 'error');
}

async function deleteCareEntry(id) {
    const ok = await Dialog.confirm('Supprimer ce soin ?');
    if (!ok) return;
    const r = await apiFetch(`${BASE_URL}/api/pets/${PET_ID}/care/${id}/delete`, { method: 'POST' });
    if (r.success) window.location.reload();
    else Dialog.toast(r.error || 'Erreur.', 'error');
}
