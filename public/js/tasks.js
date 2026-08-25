// ============================================
// FamilyBoard - Tasks JS
// ============================================

let editingTaskId = null;

function openNewListModal() {
    openModal('new-list-modal');
}

function openLinkEventModal() {
    document.getElementById('link-event-select').value = '';
    openModal('link-event-modal');
}

async function linkListEvent() {
    const eventId = document.getElementById('link-event-select').value;
    if (!eventId) return;
    const res = await apiFetch(BASE_URL + '/api/tasks/list/' + LIST_ID + '/link-event', {
        method: 'POST',
        body: JSON.stringify({ event_id: eventId }),
    });
    if (res && res.success) {
        location.reload();
    } else {
        Dialog.alert((res && res.error) || "Impossible de lier cet événement.");
    }
}

async function unlinkListEvent() {
    const confirmed = await Dialog.confirm('Délier cette liste de l\'événement ? Les tâches déjà cochées restent inchangées.');
    if (!confirmed) return;
    const res = await apiFetch(BASE_URL + '/api/tasks/list/' + LIST_ID + '/unlink-event', { method: 'POST' });
    if (res && res.success) location.reload();
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
    if (!title) { Dialog.toast('Le titre est requis.', 'error'); return; }

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
    if (!await Dialog.confirm('Supprimer cette tâche ?')) return;
    const result = await apiFetch(`${BASE_URL}/api/tasks/task/${id}/delete`, { method: 'POST' });
    if (result.success) {
        const item = document.querySelector(`[data-task-id="${id}"]`);
        if (item) item.remove();
    }
}

// ---- Partage public (lien, sans compte) ----

function showShareLink(share) {
    const container = document.getElementById('share-qr-container');
    container.innerHTML = '';
    const qr = qrcode(0, 'M');
    qr.addData(share.url);
    qr.make();
    container.innerHTML = qr.createSvgTag({ cellSize: 5, margin: 4 });
    document.getElementById('share-link-text').textContent = share.url;
    document.getElementById('share-open-link').href = share.url;
    document.getElementById('share-copy-btn').onclick = () => copyCode(share.url);
    openModal('share-list-modal');
}

async function openShareModal(listId) {
    const r = await apiFetch(`${BASE_URL}/api/tasks/list/${listId}/share`, { method: 'POST', body: '{}' });
    if (r.success) showShareLink(r.share);
    else Dialog.toast(r.error || 'Erreur.', 'error');
}

async function regenerateShareLink() {
    const ok = await Dialog.confirm('Régénérer le lien ? L\'ancien lien cessera immédiatement de fonctionner.');
    if (!ok) return;
    const r = await apiFetch(`${BASE_URL}/api/tasks/list/${LIST_ID}/share/regenerate`, { method: 'POST', body: '{}' });
    if (r.success) showShareLink(r.share);
}

async function revokeShareLink() {
    const ok = await Dialog.confirm('Révoquer ce lien de partage ?');
    if (!ok) return;
    const r = await apiFetch(`${BASE_URL}/api/tasks/list/${LIST_ID}/share/revoke`, { method: 'POST' });
    if (r.success) {
        closeModal('share-list-modal');
        Dialog.toast('Lien révoqué.');
    }
}
