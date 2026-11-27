// ============================================
// FamilyBoard - Suivi nounou
// ============================================

// ---- Entrées d'heures ----

function openNewEntryModal() {
    document.getElementById('entry-modal-title').textContent = 'Nouvelle entrée';
    document.getElementById('entry-id').value = '';
    document.getElementById('entry-date').value = new Date().toISOString().slice(0, 10);
    document.getElementById('entry-hours').value = '';
    document.getElementById('entry-child').value = '';
    document.getElementById('entry-child-new-name').value = '';
    document.getElementById('entry-child-new-name').style.display = 'none';
    document.getElementById('entry-nanny-name').value = '';
    document.getElementById('entry-notes').value = '';
    openModal('entry-modal');
}

function openEditEntryModal(entry) {
    document.getElementById('entry-modal-title').textContent = "Modifier l'entrée";
    document.getElementById('entry-id').value = entry.id;
    document.getElementById('entry-date').value = entry.entry_date;
    document.getElementById('entry-hours').value = entry.hours;
    document.getElementById('entry-child').value = entry.family_child_id || '';
    document.getElementById('entry-child-new-name').value = '';
    document.getElementById('entry-child-new-name').style.display = 'none';
    document.getElementById('entry-nanny-name').value = entry.nanny_name || '';
    document.getElementById('entry-notes').value = entry.notes || '';
    openModal('entry-modal');
}

async function saveEntry() {
    const date = document.getElementById('entry-date').value;
    const hours = parseFloat(document.getElementById('entry-hours').value);
    if (!date || !hours || hours <= 0 || hours > 24) {
        Dialog.toast('Date et nombre d\'heures (entre 0 et 24) requis.', 'error');
        return;
    }
    const childSelect = document.getElementById('entry-child').value;
    const payload = {
        entry_date: date,
        hours,
        child_id: childSelect === '__new__' ? '' : childSelect,
        new_child_name: childSelect === '__new__' ? document.getElementById('entry-child-new-name').value.trim() : '',
        nanny_name: document.getElementById('entry-nanny-name').value,
        notes: document.getElementById('entry-notes').value,
    };
    const id = document.getElementById('entry-id').value;
    const url = id ? `${BASE_URL}/api/nanny/entries/${id}` : `${BASE_URL}/api/nanny/entries`;
    const r = await apiFetch(url, { method: 'POST', body: JSON.stringify(payload) });
    if (r.success) {
        window.location.reload();
    } else {
        Dialog.toast(r.error || 'Erreur.', 'error');
    }
}

async function deleteEntry(id) {
    const ok = await Dialog.confirm('Supprimer cette entrée ?');
    if (!ok) return;
    const r = await apiFetch(`${BASE_URL}/api/nanny/entries/${id}/delete`, { method: 'POST' });
    if (r.success) window.location.reload();
    else Dialog.toast(r.error || 'Erreur.', 'error');
}
