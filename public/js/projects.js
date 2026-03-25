// ============================================
// FamilyBoard - Projects JS
// ============================================

// Project list page
function openProjectModal() {
    openModal('project-modal');
}

async function createProject() {
    const name = document.getElementById('proj-name').value.trim();
    if (!name) { Dialog.toast('Nom requis.', 'error'); return; }

    const data = {
        name,
        description: document.getElementById('proj-desc').value,
        budget: parseFloat(document.getElementById('proj-budget').value) || null,
        deadline: document.getElementById('proj-deadline').value || null,
        color: document.getElementById('proj-color').value,
    };

    const result = await apiFetch(`${BASE_URL}/api/projects`, { method: 'POST', body: JSON.stringify(data) });
    if (result.success) {
        window.location.href = `${BASE_URL}/projects/${result.id}`;
    }
}

// Project detail page
function openEditProjectModal(project) {
    document.getElementById('edit-proj-name').value = project.name;
    document.getElementById('edit-proj-desc').value = project.description || '';
    document.getElementById('edit-proj-status').value = project.status;
    document.getElementById('edit-proj-color').value = project.color;
    document.getElementById('edit-proj-budget').value = project.budget || '';
    document.getElementById('edit-proj-deadline').value = project.deadline || '';
    openModal('edit-project-modal');
}

async function updateProject() {
    const data = {
        name: document.getElementById('edit-proj-name').value.trim(),
        description: document.getElementById('edit-proj-desc').value,
        status: document.getElementById('edit-proj-status').value,
        color: document.getElementById('edit-proj-color').value,
        budget: parseFloat(document.getElementById('edit-proj-budget').value) || null,
        deadline: document.getElementById('edit-proj-deadline').value || null,
    };
    const result = await apiFetch(`${BASE_URL}/api/projects/${PROJECT_ID}`, { method: 'POST', body: JSON.stringify(data) });
    if (result.success) { closeModal('edit-project-modal'); location.reload(); }
}

async function deleteProject(id) {
    if (!await Dialog.confirm('Supprimer ce projet et toutes ses données ?')) return;
    const result = await apiFetch(`${BASE_URL}/api/projects/${id}/delete`, { method: 'POST' });
    if (result.success) window.location.href = `${BASE_URL}/projects`;
}

// Tasks
let editingProjectTaskId = null;

function openTaskModal() {
    editingProjectTaskId = null;
    document.getElementById('ptask-modal-title').textContent = 'Nouvelle tâche';
    document.getElementById('ptask-id').value = '';
    document.getElementById('ptask-title').value = '';
    document.getElementById('ptask-desc').value = '';
    document.getElementById('ptask-status').value = 'todo';
    document.getElementById('ptask-priority').value = 'medium';
    document.getElementById('ptask-assigned').value = '';
    document.getElementById('ptask-due-date').value = '';
    openModal('project-task-modal');
}

function openEditProjectTask(task) {
    editingProjectTaskId = task.id;
    document.getElementById('ptask-modal-title').textContent = 'Modifier la tâche';
    document.getElementById('ptask-id').value = task.id;
    document.getElementById('ptask-title').value = task.title;
    document.getElementById('ptask-desc').value = task.description || '';
    document.getElementById('ptask-status').value = task.status;
    document.getElementById('ptask-priority').value = task.priority;
    document.getElementById('ptask-assigned').value = task.assigned_to || '';
    document.getElementById('ptask-due-date').value = task.due_date || '';
    openModal('project-task-modal');
}

async function saveProjectTask() {
    const title = document.getElementById('ptask-title').value.trim();
    if (!title) { Dialog.toast('Titre requis.', 'error'); return; }

    const data = {
        title,
        description: document.getElementById('ptask-desc').value,
        status: document.getElementById('ptask-status').value,
        priority: document.getElementById('ptask-priority').value,
        assigned_to: document.getElementById('ptask-assigned').value || null,
        due_date: document.getElementById('ptask-due-date').value || null,
    };

    const id = document.getElementById('ptask-id').value;
    let result;
    if (id) {
        result = await apiFetch(`${BASE_URL}/api/projects/task/${id}`, { method: 'POST', body: JSON.stringify(data) });
    } else {
        result = await apiFetch(`${BASE_URL}/api/projects/${PROJECT_ID}/task`, { method: 'POST', body: JSON.stringify(data) });
    }
    if (result.success) { closeModal('project-task-modal'); location.reload(); }
}

