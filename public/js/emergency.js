// ---- Fiches urgence ----

async function saveEmergencyCard(subjectType, subjectId, formId) {
    const val = id => document.getElementById(formId + id)?.value.trim() || '';
    const payload = {
        subject_type: subjectType,
        subject_id: subjectId,
        blood_type: val('-blood'),
        allergies: val('-allergies'),
        medications: val('-medications'),
        conditions: val('-conditions'),
        doctor_name: val('-doctor-name'),
        doctor_phone: val('-doctor-phone'),
        emergency_contact_name: val('-contact-name'),
        emergency_contact_phone: val('-contact-phone'),
        notes: val('-notes'),
    };
    const r = await apiFetch(BASE_URL + '/api/emergency', { method: 'POST', body: JSON.stringify(payload) });
    if (r.success) {
        Dialog.toast('Fiche enregistrée !');
        setTimeout(() => window.location.reload(), 600);
    } else {
        Dialog.toast(r.error || 'Erreur.', 'error');
    }
}

function showEmergencyQr(token) {
    const url = window.location.origin + BASE_URL + '/emergency/public/' + token;
    const container = document.getElementById('emergency-qr-container');
    container.innerHTML = '';
    const qr = qrcode(0, 'M');
    qr.addData(url);
    qr.make();
    container.innerHTML = qr.createSvgTag({ cellSize: 5, margin: 4 });
    document.getElementById('emergency-qr-link').textContent = url;
    openModal('emergency-qr-modal');
}
