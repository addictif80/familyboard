<?php
$pageTitle = 'Budget familial';
$extraJs = ['budget.js'];
ob_start();
?>
<div class="budget-container">
    <!-- Month nav -->
    <div class="budget-nav">
        <?php
        $prevMonth = date('Y-m', strtotime($month . '-01 -1 month'));
        $nextMonth = date('Y-m', strtotime($month . '-01 +1 month'));
        ?>
        <a href="?month=<?= $prevMonth ?>" class="btn btn-secondary btn-sm">‹</a>
        <h3><?= date('F Y', strtotime($month . '-01')) ?></h3>
        <a href="?month=<?= $nextMonth ?>" class="btn btn-secondary btn-sm">›</a>
    </div>

    <!-- Summary cards -->
    <div class="budget-summary">
        <div class="summary-card summary-income">
            <span class="summary-label">Revenus</span>
            <span class="summary-amount">+<?= number_format($summary['income'], 2, ',', ' ') ?> €</span>
        </div>
        <div class="summary-card summary-expenses">
            <span class="summary-label">Dépenses</span>
            <span class="summary-amount">-<?= number_format($summary['expenses'], 2, ',', ' ') ?> €</span>
        </div>
        <div class="summary-card <?= $summary['balance'] >= 0 ? 'summary-positive' : 'summary-negative' ?>">
            <span class="summary-label">Solde</span>
            <span class="summary-amount"><?= ($summary['balance'] >= 0 ? '+' : '') . number_format($summary['balance'], 2, ',', ' ') ?> €</span>
        </div>
    </div>

    <div class="budget-main-grid">
        <!-- Transactions -->
        <div class="card">
            <div class="card-header">
                <h3>Transactions</h3>
                <button class="btn btn-primary btn-sm" onclick="openTransactionModal()">+ Ajouter</button>
            </div>
            <div class="transactions-list" id="transactions-list">
                <?php foreach ($transactions as $tx): ?>
                    <div class="transaction-item" data-id="<?= $tx['id'] ?>">
                        <div class="tx-icon"><?= htmlspecialchars($tx['category_icon'] ?? '💰') ?></div>
                        <div class="tx-body">
                            <strong><?= htmlspecialchars($tx['title']) ?></strong>
                            <small><?= htmlspecialchars($tx['category_name'] ?? 'Sans catégorie') ?> · <?= date('d/m', strtotime($tx['date'])) ?> · <?= htmlspecialchars($tx['user_name']) ?></small>
                        </div>
                        <span class="tx-amount <?= $tx['type'] === 'income' ? 'amount-income' : 'amount-expense' ?>">
                            <?= $tx['type'] === 'income' ? '+' : '-' ?><?= number_format($tx['amount'], 2, ',', ' ') ?> €
                        </span>
                        <button class="btn-icon-sm" onclick="deleteTransaction(<?= $tx['id'] ?>)">🗑</button>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($transactions)): ?>
                    <p class="empty-state">Aucune transaction ce mois-ci.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Goals & categories -->
        <div>
            <!-- Goals -->
            <div class="card">
                <div class="card-header">
                    <h3>Objectifs d'épargne</h3>
                    <button class="btn btn-sm btn-secondary" onclick="openGoalModal()">+</button>
                </div>
                <?php foreach ($goals as $goal): ?>
                    <?php $pct = $goal['target_amount'] > 0 ? min(100, round($goal['current_amount'] / $goal['target_amount'] * 100)) : 0; ?>
                    <div class="goal-item" data-id="<?= $goal['id'] ?>">
                        <div class="goal-header">
                            <strong><?= htmlspecialchars($goal['name']) ?></strong>
                            <span><?= number_format($goal['current_amount'], 0, ',', ' ') ?> / <?= number_format($goal['target_amount'], 0, ',', ' ') ?> €</span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width:<?= $pct ?>%;background:<?= htmlspecialchars($goal['color']) ?>"></div>
                        </div>
                        <div class="goal-footer">
                            <small><?= $pct ?>%</small>
                            <?php if ($goal['deadline']): ?>
                                <small>Objectif: <?= date('d/m/Y', strtotime($goal['deadline'])) ?></small>
                            <?php endif; ?>
                            <button class="btn-icon-sm" onclick="openEditGoalModal(<?= htmlspecialchars(json_encode($goal)) ?>)">✏️</button>
                            <button class="btn-icon-sm" onclick="deleteGoal(<?= $goal['id'] ?>)">🗑</button>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($goals)): ?>
                    <p class="empty-state">Aucun objectif.</p>
                <?php endif; ?>
            </div>

            <!-- Category breakdown -->
            <div class="card" style="margin-top:1rem">
                <h3>Par catégorie</h3>
                <?php foreach ($breakdown as $cat): ?>
                    <div class="cat-bar">
                        <div class="cat-label">
                            <span><?= htmlspecialchars($cat['icon'] ?? '💰') ?></span>
                            <span><?= htmlspecialchars($cat['name'] ?? 'Sans catégorie') ?></span>
                            <span class="<?= $cat['type'] === 'income' ? 'amount-income' : 'amount-expense' ?>"><?= number_format($cat['total'], 2, ',', ' ') ?> €</span>
                        </div>
                    </div>
                <?php endforeach; ?>
                <div style="margin-top:.5rem">
                    <button class="btn btn-sm btn-secondary" onclick="openCategoryModal()">⚙️ Gérer les catégories</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Transaction Modal -->
