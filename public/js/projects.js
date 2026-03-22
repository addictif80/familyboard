// ============================================
// FamilyBoard - Projects JS
// ============================================

// Project list page
function openProjectModal() {
    openModal('project-modal');
}

async function createProject() {
    const name = document.getElementById('proj-name').value.trim();
    if (!name) { alert('Nom requis.'); return; }

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
    if (!confirm('Supprimer ce projet et toutes ses données ?')) return;
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
    openModal('project-task-modal');
}

async function saveProjectTask() {
    const title = document.getElementById('ptask-title').value.trim();
    if (!title) { alert('Titre requis.'); return; }

    const data = {
        title,
        description: document.getElementById('ptask-desc').value,
        status: document.getElementById('ptask-status').value,
        priority: document.getElementById('ptask-priority').value,
        assigned_to: document.getElementById('ptask-assigned').value || null,
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
    if (!confirm('Supprimer cette tâche ?')) return;
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
    if (!title || !amount || !date) { alert('Description, montant et date requis.'); return; }

    const data = { title, amount, date, notes: document.getElementById('exp-notes').value };
    const result = await apiFetch(`${BASE_URL}/api/projects/${PROJECT_ID}/expense`, { method: 'POST', body: JSON.stringify(data) });
    if (result.success) { closeModal('expense-modal'); location.reload(); }
}

async function deleteExpense(id) {
    if (!confirm('Supprimer cette dépense ?')) return;
    const result = await apiFetch(`${BASE_URL}/api/projects/expense/${id}/delete`, { method: 'POST' });
    if (result.success) {
        const el = document.querySelector(`.expense-item[data-id="${id}"]`);
        if (el) el.remove();
    }
}
