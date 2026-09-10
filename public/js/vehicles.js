// ============================================
// FamilyBoard - Véhicules
// ============================================

function openNewVehicleModal() {
    document.getElementById('vehicle-modal-title').textContent = 'Nouveau véhicule';
    document.getElementById('vehicle-id').value = '';
    document.getElementById('vehicle-name').value = '';
    document.getElementById('vehicle-plate').value = '';
    document.getElementById('vehicle-brand').value = '';
    document.getElementById('vehicle-model').value = '';
    document.getElementById('vehicle-mileage').value = '';
    document.getElementById('vehicle-insurance-company').value = '';
    document.getElementById('vehicle-insurance-expiry').value = '';
    document.getElementById('vehicle-ct-expiry').value = '';
    document.getElementById('vehicle-notes').value = '';
    openModal('vehicle-modal');
}

function openEditVehicleModal(v) {
    document.getElementById('vehicle-modal-title').textContent = 'Modifier le véhicule';
    document.getElementById('vehicle-id').value = v.id;
    document.getElementById('vehicle-name').value = v.name;
    document.getElementById('vehicle-plate').value = v.plate || '';
    document.getElementById('vehicle-brand').value = v.brand || '';
    document.getElementById('vehicle-model').value = v.model || '';
    document.getElementById('vehicle-mileage').value = v.mileage || '';
    document.getElementById('vehicle-insurance-company').value = v.insurance_company || '';
    document.getElementById('vehicle-insurance-expiry').value = v.insurance_expiry || '';
    document.getElementById('vehicle-ct-expiry').value = v.technical_control_expiry || '';
    document.getElementById('vehicle-notes').value = v.notes || '';
    openModal('vehicle-modal');
}

async function saveVehicle() {
    const name = document.getElementById('vehicle-name').value.trim();
    if (!name) { Dialog.toast('Le nom est requis.', 'error'); return; }
    const payload = {
        name,
        plate: document.getElementById('vehicle-plate').value,
        brand: document.getElementById('vehicle-brand').value,
        model: document.getElementById('vehicle-model').value,
        mileage: document.getElementById('vehicle-mileage').value,
        insurance_company: document.getElementById('vehicle-insurance-company').value,
        insurance_expiry: document.getElementById('vehicle-insurance-expiry').value,
        technical_control_expiry: document.getElementById('vehicle-ct-expiry').value,
        notes: document.getElementById('vehicle-notes').value,
    };
    const id = document.getElementById('vehicle-id').value;
    const url = id ? `${BASE_URL}/api/vehicles/${id}` : `${BASE_URL}/api/vehicles`;
    const r = await apiFetch(url, { method: 'POST', body: JSON.stringify(payload) });
    if (r.success) {
        window.location.href = `${BASE_URL}/vehicles?id=${id || r.id}`;
    } else {
        Dialog.toast(r.error || 'Erreur.', 'error');
    }
}

async function deleteVehicle(id) {
    const ok = await Dialog.confirm('Supprimer ce véhicule et son historique d\'entretien ?');
    if (!ok) return;
    const r = await apiFetch(`${BASE_URL}/api/vehicles/${id}/delete`, { method: 'POST' });
    if (r.success) window.location.href = `${BASE_URL}/vehicles`;
}

async function addMaintenance() {
    const title = document.getElementById('maint-title').value.trim();
    const date = document.getElementById('maint-date').value;
    if (!title || !date) { Dialog.toast('Titre et date requis.', 'error'); return; }
    const payload = {
        title,
        done_at: date,
        mileage: document.getElementById('maint-mileage').value,
        cost: document.getElementById('maint-cost').value,
        notes: document.getElementById('maint-notes').value,
    };
    const r = await apiFetch(`${BASE_URL}/api/vehicles/${VEHICLE_ID}/maintenance`, { method: 'POST', body: JSON.stringify(payload) });
    if (r.success) window.location.reload();
    else Dialog.toast(r.error || 'Erreur.', 'error');
}

async function deleteMaintenance(id) {
    const ok = await Dialog.confirm('Supprimer cet entretien ?');
    if (!ok) return;
    const r = await apiFetch(`${BASE_URL}/api/vehicles/${VEHICLE_ID}/maintenance/${id}/delete`, { method: 'POST' });
    if (r.success) window.location.reload();
    else Dialog.toast(r.error || 'Erreur.', 'error');
}
