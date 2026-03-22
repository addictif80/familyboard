// ============================================
// FamilyBoard - Budget JS
// ============================================

function openTransactionModal() {
    document.getElementById('tx-title').value = '';
    document.getElementById('tx-amount').value = '';
    document.getElementById('tx-date').value = new Date().toISOString().slice(0,10);
    document.getElementById('tx-category').value = '';
    document.getElementById('tx-notes').value = '';
    document.getElementById('tx-type-expense').checked = true;
    openModal('transaction-modal');
}

async function saveTransaction() {
    const title = document.getElementById('tx-title').value.trim();
    const amount = parseFloat(document.getElementById('tx-amount').value);
    const date = document.getElementById('tx-date').value;

    if (!title || !amount || !date) { alert('Titre, montant et date requis.'); return; }

    const type = document.querySelector('input[name="tx-type-r"]:checked').value;
    const data = {
        title, amount, date, type,
        category_id: document.getElementById('tx-category').value || null,
        notes: document.getElementById('tx-notes').value,
    };

    const result = await apiFetch(`${BASE_URL}/api/budget/transaction`, { method: 'POST', body: JSON.stringify(data) });
    if (result.success) { closeModal('transaction-modal'); location.reload(); }
}

async function deleteTransaction(id) {
    if (!confirm('Supprimer cette transaction ?')) return;
    const result = await apiFetch(`${BASE_URL}/api/budget/transaction/${id}/delete`, { method: 'POST' });
    if (result.success) {
        const el = document.querySelector(`.transaction-item[data-id="${id}"]`);
        if (el) el.remove();
    }
}

function openGoalModal() {
    document.getElementById('goal-modal-title').textContent = 'Nouvel objectif';
    document.getElementById('goal-id').value = '';
    document.getElementById('goal-name').value = '';
    document.getElementById('goal-target').value = '';
    document.getElementById('goal-current').value = '0';
    document.getElementById('goal-deadline').value = '';
    document.getElementById('goal-color').value = '#4A90D9';
    openModal('goal-modal');
}

function openEditGoalModal(goal) {
    document.getElementById('goal-modal-title').textContent = 'Modifier l\'objectif';
    document.getElementById('goal-id').value = goal.id;
    document.getElementById('goal-name').value = goal.name;
    document.getElementById('goal-target').value = goal.target_amount;
    document.getElementById('goal-current').value = goal.current_amount;
    document.getElementById('goal-deadline').value = goal.deadline || '';
    document.getElementById('goal-color').value = goal.color;
    openModal('goal-modal');
}

async function saveGoal() {
    const name = document.getElementById('goal-name').value.trim();
    const target = parseFloat(document.getElementById('goal-target').value);
    if (!name || !target) { alert('Nom et montant cible requis.'); return; }

    const data = {
        name, target_amount: target,
        current_amount: parseFloat(document.getElementById('goal-current').value) || 0,
        deadline: document.getElementById('goal-deadline').value || null,
        color: document.getElementById('goal-color').value,
    };

    const id = document.getElementById('goal-id').value;
    const url = id ? `${BASE_URL}/api/budget/goal/${id}` : `${BASE_URL}/api/budget/goal`;
    const result = await apiFetch(url, { method: 'POST', body: JSON.stringify(data) });
    if (result.success) { closeModal('goal-modal'); location.reload(); }
}

async function deleteGoal(id) {
    if (!confirm('Supprimer cet objectif ?')) return;
    const result = await apiFetch(`${BASE_URL}/api/budget/goal/${id}/delete`, { method: 'POST' });
    if (result.success) location.reload();
}

function openCategoryModal() {
    openModal('category-modal');
}

async function createCategory() {
    const name = document.getElementById('new-cat-name').value.trim();
    if (!name) return;
    const data = {
        name,
        icon: document.getElementById('new-cat-icon').value || '💰',
        color: document.getElementById('new-cat-color').value,
    };
    const result = await apiFetch(`${BASE_URL}/api/budget/category`, { method: 'POST', body: JSON.stringify(data) });
    if (result.success) {
        document.getElementById('new-cat-name').value = '';
        document.getElementById('new-cat-icon').value = '';
        const list = document.getElementById('cat-list');
        const div = document.createElement('div');
        div.className = 'cat-manage-item';
        div.setAttribute('data-id', result.id);
        div.innerHTML = `<span>${escapeHtml(data.icon)} ${escapeHtml(name)}</span><button onclick="deleteCategory(${result.id})" class="btn-icon-sm">🗑</button>`;
        list.appendChild(div);
    }
}

async function deleteCategory(id) {
    if (!confirm('Supprimer cette catégorie ?')) return;
    const result = await apiFetch(`${BASE_URL}/api/budget/category/${id}/delete`, { method: 'POST' });
    if (result.success) {
        const el = document.querySelector(`.cat-manage-item[data-id="${id}"]`);
        if (el) el.remove();
    }
}
