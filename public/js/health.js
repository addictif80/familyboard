// ============================================
// FamilyBoard - Santé
// ============================================

function switchHealthTab(tab) {
    document.querySelectorAll('.settings-tab-panel').forEach(p => p.classList.toggle('active', p.dataset.tab === tab));
    document.querySelectorAll('.settings-tab-btn').forEach(b => b.classList.toggle('active', b.dataset.tab === tab));
}

// ---- Calendrier vaccinal (France, calendrier simplifié grand public) ----
// Âges en mois (72 = 6 ans, 132 = 11 ans) — volontairement simplifié (pas les cas particuliers
// de prématurité, rattrapage, zones à risque...) : une aide au remplissage, pas un avis médical,
// d'où le rappel explicite dans l'interface de vérifier avec le médecin.
const VACCINE_SCHEDULE_FR = [
    { months: 2,   label: '2 mois — Hexavalent (1) + Pneumocoque (1)' },
    { months: 4,   label: '4 mois — Hexavalent (2) + Pneumocoque (2)' },
    { months: 5,   label: '5 mois — Méningocoque C (1)' },
    { months: 11,  label: '11 mois — Hexavalent (3) + Pneumocoque (3)' },
    { months: 12,  label: '12 mois — ROR (1) + Méningocoque C (2)' },
    { months: 17,  label: '16-18 mois — ROR (2)' },
    { months: 72,  label: '6 ans — Rappel DTP-Coqueluche' },
    { months: 132, label: '11-13 ans — Rappel DTP-Coqueluche + Méningocoque ACWY + HPV' },
    { months: 300, label: '25 ans — Rappel dTP' },
];

function updateVaccineScheduleVisibility() {
    const wrap = document.getElementById('vaccine-schedule-wrap');
    const select = document.getElementById('vaccine-schedule-select');
    const [subjectType, subjectId] = document.getElementById('entry-subject').value.split(':');
    const isVaccination = document.getElementById('entry-type').value === 'vaccination';
    const birthDate = subjectType === 'child' ? HEALTH_SUBJECT_BIRTHDATES[subjectId] : null;

    if (!isVaccination || !birthDate) {
        wrap.style.display = 'none';
        return;
    }
    wrap.style.display = '';
    select.innerHTML = '<option value="">— Choisir un âge pour pré-remplir le titre, la date et le rappel —</option>' +
        VACCINE_SCHEDULE_FR.map((v, i) => `<option value="${i}">${v.label}</option>`).join('');
}

function applyVaccineSuggestion() {
    const idx = document.getElementById('vaccine-schedule-select').value;
    if (idx === '') return;
    const [subjectType, subjectId] = document.getElementById('entry-subject').value.split(':');
    const birthDate = new Date(HEALTH_SUBJECT_BIRTHDATES[subjectId] + 'T00:00:00');
    const v = VACCINE_SCHEDULE_FR[parseInt(idx, 10)];
    const due = new Date(birthDate);
    due.setMonth(due.getMonth() + v.months);
    const dueStr = due.toISOString().slice(0, 10);

    document.getElementById('entry-title').value = v.label.replace(/^[^—]+—\s*/, '');
    document.getElementById('entry-date').value = dueStr;
    document.getElementById('entry-reminder').value = dueStr;
}

// ---- Carnet de santé ----

async function addHealthEntry() {
    const [subjectType, subjectId] = document.getElementById('entry-subject').value.split(':');
    const title = document.getElementById('entry-title').value.trim();
    if (!title) { Dialog.toast('Le titre est requis.', 'error'); return; }
    const payload = {
        subject_type: subjectType,
        subject_id: subjectId,
        entry_type: document.getElementById('entry-type').value,
        title,
        description: document.getElementById('entry-description').value,
        entry_date: document.getElementById('entry-date').value,
        reminder_date: document.getElementById('entry-reminder').value,
    };
    const r = await apiFetch(`${BASE_URL}/api/health/entries`, { method: 'POST', body: JSON.stringify(payload) });
    if (r.success) window.location.reload();
    else Dialog.toast(r.error || 'Erreur.', 'error');
}

