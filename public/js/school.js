// ============================================
// FamilyBoard - Suivi scolaire
// ============================================

function switchSchoolTab(tab) {
    document.querySelectorAll('.settings-tab-panel').forEach(p => p.classList.toggle('active', p.dataset.tab === tab));
    document.querySelectorAll('.settings-tab-btn').forEach(b => b.classList.toggle('active', b.dataset.tab === tab));
}

// ---- Élève ----

function onStudentChildSelect() {
    const select = document.getElementById('student-child-select');
    const isNew = select.value === '__new__';
    document.getElementById('student-name-group').style.display = isNew ? '' : 'none';
    if (!isNew) {
        const color = select.options[select.selectedIndex]?.dataset.color;
        if (color) document.getElementById('student-color').value = color;
    }
}

function openNewStudentModal() {
    document.getElementById('student-modal-title').textContent = 'Nouvel élève';
    document.getElementById('student-id').value = '';
    document.getElementById('student-child-select-group').style.display = '';
    document.getElementById('student-child-select').value = '__new__';
    document.getElementById('student-name').value = '';
    document.getElementById('student-school').value = '';
    document.getElementById('student-class').value = '';
    document.getElementById('student-color').value = '#4A90D9';
    onStudentChildSelect();
    openModal('student-modal');
}

function openEditStudentModal() {
    if (!SELECTED_STUDENT) return;
    document.getElementById('student-modal-title').textContent = "Modifier l'élève";
    document.getElementById('student-id').value = SELECTED_STUDENT.id;
    document.getElementById('student-child-select-group').style.display = 'none';
    document.getElementById('student-name-group').style.display = '';
    document.getElementById('student-name').value = SELECTED_STUDENT.name;
    document.getElementById('student-school').value = SELECTED_STUDENT.school_name || '';
    document.getElementById('student-class').value = SELECTED_STUDENT.class_name || '';
    document.getElementById('student-color').value = SELECTED_STUDENT.color || '#4A90D9';
    openModal('student-modal');
}

async function saveStudent() {
    const id = document.getElementById('student-id').value;
    const payload = {
        school_name: document.getElementById('student-school').value,
        class_name: document.getElementById('student-class').value,
        color: document.getElementById('student-color').value,
    };
    if (id) {
        // Modification : le nom reste un champ libre propre à la fiche élève.
        const name = document.getElementById('student-name').value.trim();
        if (!name) { Dialog.toast('Le nom est requis.', 'error'); return; }
        payload.name = name;
    } else {
        // Création : identifié via le registre familial (enfant existant, ou nouveau nom).
        const childSelect = document.getElementById('student-child-select').value;
        if (childSelect === '__new__') {
            const name = document.getElementById('student-name').value.trim();
            if (!name) { Dialog.toast('Le nom est requis.', 'error'); return; }
            payload.new_child_name = name;
        } else {
            payload.family_child_id = childSelect;
        }
    }
    const url = id ? `${BASE_URL}/api/school/students/${id}` : `${BASE_URL}/api/school/students`;
    const r = await apiFetch(url, { method: 'POST', body: JSON.stringify(payload) });
    if (r.success) {
        window.location.href = `${BASE_URL}/school?id=${id || r.id}`;
    } else {
        Dialog.toast(r.error || 'Erreur.', 'error');
    }
}

async function deleteStudent() {
    const ok = await Dialog.confirm('Supprimer cet élève et toutes ses données (notes, absences, documents...) ? Cette action est irréversible.');
    if (!ok) return;
    const r = await apiFetch(`${BASE_URL}/api/school/students/${STUDENT_ID}/delete`, { method: 'POST' });
    if (r.success) window.location.href = `${BASE_URL}/school`;
}

// ---- Liens ----

function openLinksModal() {
    openModal('links-modal');
}

function toggleCoparentSelect() {
    const checked = document.getElementById('link-is-coparent').checked;
    document.getElementById('link-coparent').style.display = checked ? '' : 'none';
    if (!checked) document.getElementById('link-coparent').value = '';
}

async function saveLinks() {
    const payload = {
        linked_user_id: document.getElementById('link-user').value,
        is_coparent: document.getElementById('link-is-coparent').checked,
        linked_coparent_id: document.getElementById('link-coparent').value,
        linked_task_list_id: document.getElementById('link-tasklist').value,
    };
    const r = await apiFetch(`${BASE_URL}/api/school/students/${STUDENT_ID}/links`, { method: 'POST', body: JSON.stringify(payload) });
    if (!r.success) { Dialog.toast(r.error || 'Erreur.', 'error'); return; }

    const documentIds = Array.from(document.querySelectorAll('.link-doc-cb:checked')).map(cb => cb.value);
    const r2 = await apiFetch(`${BASE_URL}/api/school/students/${STUDENT_ID}/documents/link`, { method: 'POST', body: JSON.stringify({ document_ids: documentIds }) });
    if (r2.success) window.location.reload();
    else Dialog.toast(r2.error || 'Erreur.', 'error');
}

// ---- Matières & profs ----

async function addSubject() {
    const name = document.getElementById('subject-name').value.trim();
    if (!name) { Dialog.toast('Le nom de la matière est requis.', 'error'); return; }
    const payload = {
        name,
        teacher_name: document.getElementById('subject-teacher').value,
        color: document.getElementById('subject-color').value,
    };
    const r = await apiFetch(`${BASE_URL}/api/school/students/${STUDENT_ID}/subjects`, { method: 'POST', body: JSON.stringify(payload) });
    if (r.success) window.location.reload();
    else Dialog.toast(r.error || 'Erreur.', 'error');
}