async function deleteProjectTask(id) {
    if (!await Dialog.confirm('Supprimer cette tâche ?')) return;
    const result = await apiFetch(`${BASE_URL}/api/projects/task/${id}/delete`, { method: 'POST' });
    if (result.success) {
        const el = document.querySelector(`.project-task-item[data-id="${id}"]`);
        if (el) el.remove();
    }
}

// Expenses
function openExpenseModal() {
    document.getElementById('exp-title').value = '';
    document.getElementById('exp-amount').value = '';
    document.getElementById('exp-date').value = new Date().toISOString().slice(0,10);
    document.getElementById('exp-notes').value = '';
    openModal('expense-modal');
}

async function saveExpense() {
    const title = document.getElementById('exp-title').value.trim();
    const amount = parseFloat(document.getElementById('exp-amount').value);
    const date = document.getElementById('exp-date').value;
    if (!title || !amount || !date) { Dialog.toast('Description, montant et date requis.', 'error'); return; }

    const data = { title, amount, date, notes: document.getElementById('exp-notes').value };
    const result = await apiFetch(`${BASE_URL}/api/projects/${PROJECT_ID}/expense`, { method: 'POST', body: JSON.stringify(data) });
    if (result.success) { closeModal('expense-modal'); location.reload(); }
}

async function deleteExpense(id) {
    if (!await Dialog.confirm('Supprimer cette dépense ?')) return;
    const result = await apiFetch(`${BASE_URL}/api/projects/expense/${id}/delete`, { method: 'POST' });
    if (result.success) {
        const el = document.querySelector(`.expense-item[data-id="${id}"]`);
        if (el) el.remove();
    }
}

// ── Materials ─────────────────────────────────────────────────

let _editingMaterialId = null;

function openMaterialModal() {
    _editingMaterialId = null;
    document.getElementById('mat-modal-title').textContent = 'Nouveau matériau';
    document.getElementById('mat-id').value = '';
    document.getElementById('mat-name').value = '';
    document.getElementById('mat-url').value = '';
    document.getElementById('mat-price').value = '';
    document.getElementById('mat-qty').value = '1';
    document.getElementById('mat-unit').value = '';
    document.getElementById('mat-notes').value = '';
    openModal('material-modal');
}

function openEditMaterialModal(mat) {
    _editingMaterialId = mat.id;
    document.getElementById('mat-modal-title').textContent = 'Modifier le matériau';
    document.getElementById('mat-id').value = mat.id;
    document.getElementById('mat-name').value = mat.name;
    document.getElementById('mat-url').value = mat.url || '';
    document.getElementById('mat-price').value = mat.price ?? '';
    document.getElementById('mat-qty').value = mat.quantity ?? 1;
    document.getElementById('mat-unit').value = mat.unit || '';
    document.getElementById('mat-notes').value = mat.notes || '';
    openModal('material-modal');
}

async function saveMaterial() {
    const name = document.getElementById('mat-name').value.trim();
    if (!name) { Dialog.toast('Nom requis.', 'error'); return; }

    const data = {
        name,
        url:      document.getElementById('mat-url').value.trim() || null,
        price:    parseFloat(document.getElementById('mat-price').value) || null,
        quantity: parseFloat(document.getElementById('mat-qty').value) || 1,
        unit:     document.getElementById('mat-unit').value.trim() || null,
        notes:    document.getElementById('mat-notes').value.trim() || null,
    };

    const id = document.getElementById('mat-id').value;
    const url = id
        ? `${BASE_URL}/api/projects/material/${id}`
        : `${BASE_URL}/api/projects/${PROJECT_ID}/material`;

    const result = await apiFetch(url, { method: 'POST', body: JSON.stringify(data) });
    if (result.success) { closeModal('material-modal'); location.reload(); }
}

async function toggleMaterial(id, checkbox) {
    const result = await apiFetch(`${BASE_URL}/api/projects/material/${id}/toggle`, { method: 'POST' });
    if (result.success) {
        const row = document.querySelector(`.material-item[data-id="${id}"]`);
        if (row) row.classList.toggle('mat-purchased', checkbox.checked);
    } else {
        checkbox.checked = !checkbox.checked;
    }
}

