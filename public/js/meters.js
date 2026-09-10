// ============================================
// FamilyBoard - Compteurs & consommation
// ============================================

function onMeterTypeChange() {
    const select = document.getElementById('meter-type');
    const unit = select.options[select.selectedIndex]?.dataset.unit;
    document.getElementById('meter-unit').value = unit || '';
}

function openNewMeterModal() {
    document.getElementById('meter-modal-title').textContent = 'Nouveau compteur';
    document.getElementById('meter-id').value = '';
    document.getElementById('meter-name').value = '';
    document.getElementById('meter-type').value = 'electricite';
    document.getElementById('meter-provider').value = '';
    document.getElementById('meter-contract').value = '';
    onMeterTypeChange();
    openModal('meter-modal');
}

function openEditMeterModal(m) {
    document.getElementById('meter-modal-title').textContent = 'Modifier le compteur';
    document.getElementById('meter-id').value = m.id;
    document.getElementById('meter-name').value = m.name;
    document.getElementById('meter-type').value = m.meter_type;
    document.getElementById('meter-unit').value = m.unit;
    document.getElementById('meter-provider').value = m.provider || '';
    document.getElementById('meter-contract').value = m.contract_ref || '';
    openModal('meter-modal');
}

async function saveMeter() {
    const name = document.getElementById('meter-name').value.trim();
    if (!name) { Dialog.toast('Le nom est requis.', 'error'); return; }
    const payload = {
        name,
        meter_type: document.getElementById('meter-type').value,
        unit: document.getElementById('meter-unit').value,
        provider: document.getElementById('meter-provider').value,
        contract_ref: document.getElementById('meter-contract').value,
    };
    const id = document.getElementById('meter-id').value;
    const url = id ? `${BASE_URL}/api/meters/${id}` : `${BASE_URL}/api/meters`;
    const r = await apiFetch(url, { method: 'POST', body: JSON.stringify(payload) });
    if (r.success) {
        window.location.href = `${BASE_URL}/meters?id=${id || r.id}`;
    } else {
        Dialog.toast(r.error || 'Erreur.', 'error');
    }
}

async function deleteMeter(id) {
    const ok = await Dialog.confirm('Supprimer ce compteur et ses relevés ?');
    if (!ok) return;
    const r = await apiFetch(`${BASE_URL}/api/meters/${id}/delete`, { method: 'POST' });
    if (r.success) window.location.href = `${BASE_URL}/meters`;
}

async function addReading() {
    const date = document.getElementById('reading-date').value;
    const value = document.getElementById('reading-value').value;
    if (!date || value === '') { Dialog.toast('Date et index requis.', 'error'); return; }
    const payload = {
        reading_at: date,
        value,
        notes: document.getElementById('reading-notes').value,
    };
    const r = await apiFetch(`${BASE_URL}/api/meters/${METER_ID}/readings`, { method: 'POST', body: JSON.stringify(payload) });
    if (r.success) window.location.reload();
    else Dialog.toast(r.error || 'Erreur.', 'error');
}

async function deleteReading(id) {
    const ok = await Dialog.confirm('Supprimer ce relevé ?');
    if (!ok) return;
    const r = await apiFetch(`${BASE_URL}/api/meters/${METER_ID}/readings/${id}/delete`, { method: 'POST' });
    if (r.success) window.location.reload();
    else Dialog.toast(r.error || 'Erreur.', 'error');
}
