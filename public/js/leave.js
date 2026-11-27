// ============================================
// FamilyBoard - Congés familiaux
// ============================================

function openNewLeaveModal() {
    document.getElementById('leave-start').value = '';
    document.getElementById('leave-end').value = '';
    document.getElementById('leave-title').value = '';
    document.getElementById('leave-notes').value = '';
    const warning = document.getElementById('leave-overlap-warning');
    warning.style.display = 'none';
    warning.textContent = '';
    openModal('leave-modal');
}

async function saveLeave() {
    const start = document.getElementById('leave-start').value;
    const end = document.getElementById('leave-end').value;
    if (!start || !end) { Dialog.toast('Dates de début et de fin requises.', 'error'); return; }
    if (end < start) { Dialog.toast('La date de fin doit être après la date de début.', 'error'); return; }
    const payload = {
        user_id: document.getElementById('leave-user').value,
        leave_type: document.getElementById('leave-type').value,
        title: document.getElementById('leave-title').value.trim(),
        start_date: start,
        end_date: end,
        notes: document.getElementById('leave-notes').value,
    };
    const r = await apiFetch(`${BASE_URL}/api/leave`, { method: 'POST', body: JSON.stringify(payload) });
    if (r.success) {
        if (r.overlaps && r.overlaps.length) {
            Dialog.toast(`Congé enregistré. Attention, chevauchement avec : ${r.overlaps.join(', ')}.`, 'error');
        } else {
            Dialog.toast('Congé enregistré.', 'success');
        }
        window.location.reload();
    } else {
        Dialog.toast(r.error || 'Erreur.', 'error');
    }
}

async function deleteLeave(id) {
    const ok = await Dialog.confirm('Supprimer ce congé ?');
    if (!ok) return;
    const r = await apiFetch(`${BASE_URL}/api/leave/${id}/delete`, { method: 'POST' });
    if (r.success) window.location.reload();
    else Dialog.toast(r.error || 'Erreur.', 'error');
}
