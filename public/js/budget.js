// ============================================
// FamilyBoard - Budget JS
// ============================================

function openTransactionModal() {
    document.getElementById('tx-title').value = '';
    document.getElementById('tx-amount').value = '';
    document.getElementById('tx-date').value = new Date().toISOString().slice(0,10);
    document.getElementById('tx-category').value = '';
    document.getElementById('tx-category-hint').textContent = '';
    document.getElementById('tx-notes').value = '';
    document.getElementById('tx-type-expense').checked = true;
    openModal('transaction-modal');
}

// Saisie assistée : suggère une catégorie déjà utilisée pour un titre similaire.
async function suggestTxCategory() {
    const title = document.getElementById('tx-title').value.trim();
    const hint = document.getElementById('tx-category-hint');
    if (!title) { hint.textContent = ''; return; }

    const result = await apiFetch(`${BASE_URL}/api/budget/suggest-category?title=${encodeURIComponent(title)}`);
    if (result.suggestion) {
        document.getElementById('tx-category').value = result.suggestion.category_id;
        hint.textContent = `(suggéré : ${result.suggestion.icon} ${result.suggestion.name})`;
    } else {
        hint.textContent = '';
    }
}

async function saveTransaction() {
    const title = document.getElementById('tx-title').value.trim();
    const amount = parseFloat(document.getElementById('tx-amount').value);
    const date = document.getElementById('tx-date').value;

    if (!title || !amount || !date) { Dialog.toast('Titre, montant et date requis.', 'error'); return; }

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
    if (!await Dialog.confirm('Supprimer cette transaction ?')) return;
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
    if (!name || !target) { Dialog.toast('Nom et montant cible requis.', 'error'); return; }

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
    if (!await Dialog.confirm('Supprimer cet objectif ?')) return;
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
    if (!await Dialog.confirm('Supprimer cette catégorie ?')) return;
    const result = await apiFetch(`${BASE_URL}/api/budget/category/${id}/delete`, { method: 'POST' });
    if (result.success) {
        const el = document.querySelector(`.cat-manage-item[data-id="${id}"]`);
        if (el) el.remove();
    }
}

// ──────────────────────────────────────────────────────────────
// Recurring items
// ──────────────────────────────────────────────────────────────

function openRecurringModal() {
    document.getElementById('recurring-modal-title').textContent = 'Nouvelle opération récurrente';
    document.getElementById('rec-id').value = '';
    document.getElementById('rec-type-expense').checked = true;
    document.getElementById('rec-title').value = '';
    document.getElementById('rec-amount').value = '';
    document.getElementById('rec-day').value = '1';
    document.getElementById('rec-category').value = '';
    document.getElementById('rec-alert').value = '3';
    document.getElementById('rec-notes').value = '';
    // Reset user to current user (first option)
    const sel = document.getElementById('rec-user');
    if (sel && sel.options.length) sel.selectedIndex = 0;
    openModal('recurring-modal');
}

function openEditRecurringModal(item) {
    document.getElementById('recurring-modal-title').textContent = 'Modifier l\'opération';
    document.getElementById('rec-id').value = item.id;
    document.querySelector(`input[name="rec-type-r"][value="${item.type}"]`).checked = true;
    document.getElementById('rec-title').value = item.title;
    document.getElementById('rec-amount').value = item.amount;
    document.getElementById('rec-day').value = item.day_of_month;
    document.getElementById('rec-category').value = item.category_id || '';
    document.getElementById('rec-alert').value = item.alert_days_before;
    document.getElementById('rec-notes').value = item.notes || '';
    const sel = document.getElementById('rec-user');
    if (sel) sel.value = item.user_id;
    openModal('recurring-modal');
}

async function saveRecurring() {
    const title = document.getElementById('rec-title').value.trim();
    const amount = parseFloat(document.getElementById('rec-amount').value);
    const day = parseInt(document.getElementById('rec-day').value, 10);
    if (!title || !amount || !day) { Dialog.toast('Titre, montant et jour requis.', 'error'); return; }

    const data = {
        title, amount,
        type: document.querySelector('input[name="rec-type-r"]:checked').value,
        day_of_month: day,
        category_id: document.getElementById('rec-category').value || null,
        user_id: document.getElementById('rec-user').value,
        alert_days_before: parseInt(document.getElementById('rec-alert').value, 10),
        notes: document.getElementById('rec-notes').value,
    };

    const id = document.getElementById('rec-id').value;
    const url = id ? `${BASE_URL}/api/budget/recurring/${id}` : `${BASE_URL}/api/budget/recurring`;
    const result = await apiFetch(url, { method: 'POST', body: JSON.stringify(data) });
    if (result.success) { closeModal('recurring-modal'); location.reload(); }
}

async function deleteRecurring(id) {
    if (!await Dialog.confirm('Supprimer cette opération récurrente ?')) return;
    const result = await apiFetch(`${BASE_URL}/api/budget/recurring/${id}/delete`, { method: 'POST' });
    if (result.success) location.reload();
}

// ──────────────────────────────────────────────────────────────
// Chart.js — fixed vs variable by category
// ──────────────────────────────────────────────────────────────

let _budgetChart = null;
let _chartData   = null;
let _chartType   = 'expense';

async function loadBudgetChart() {
    try {
        _chartData = await apiFetch(`${BASE_URL}/api/budget/chart?month=${CURRENT_MONTH}`);
    } catch(e) { return; }
    renderBudgetChart();
}

function setChartType(type) {
    _chartType = type;
    document.getElementById('chart-btn-expense').classList.toggle('btn-primary', type === 'expense');
    document.getElementById('chart-btn-expense').classList.toggle('btn-secondary', type !== 'expense');
    document.getElementById('chart-btn-income').classList.toggle('btn-primary', type === 'income');
    document.getElementById('chart-btn-income').classList.toggle('btn-secondary', type !== 'income');
    renderBudgetChart();
}

function renderBudgetChart() {
    if (!_chartData) return;
    const canvas = document.getElementById('budget-chart');
    if (!canvas) return;

    const rows = (_chartData[_chartType] || []);
    const labels   = rows.map(r => r.category || 'Sans catégorie');
    const fixed    = rows.map(r => parseFloat(r.fixed)    || 0);
    const variable = rows.map(r => parseFloat(r.variable) || 0);

    if (_budgetChart) { _budgetChart.destroy(); _budgetChart = null; }

    if (!rows.length) {
        const ctx = canvas.getContext('2d');
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        return;
    }

    _budgetChart = new Chart(canvas, {
        type: 'bar',
        data: {
            labels,
            datasets: [
                { label: 'Fixes (récurrents)', data: fixed,    backgroundColor: '#4A90D9', stack: 'a' },
                { label: 'Variables',           data: variable, backgroundColor: '#F39C12', stack: 'a' },
            ],
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: {
                x: { stacked: true },
                y: {
                    stacked: true,
                    ticks: { callback: v => v + ' €' },
                },
            },
        },
    });
}

// Init chart on page load
document.addEventListener('DOMContentLoaded', () => {
    setChartType('expense');
    loadBudgetChart();
});
