<?php
$pageTitle = 'Budget familial';
$extraJs   = ['budget.js'];
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
        <h3><?= \App\Core\DateHelper::monthYearUc($month . '-01') ?></h3>
        <a href="?month=<?= $nextMonth ?>" class="btn btn-secondary btn-sm">›</a>
    </div>

    <!-- Summary cards -->
    <div class="budget-summary">
        <div class="summary-card summary-income">
            <span class="summary-label">Revenus réels</span>
            <span class="summary-amount">+<?= number_format($summary['income'], 2, ',', ' ') ?> €</span>
        </div>
        <div class="summary-card summary-expenses">
            <span class="summary-label">Dépenses réelles</span>
            <span class="summary-amount">-<?= number_format($summary['expenses'], 2, ',', ' ') ?> €</span>
        </div>
        <div class="summary-card <?= $summary['balance'] >= 0 ? 'summary-positive' : 'summary-negative' ?>">
            <span class="summary-label">Solde réel</span>
            <span class="summary-amount"><?= ($summary['balance'] >= 0 ? '+' : '') . number_format($summary['balance'], 2, ',', ' ') ?> €</span>
        </div>
        <div class="summary-card" style="border-left:3px solid var(--primary)">
            <span class="summary-label">Budget fixe / mois</span>
            <span class="summary-amount" style="font-size:.95rem">
                <span style="color:var(--success)">+<?= number_format($recurringSummary['income'], 2, ',', ' ') ?> €</span>
                &nbsp;/&nbsp;
                <span style="color:var(--danger)">-<?= number_format($recurringSummary['expenses'], 2, ',', ' ') ?> €</span>
            </span>
        </div>
    </div>

    <!-- Recurring section -->
    <div class="card" style="margin-bottom:1.25rem">
        <div class="card-header">
            <h3>🔁 Dépenses fixes &amp; revenus récurrents</h3>
            <button class="btn btn-primary btn-sm" onclick="openRecurringModal()">+ Ajouter</button>
        </div>
        <?php if (empty($recurring)): ?>
            <p class="empty-state">Aucun élément récurrent. Ajoutez vos loyers, salaires, abonnements…</p>
        <?php else: ?>
        <div class="recurring-table-wrap">
            <table class="recurring-table">
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Titre</th>
                        <th>Montant</th>
                        <th>Catégorie</th>
                        <th>Membre</th>
                        <th>Jour</th>
                        <th>Alerte</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recurring as $r): ?>
                    <tr class="<?= $r['is_active'] ? '' : 'recurring-inactive' ?>">
                        <td>
                            <span class="badge <?= $r['type'] === 'income' ? 'badge-income' : 'badge-expense' ?>">
                                <?= $r['type'] === 'income' ? '💵 Virement' : '💸 Prélèvement' ?>
                            </span>
                        </td>
                        <td><strong><?= htmlspecialchars($r['title']) ?></strong>
                            <?php if ($r['notes']): ?>
                                <br><small style="color:var(--text-muted)"><?= htmlspecialchars(mb_substr($r['notes'],0,40)) ?></small>
                            <?php endif; ?>
                        </td>
                        <td class="<?= $r['type'] === 'income' ? 'amount-income' : 'amount-expense' ?>">
                            <?= $r['type'] === 'income' ? '+' : '-' ?><?= number_format($r['amount'], 2, ',', ' ') ?> €
                        </td>
                        <td><?= $r['category_icon'] ? htmlspecialchars($r['category_icon']) . ' ' : '' ?><?= htmlspecialchars($r['category_name'] ?? '—') ?></td>
                        <td>
                            <div style="display:flex;align-items:center;gap:.4rem">
                                <?= \App\Core\Avatar::html($r['user_avatar'] ?? null, $r['user_color'], $r['user_name'], 'user-avatar user-avatar-xs') ?>
                                <?= htmlspecialchars($r['user_name']) ?>
                            </div>
                        </td>
                        <td>le <strong><?= $r['day_of_month'] ?></strong></td>
                        <td><?= $r['alert_days_before'] > 0 ? $r['alert_days_before'] . 'j avant' : '—' ?></td>
                        <td>
                            <button class="btn-icon-sm" onclick="openEditRecurringModal(<?= htmlspecialchars(json_encode($r)) ?>)" title="Modifier">✏️</button>
                            <button class="btn-icon-sm" onclick="deleteRecurring(<?= $r['id'] ?>)" title="Supprimer">🗑</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <div class="budget-main-grid">
        <!-- Transactions -->
        <div class="card">
            <div class="card-header">
                <h3>Transactions du mois</h3>
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

        <!-- Right column -->
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
                                <small>Échéance: <?= \App\Core\DateHelper::long($goal['deadline']) ?></small>
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

            <!-- Chart: fixed vs variable by category -->
            <div class="card" style="margin-top:1rem">
                <div class="card-header">
                    <h3>📊 Graphique par catégorie</h3>
                    <div style="display:flex;gap:.4rem">
                        <button class="btn btn-sm btn-secondary" id="chart-btn-expense" onclick="setChartType('expense')">Dépenses</button>
                        <button class="btn btn-sm btn-secondary" id="chart-btn-income"  onclick="setChartType('income')">Revenus</button>
                    </div>
                </div>
                <div style="position:relative;min-height:220px">
                    <canvas id="budget-chart"></canvas>
                </div>
                <div style="display:flex;gap:1rem;justify-content:center;margin-top:.5rem;font-size:.78rem;color:var(--text-muted)">
                    <span><span style="display:inline-block;width:10px;height:10px;border-radius:2px;background:#4A90D9;margin-right:4px"></span>Fixes (récurrents)</span>
                    <span><span style="display:inline-block;width:10px;height:10px;border-radius:2px;background:#F39C12;margin-right:4px"></span>Variables (transactions)</span>
                </div>
            </div>

            <!-- Category breakdown (text) -->
            <div class="card" style="margin-top:1rem">
                <div class="card-header">
                    <h3>Par catégorie</h3>
                    <button class="btn btn-sm btn-secondary" onclick="openCategoryModal()">⚙️</button>
                </div>
                <?php foreach ($breakdown as $cat): ?>
                    <div class="cat-bar">
                        <div class="cat-label">
                            <span><?= htmlspecialchars($cat['icon'] ?? '💰') ?></span>
                            <span><?= htmlspecialchars($cat['name'] ?? 'Sans catégorie') ?></span>
                            <span class="<?= $cat['type'] === 'income' ? 'amount-income' : 'amount-expense' ?>"><?= number_format($cat['total'], 2, ',', ' ') ?> €</span>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($breakdown)): ?><p class="empty-state">Aucune donnée.</p><?php endif; ?>
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
                <label class="radio-option"><input type="radio" name="tx-type-r" id="tx-type-expense" value="expense" checked> 💸 Dépense</label>
                <label class="radio-option"><input type="radio" name="tx-type-r" id="tx-type-income"  value="income">  💵 Revenu</label>
            </div>
            <div class="form-group"><label>Titre</label><input type="text" id="tx-title" placeholder="Courses, salaire…" onblur="suggestTxCategory()"></div>
            <div class="form-row">
                <div class="form-group"><label>Montant (€)</label><input type="number" id="tx-amount" step="0.01" min="0"></div>
                <div class="form-group"><label>Date</label><input type="date" id="tx-date" value="<?= date('Y-m-d') ?>"></div>
            </div>
            <div class="form-group">
                <label>Catégorie <small id="tx-category-hint" style="color:var(--text-muted)"></small></label>
                <select id="tx-category">
                    <option value="">Sans catégorie</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['icon']) ?> <?= htmlspecialchars($cat['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group"><label>Notes</label><textarea id="tx-notes" rows="2"></textarea></div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('transaction-modal')">Annuler</button>
            <button class="btn btn-primary" onclick="saveTransaction()">Enregistrer</button>
        </div>
    </div>
</div>

<!-- Recurring Modal -->
<div class="modal-overlay" id="recurring-modal" style="display:none">
    <div class="modal">
        <div class="modal-header">
            <h3 id="recurring-modal-title">Nouvelle opération récurrente</h3>
            <button onclick="closeModal('recurring-modal')">✕</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="rec-id">
            <div class="form-row">
                <label class="radio-option"><input type="radio" name="rec-type-r" id="rec-type-expense" value="expense" checked> 💸 Prélèvement</label>
                <label class="radio-option"><input type="radio" name="rec-type-r" id="rec-type-income"  value="income">  💵 Virement</label>
            </div>
            <div class="form-group"><label>Titre</label><input type="text" id="rec-title" placeholder="Loyer, salaire, abonnement…"></div>
            <div class="form-row">
                <div class="form-group"><label>Montant (€)</label><input type="number" id="rec-amount" step="0.01" min="0"></div>
                <div class="form-group">
                    <label>Jour du mois</label>
                    <input type="number" id="rec-day" min="1" max="28" value="1">
                    <small style="color:var(--text-muted)">Entre 1 et 28</small>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Catégorie</label>
                    <select id="rec-category">
                        <option value="">Sans catégorie</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['icon']) ?> <?= htmlspecialchars($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Membre concerné</label>
                    <select id="rec-user">
                        <?php foreach ($members as $m): ?>
                            <option value="<?= $m['id'] ?>" <?= $m['id'] === $user['id'] ? 'selected' : '' ?>><?= htmlspecialchars($m['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>Alerte email (jours avant)</label>
                <select id="rec-alert">
                    <option value="0">Aucune alerte</option>
                    <option value="1">1 jour avant</option>
                    <option value="2">2 jours avant</option>
                    <option value="3" selected>3 jours avant</option>
                    <option value="5">5 jours avant</option>
                    <option value="7">7 jours avant</option>
                </select>
            </div>
            <div class="form-group"><label>Notes</label><textarea id="rec-notes" rows="2" placeholder="Optionnel"></textarea></div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('recurring-modal')">Annuler</button>
            <button class="btn btn-primary" onclick="saveRecurring()">Enregistrer</button>
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
            <div class="form-group"><label>Nom</label><input type="text" id="goal-name" placeholder="Vacances, voiture…"></div>
            <div class="form-row">
                <div class="form-group"><label>Objectif (€)</label><input type="number" id="goal-target" step="0.01" placeholder="5000"></div>
                <div class="form-group"><label>Épargné (€)</label><input type="number" id="goal-current" step="0.01" placeholder="0"></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Échéance</label><input type="date" id="goal-deadline"></div>
                <div class="form-group"><label>Couleur</label><input type="color" id="goal-color" value="#4A90D9"></div>
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

<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
<script>
const CURRENT_MONTH = <?= json_encode($month) ?>;
const BUDGET_MEMBERS = <?= json_encode(array_map(fn($m) => ['id'=>$m['id'],'name'=>$m['name']], $members)) ?>;
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
