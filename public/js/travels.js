// ============================================
// FamilyBoard - Voyages & réservations
// ============================================

function openNewTravelModal() {
    document.getElementById('travel-modal-title').textContent = 'Nouveau voyage';
    document.getElementById('travel-id').value = '';
    document.getElementById('travel-title').value = '';
    document.getElementById('travel-destination').value = '';
    document.getElementById('travel-start').value = '';
    document.getElementById('travel-end').value = '';
    document.getElementById('travel-budget').value = '';
    document.getElementById('travel-notes').value = '';
    openModal('travel-modal');
}

function openEditTravelModal(t) {
    document.getElementById('travel-modal-title').textContent = 'Modifier le voyage';
    document.getElementById('travel-id').value = t.id;
    document.getElementById('travel-title').value = t.title;
    document.getElementById('travel-destination').value = t.destination || '';
    document.getElementById('travel-start').value = t.start_date || '';
    document.getElementById('travel-end').value = t.end_date || '';
    document.getElementById('travel-budget').value = t.budget_cents != null ? (t.budget_cents / 100) : '';
    document.getElementById('travel-notes').value = t.notes || '';
    openModal('travel-modal');
}

async function saveTravel() {
    const title = document.getElementById('travel-title').value.trim();
    if (!title) { Dialog.toast('Le titre est requis.', 'error'); return; }
    const payload = {
        title,
        destination: document.getElementById('travel-destination').value,
        start_date: document.getElementById('travel-start').value,
        end_date: document.getElementById('travel-end').value,
        budget: document.getElementById('travel-budget').value,
        notes: document.getElementById('travel-notes').value,
    };
    const id = document.getElementById('travel-id').value;
    const url = id ? `${BASE_URL}/api/travels/${id}` : `${BASE_URL}/api/travels`;
    const r = await apiFetch(url, { method: 'POST', body: JSON.stringify(payload) });
    if (r.success) {
        window.location.href = `${BASE_URL}/travels?id=${id || r.id}`;
    } else {
        Dialog.toast(r.error || 'Erreur.', 'error');
    }
}

async function deleteTravel(id) {
    const ok = await Dialog.confirm('Supprimer ce voyage et ses réservations ?');
    if (!ok) return;
    const r = await apiFetch(`${BASE_URL}/api/travels/${id}/delete`, { method: 'POST' });
    if (r.success) window.location.href = `${BASE_URL}/travels`;
}

async function addReservation() {
    const title = document.getElementById('res-title').value.trim();
    if (!title) { Dialog.toast('Titre requis.', 'error'); return; }
    const payload = {
        title,
        type: document.getElementById('res-type').value,
        reservation_date: document.getElementById('res-date').value,
        confirmation_number: document.getElementById('res-confirmation').value,
        cost: document.getElementById('res-cost').value,
        notes: document.getElementById('res-notes').value,
    };
    const r = await apiFetch(`${BASE_URL}/api/travels/${TRAVEL_ID}/reservations`, { method: 'POST', body: JSON.stringify(payload) });
    if (r.success) window.location.reload();
    else Dialog.toast(r.error || 'Erreur.', 'error');
}

async function deleteReservation(id) {
    const ok = await Dialog.confirm('Supprimer cette réservation ?');
    if (!ok) return;
    const r = await apiFetch(`${BASE_URL}/api/travels/${TRAVEL_ID}/reservations/${id}/delete`, { method: 'POST' });
    if (r.success) window.location.reload();
    else Dialog.toast(r.error || 'Erreur.', 'error');
}