async function deleteHealthEntry(id) {
    const ok = await Dialog.confirm('Supprimer cette entrée ?');
    if (!ok) return;
    const r = await apiFetch(`${BASE_URL}/api/health/entries/${id}/delete`, { method: 'POST' });
    if (r.success) window.location.reload();
    else Dialog.toast(r.error || 'Erreur.', 'error');
}

// ---- Croissance ----

async function addGrowthEntry() {
    const childId = document.getElementById('growth-child-id').value;
    const date = document.getElementById('growth-date').value;
    if (!childId || !date) { Dialog.toast('Date requise.', 'error'); return; }
    const payload = {
        child_id: childId,
        measured_at: date,
        height_cm: document.getElementById('growth-height').value,
        weight_kg: document.getElementById('growth-weight').value,
    };
    const r = await apiFetch(`${BASE_URL}/api/health/growth`, { method: 'POST', body: JSON.stringify(payload) });
    if (r.success) window.location.reload();
    else Dialog.toast(r.error || 'Erreur.', 'error');
}

async function deleteGrowthEntry(id) {
    const ok = await Dialog.confirm('Supprimer cette mesure ?');
    if (!ok) return;
    const r = await apiFetch(`${BASE_URL}/api/health/growth/${id}/delete`, { method: 'POST' });
    if (r.success) window.location.reload();
    else Dialog.toast(r.error || 'Erreur.', 'error');
}

// ---- Médecins ----

function openNewDoctorModal() {
    document.getElementById('doctor-modal-title').textContent = 'Nouveau médecin';
    document.getElementById('doctor-id').value = '';
    document.getElementById('doctor-specialty').value = '';
    document.getElementById('doctor-name').value = '';
    document.getElementById('doctor-phone').value = '';
    document.getElementById('doctor-address').value = '';
    document.getElementById('doctor-next-appointment').value = '';
    document.getElementById('doctor-renewal').value = '';
    document.getElementById('doctor-notes').value = '';
    openModal('doctor-modal');
}

function openEditDoctorModal(doc) {
    document.getElementById('doctor-modal-title').textContent = 'Modifier le médecin';
    document.getElementById('doctor-id').value = doc.id;
    document.getElementById('doctor-subject').value = `${doc.subject_type}:${doc.subject_id}`;
    document.getElementById('doctor-specialty').value = doc.specialty || '';
    document.getElementById('doctor-name').value = doc.name;
    document.getElementById('doctor-phone').value = doc.phone || '';
    document.getElementById('doctor-address').value = doc.address || '';
    document.getElementById('doctor-next-appointment').value = doc.next_appointment || '';
    document.getElementById('doctor-renewal').value = doc.prescription_renewal_date || '';
    document.getElementById('doctor-notes').value = doc.notes || '';
    openModal('doctor-modal');
}

async function saveDoctor() {
    const [subjectType, subjectId] = document.getElementById('doctor-subject').value.split(':');
    const name = document.getElementById('doctor-name').value.trim();
    if (!name) { Dialog.toast('Le nom est requis.', 'error'); return; }
    const payload = {
        subject_type: subjectType,
        subject_id: subjectId,
        name,
        specialty: document.getElementById('doctor-specialty').value,
        phone: document.getElementById('doctor-phone').value,
        address: document.getElementById('doctor-address').value,
        next_appointment: document.getElementById('doctor-next-appointment').value,
        prescription_renewal_date: document.getElementById('doctor-renewal').value,
        notes: document.getElementById('doctor-notes').value,
    };
    const id = document.getElementById('doctor-id').value;
    const url = id ? `${BASE_URL}/api/health/doctors/${id}` : `${BASE_URL}/api/health/doctors`;
    const r = await apiFetch(url, { method: 'POST', body: JSON.stringify(payload) });
    if (r.success) window.location.reload();
    else Dialog.toast(r.error || 'Erreur.', 'error');
}

async function deleteDoctor(id) {
    const ok = await Dialog.confirm('Supprimer ce médecin ?');
    if (!ok) return;
    const r = await apiFetch(`${BASE_URL}/api/health/doctors/${id}/delete`, { method: 'POST' });
    if (r.success) window.location.reload();
    else Dialog.toast(r.error || 'Erreur.', 'error');
}
