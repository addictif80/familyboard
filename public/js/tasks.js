// ============================================
// FamilyBoard - Tasks JS
// ============================================

let editingTaskId = null;

function openNewListModal() {
    openModal('new-list-modal');
}

function openNewTaskModal() {
    editingTaskId = null;
    document.getElementById('task-modal-title').textContent = 'Nouvelle tâche';
    document.getElementById('task-id').value = '';
    document.getElementById('task-title').value = '';
    document.getElementById('task-notes').value = '';
    document.getElementById('task-assigned').value = '';
    document.getElementById('task-priority').value = 'medium';
    document.getElementById('task-due').value = '';
    openModal('task-modal');
}

function openEditTaskModal(task) {
    editingTaskId = task.id;
    document.getElementById('task-modal-title').textContent = 'Modifier la tâche';
    document.getElementById('task-id').value = task.id;
    document.getElementById('task-title').value = task.title;
    document.getElementById('task-notes').value = task.notes || '';
    document.getElementById('task-assigned').value = task.assigned_to || '';
    document.getElementById('task-priority').value = task.priority || 'medium';
    document.getElementById('task-due').value = task.due_date || '';
    openModal('task-modal');
}

async function saveTask() {
    const title = document.getElementById('task-title').value.trim();
    if (!title) { alert('Le titre est requis.'); return; }

    const data = {
        title,
        notes: document.getElementById('task-notes').value,
        assigned_to: document.getElementById('task-assigned').value || null,
        priority: document.getElementById('task-priority').value,
        due_date: document.getElementById('task-due').value || null,
    };

    const id = document.getElementById('task-id').value;
    let result;
    if (id) {
        result = await apiFetch(`${BASE_URL}/api/tasks/task/${id}/update`, { method: 'POST', body: JSON.stringify(data) });
    } else {
        result = await apiFetch(`${BASE_URL}/api/tasks/list/${LIST_ID}/task`, { method: 'POST', body: JSON.stringify(data) });
    }

    if (result.success) {
        closeModal('task-modal');
        location.reload();
    }
}

async function toggleTask(id) {
    const result = await apiFetch(`${BASE_URL}/api/tasks/task/${id}/toggle`, { method: 'POST' });
    if (result.success) {
        const item = document.querySelector(`[data-task-id="${id}"]`);
        if (item) {
            item.classList.toggle('completed', result.completed);
            const btn = item.querySelector('.task-check');
            if (btn) btn.textContent = result.completed ? '✅' : '⬜';
        }
    }
}

async function deleteTask(id) {
    if (!confirm('Supprimer cette tâche ?')) return;
    const result = await apiFetch(`${BASE_URL}/api/tasks/task/${id}/delete`, { method: 'POST' });
    if (result.success) {
        const item = document.querySelector(`[data-task-id="${id}"]`);
        if (item) item.remove();
    }
}
