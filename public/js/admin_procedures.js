// ============================================
// FamilyBoard - Démarches administratives
// ============================================

function openNewProcedureModal() {
    document.getElementById('proc-title').value = '';
    document.getElementById('proc-deadline').value = '';
    document.getElementById('proc-notes').value = '';
    openModal('procedure-modal');
}

async function saveProcedure() {
    const [subjectType, subjectId] = document.getElementById('proc-subject').value.split(':');
    const title = document.getElementById('proc-title').value.trim();
    const deadline = document.getElementById('proc-deadline').value;
    if (!title || !deadline) { Dialog.toast('Titre et échéance requis.', 'error'); return; }
    const payload = {
        subject_type: subjectType,
        subject_id: subjectId,
        procedure_type: document.getElementById('proc-type').value,
        title,
        deadline_date: deadline,
        notes: document.getElementById('proc-notes').value,
    };
    const r = await apiFetch(`${BASE_URL}/api/admin-procedures`, { method: 'POST', body: JSON.stringify(payload) });
    if (r.success) window.location.reload();
    else Dialog.toast(r.error || 'Erreur.', 'error');
}

async function toggleProcedureDone(id, done) {
    await apiFetch(`${BASE_URL}/api/admin-procedures/${id}/done`, { method: 'POST', body: JSON.stringify({ done }) });
    window.location.reload();
}

async function deleteProcedure(id) {
    const ok = await Dialog.confirm('Supprimer cette démarche ?');
    if (!ok) return;
    const r = await apiFetch(`${BASE_URL}/api/admin-procedures/${id}/delete`, { method: 'POST' });
    if (r.success) window.location.reload();
    else Dialog.toast(r.error || 'Erreur.', 'error');
}