async function deleteSubject(id) {
    const ok = await Dialog.confirm('Supprimer cette matière ? Les créneaux et notes associés seront aussi supprimés.');
    if (!ok) return;
    const r = await apiFetch(`${BASE_URL}/api/school/students/${STUDENT_ID}/subjects/${id}/delete`, { method: 'POST' });
    if (r.success) window.location.reload();
}

// ---- Emploi du temps ----

async function addTimetableSlot() {
    const payload = {
        day_of_week: document.getElementById('slot-day').value,
        subject_id: document.getElementById('slot-subject').value,
        start_time: document.getElementById('slot-start').value,
        end_time: document.getElementById('slot-end').value,
        room: document.getElementById('slot-room').value,
    };
    if (!payload.start_time || !payload.end_time) { Dialog.toast('Heure de début et de fin requises.', 'error'); return; }
    const r = await apiFetch(`${BASE_URL}/api/school/students/${STUDENT_ID}/timetable`, { method: 'POST', body: JSON.stringify(payload) });
    if (r.success) window.location.reload();
    else Dialog.toast(r.error || 'Erreur.', 'error');
}

async function deleteTimetableSlot(id) {
    const ok = await Dialog.confirm('Supprimer ce créneau ?');
    if (!ok) return;
    const r = await apiFetch(`${BASE_URL}/api/school/students/${STUDENT_ID}/timetable/${id}/delete`, { method: 'POST' });
    if (r.success) window.location.reload();
}

// ---- Notes ----

async function addGrade() {
    const payload = {
        subject_id: document.getElementById('grade-subject').value,
        grade_value: document.getElementById('grade-value').value,
        grade_max: document.getElementById('grade-max').value,
        grade_date: document.getElementById('grade-date').value,
        title: document.getElementById('grade-title').value,
        comment: document.getElementById('grade-comment').value,
    };
    if (payload.grade_value === '') { Dialog.toast('La note est requise.', 'error'); return; }
    const r = await apiFetch(`${BASE_URL}/api/school/students/${STUDENT_ID}/grades`, { method: 'POST', body: JSON.stringify(payload) });
    if (r.success) window.location.reload();
    else Dialog.toast(r.error || 'Erreur.', 'error');
}

async function deleteGrade(id) {
    const ok = await Dialog.confirm('Supprimer cette note ?');
    if (!ok) return;
    const r = await apiFetch(`${BASE_URL}/api/school/students/${STUDENT_ID}/grades/${id}/delete`, { method: 'POST' });
    if (r.success) window.location.reload();
}

// ---- Absences ----

async function addAbsence() {
    const payload = {
        absence_date: document.getElementById('absence-date').value,
        subject_id: document.getElementById('absence-subject').value,
        duration: document.getElementById('absence-duration').value,
        reason: document.getElementById('absence-reason').value,
        justified: document.getElementById('absence-justified').checked,
    };
    if (!payload.absence_date) { Dialog.toast('La date est requise.', 'error'); return; }
    const r = await apiFetch(`${BASE_URL}/api/school/students/${STUDENT_ID}/absences`, { method: 'POST', body: JSON.stringify(payload) });
    if (r.success) window.location.reload();
    else Dialog.toast(r.error || 'Erreur.', 'error');
}

async function toggleAbsenceJustified(id, justified) {
    const r = await apiFetch(`${BASE_URL}/api/school/students/${STUDENT_ID}/absences/${id}/justify`, { method: 'POST', body: JSON.stringify({ justified }) });
    if (r.success) window.location.reload();
}

async function deleteAbsence(id) {
    const ok = await Dialog.confirm('Supprimer cette absence ?');
    if (!ok) return;
    const r = await apiFetch(`${BASE_URL}/api/school/students/${STUDENT_ID}/absences/${id}/delete`, { method: 'POST' });
    if (r.success) window.location.reload();
}

// ---- Activités extra-scolaires ----

async function addActivity() {
    const name = document.getElementById('activity-name').value.trim();
    if (!name) { Dialog.toast("Le nom de l'activité est requis.", 'error'); return; }
    const payload = {
        name,
        schedule_info: document.getElementById('activity-schedule').value,
        location: document.getElementById('activity-location').value,
        contact_info: document.getElementById('activity-contact').value,
        notes: document.getElementById('activity-notes').value,
    };
    const r = await apiFetch(`${BASE_URL}/api/school/students/${STUDENT_ID}/activities`, { method: 'POST', body: JSON.stringify(payload) });
    if (r.success) window.location.reload();
    else Dialog.toast(r.error || 'Erreur.', 'error');
}

async function deleteActivity(id) {
    const ok = await Dialog.confirm('Supprimer cette activité ?');
    if (!ok) return;
    const r = await apiFetch(`${BASE_URL}/api/school/students/${STUDENT_ID}/activities/${id}/delete`, { method: 'POST' });
    if (r.success) window.location.reload();
}

// ---- Documents ----

async function uploadSchoolDocument() {
    const input = document.getElementById('doc-file');
    const file = input.files[0];
    if (!file) { Dialog.toast('Choisissez un fichier.', 'error'); return; }
    const formData = new FormData();
    formData.append('file', file);
    formData.append('title', document.getElementById('doc-title').value);
    formData.append('doc_type', document.getElementById('doc-type').value);
    const res = await fetch(`${BASE_URL}/api/school/students/${STUDENT_ID}/documents`, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData,
    });
    const r = await res.json();
    if (r.success) window.location.reload();
    else Dialog.toast(r.error || "Erreur lors de l'envoi.", 'error');
}

async function deleteSchoolDocument(id) {
    const ok = await Dialog.confirm('Supprimer ce document ?');
    if (!ok) return;
    const r = await apiFetch(`${BASE_URL}/api/school/students/${STUDENT_ID}/documents/${id}/delete`, { method: 'POST' });
    if (r.success) window.location.reload();
}