async function deleteMaterial(id) {
    if (!await Dialog.confirm('Supprimer ce matériau ?')) return;
    const result = await apiFetch(`${BASE_URL}/api/projects/material/${id}/delete`, { method: 'POST' });
    if (result.success) {
        const el = document.querySelector(`.material-item[data-id="${id}"]`);
        if (el) el.remove();
    }
}

// ── Calendrier du projet ───────────────────────────────────────

let _projCalDate = new Date();
let _projCalEvents = [];

const _projIntlMonth = new Intl.DateTimeFormat('fr-FR', { month: 'long' });
const _projIntlDay   = new Intl.DateTimeFormat('fr-FR', { weekday: 'short' });

function _projFmtMonthYear(year, month) {
    const d = new Date(year, month, 1);
    const m = _projIntlMonth.format(d);
    return m.charAt(0).toUpperCase() + m.slice(1) + ' ' + year;
}

function _projDayNames() {
    const names = [];
    for (let i = 1; i <= 7; i++) {
        const d = new Date(2024, 0, i);
        const s = _projIntlDay.format(d);
        names.push(s.charAt(0).toUpperCase() + s.slice(1).replace('.', ''));
    }
    return names;
}

async function _loadProjCalEvents() {
    try {
        const data = await apiFetch(`${BASE_URL}/api/projects/${PROJECT_ID}/calendar`);
        _projCalEvents = Array.isArray(data) ? data : [];
    } catch (e) {
        _projCalEvents = [];
    }
    _renderProjCal();
}

function _renderProjCal() {
    const container = document.getElementById('proj-calendar');
    if (!container) return;

    const year  = _projCalDate.getFullYear();
    const month = _projCalDate.getMonth();

    document.getElementById('proj-cal-label').textContent = _projFmtMonthYear(year, month);

    const firstDay = new Date(year, month, 1);
    let startDow = firstDay.getDay();
    if (startDow === 0) startDow = 7;
    const startDate = new Date(firstDay);
    startDate.setDate(startDate.getDate() - (startDow - 1));

    const dayNames = _projDayNames();
    let html = `<div class="cal-grid">${dayNames.map(d => `<div class="cal-dayname">${d}</div>`).join('')}`;

    const today = new Date(); today.setHours(0,0,0,0);

    let d = new Date(startDate);
    for (let i = 0; i < 42; i++) {
        const isCurrentMonth = d.getMonth() === month;
        const isToday = d.getTime() === today.getTime();
        const dateStr = d.toISOString().slice(0, 10);

        const dayEvents = _projCalEvents.filter(e => e.start === dateStr);

        const dotsHtml = '<div class="cal-day-dots">' +
            dayEvents.slice(0, 4).map(e =>
                `<span class="cal-day-dot" style="background:${e.color || '#4A90D9'}"></span>`
            ).join('') + '</div>';

        let eventsHtml = '';
        dayEvents.slice(0, 3).forEach(e => {
            const label = e.title.length > 18 ? e.title.slice(0, 16) + '…' : e.title;
            const taskId = e.extendedProps?.task_id;
            const onclick = taskId ? `onclick="projCalTaskClick(${taskId})"` : '';
            eventsHtml += `<div class="cal-event" style="background:${e.color || '#4A90D9'}" ${onclick} title="${e.title}">${label}</div>`;
        });

        html += `<div class="cal-day ${isCurrentMonth ? '' : 'cal-other-month'} ${isToday ? 'cal-today' : ''}">
            <span class="cal-day-num">${d.getDate()}</span>
            ${dotsHtml}
            <div class="cal-events">${eventsHtml}</div>
        </div>`;

        d.setDate(d.getDate() + 1);
    }
    html += '</div>';
    container.innerHTML = html;
}

function projCalPrev()  { _projCalDate.setMonth(_projCalDate.getMonth() - 1); _renderProjCal(); }
function projCalNext()  { _projCalDate.setMonth(_projCalDate.getMonth() + 1); _renderProjCal(); }
function projCalToday() { _projCalDate = new Date(); _renderProjCal(); }

function projCalTaskClick(taskId) {
    // find the task in the DOM and open the edit modal
    const el = document.querySelector(`.project-task-item[data-id="${taskId}"]`);
    if (el) el.querySelector('.task-body')?.click();
}

// Init on project detail page
if (typeof PROJECT_ID !== 'undefined' && document.getElementById('proj-calendar')) {
    _loadProjCalEvents();
}
