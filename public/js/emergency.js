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

async function regenerateEmergencyLink(cardId) {
    const ok = await Dialog.confirm("Régénérer le lien ? L'ancien lien (QR code déjà imprimé, envoyé...) cessera immédiatement de fonctionner.");
    if (!ok) return;
    const r = await apiFetch(BASE_URL + '/api/emergency/' + cardId + '/regenerate-token', { method: 'POST', body: '{}' });
    if (r.success) {
        Dialog.toast('Nouveau lien généré.');
        setTimeout(() => window.location.reload(), 600);
    } else {
        Dialog.toast(r.error || 'Erreur.', 'error');
    }
}

async function viewEmergencyAccessLog(cardId) {
    const r = await apiFetch(BASE_URL + '/api/emergency/' + cardId + '/access-log');
    const body = document.getElementById('emergency-log-body');
    if (!r.success) {
        body.innerHTML = '<p>' + (r.error || 'Erreur.') + '</p>';
    } else if (!r.log.length) {
        body.innerHTML = '<p style="color:var(--text-muted)">Aucune consultation enregistrée pour l\'instant.</p>';
    } else {
        body.innerHTML = '<table class="table"><thead><tr><th>Date</th><th>Adresse IP</th></tr></thead><tbody>'
            + r.log.map(e => `<tr><td>${new Date(e.accessed_at.replace(' ', 'T') + 'Z').toLocaleString('fr-FR')}</td><td>${e.ip || '—'}</td></tr>`).join('')
            + '</tbody></table>';
    }
    openModal('emergency-log-modal');
}