<div class="modal-overlay" id="transaction-modal" style="display:none">
    <div class="modal">
        <div class="modal-header">
            <h3>Nouvelle transaction</h3>
            <button onclick="closeModal('transaction-modal')">✕</button>
        </div>
        <div class="modal-body">
            <div class="form-row">
                <label class="radio-option">
                    <input type="radio" name="tx-type-r" id="tx-type-expense" value="expense" checked> 💸 Dépense
                </label>
                <label class="radio-option">
                    <input type="radio" name="tx-type-r" id="tx-type-income" value="income"> 💵 Revenu
                </label>
            </div>
            <div class="form-group">
                <label>Titre</label>
                <input type="text" id="tx-title" placeholder="Courses, salaire…">
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Montant (€)</label>
                    <input type="number" id="tx-amount" step="0.01" min="0" placeholder="0.00">
                </div>
                <div class="form-group">
                    <label>Date</label>
                    <input type="date" id="tx-date" value="<?= date('Y-m-d') ?>">
                </div>
            </div>
            <div class="form-group">
                <label>Catégorie</label>
                <select id="tx-category">
                    <option value="">Sans catégorie</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['icon']) ?> <?= htmlspecialchars($cat['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Notes</label>
                <textarea id="tx-notes" rows="2"></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('transaction-modal')">Annuler</button>
            <button class="btn btn-primary" onclick="saveTransaction()">Enregistrer</button>
        </div>
    </div>
</div>

<!-- Goal Modal -->
<div class="modal-overlay" id="goal-modal" style="display:none">
    <div class="modal modal-sm">
        <div class="modal-header">
            <h3 id="goal-modal-title">Nouvel objectif</h3>
            <button onclick="closeModal('goal-modal')">✕</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="goal-id">
            <div class="form-group">
                <label>Nom</label>
                <input type="text" id="goal-name" placeholder="Vacances, voiture…">
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Objectif (€)</label>
                    <input type="number" id="goal-target" step="0.01" placeholder="5000">
                </div>
                <div class="form-group">
                    <label>Épargné (€)</label>
                    <input type="number" id="goal-current" step="0.01" placeholder="0">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Échéance</label>
                    <input type="date" id="goal-deadline">
                </div>
                <div class="form-group">
                    <label>Couleur</label>
                    <input type="color" id="goal-color" value="#4A90D9">
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('goal-modal')">Annuler</button>
            <button class="btn btn-primary" onclick="saveGoal()">Enregistrer</button>
        </div>
    </div>
</div>

<!-- Category Modal -->
<div class="modal-overlay" id="category-modal" style="display:none">
    <div class="modal modal-sm">
        <div class="modal-header">
            <h3>Catégories</h3>
            <button onclick="closeModal('category-modal')">✕</button>
        </div>
        <div class="modal-body">
            <div id="cat-list">
                <?php foreach ($categories as $cat): ?>
                    <div class="cat-manage-item" data-id="<?= $cat['id'] ?>">
                        <span><?= htmlspecialchars($cat['icon']) ?> <?= htmlspecialchars($cat['name']) ?></span>
                        <button onclick="deleteCategory(<?= $cat['id'] ?>)" class="btn-icon-sm">🗑</button>
                    </div>
                <?php endforeach; ?>
            </div>
            <hr>
            <div class="form-row">
                <input type="text" id="new-cat-name" placeholder="Nouvelle catégorie">
                <input type="text" id="new-cat-icon" placeholder="💡" style="width:60px">
                <input type="color" id="new-cat-color" value="#4A90D9" style="width:50px">
                <button class="btn btn-primary btn-sm" onclick="createCategory()">Ajouter</button>
            </div>
        </div>
    </div>
</div>

<script>
const CURRENT_MONTH = <?= json_encode($month) ?>;
const BASE_URL = <?= json_encode(BASE_URL) ?>;
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
