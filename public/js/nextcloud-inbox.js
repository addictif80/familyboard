function ncinboxToggleFields(id) {
    const item = document.getElementById('ncinbox-item-' + id);
    const module = document.getElementById('ncinbox-module-' + id).value;
    item.querySelectorAll('.ncinbox-field-title').forEach(el => el.style.display = module !== 'disputes' ? '' : 'none');
    item.querySelectorAll('.ncinbox-field-school').forEach(el => el.style.display = module === 'school' ? '' : 'none');
    item.querySelectorAll('.ncinbox-field-disputes').forEach(el => el.style.display = module === 'disputes' ? '' : 'none');
}

async function ncinboxAttach(id) {
    const btn = document.querySelector('#ncinbox-item-' + id + ' .ncinbox-attach-btn');
    // Un double-clic avant la réponse créerait deux enregistrements pour le même fichier.
    if (btn) { if (btn.disabled) return; btn.disabled = true; }
    const module = document.getElementById('ncinbox-module-' + id).value;
    const data = { id, module };
    const titleInput = document.getElementById('ncinbox-title-' + id);
    if (titleInput) data.title = titleInput.value.trim();
    if (module === 'school') {
        const sel = document.getElementById('ncinbox-student-' + id);
        if (!sel || !sel.value) { Dialog.toast('Aucun élève disponible.', 'error'); return; }
        data.student_id = parseInt(sel.value, 10);
    }
    if (module === 'disputes') {
        const sel = document.getElementById('ncinbox-dispute-' + id);
        if (!sel || !sel.value) { Dialog.toast('Aucun litige disponible.', 'error'); return; }
        data.dispute_id = parseInt(sel.value, 10);
    }

    const r = await apiFetch(BASE_URL + '/api/nextcloud-inbox/attach', { method: 'POST', body: JSON.stringify(data) });
    if (r.success) {
        document.getElementById('ncinbox-item-' + id).remove();
        Dialog.toast('Fichier rattaché.', 'success');
    } else {
        if (btn) btn.disabled = false;
        Dialog.toast(r.error || 'Erreur lors du rattachement.', 'error');
    }
}

async function ncinboxDismiss(id) {
    const ok = await Dialog.confirm('Ignorer ce fichier ? Il restera disponible dans Nextcloud, mais ne sera plus proposé ici.');
    if (!ok) return;
    const r = await apiFetch(BASE_URL + '/api/nextcloud-inbox/' + id + '/dismiss', { method: 'POST' });
    if (r.success) document.getElementById('ncinbox-item-' + id).remove();
    else Dialog.toast(r.error || 'Erreur.', 'error');
}
