<?php
$pageTitle = htmlspecialchars($project['name']);
$extraJs = ['projects.js'];
ob_start();
?>
<div class="project-detail">
    <div class="project-detail-header" style="border-left:4px solid <?= htmlspecialchars($project['color']) ?>">
        <div>
            <h2><?= htmlspecialchars($project['name']) ?></h2>
            <?php if ($project['description']): ?>
                <p><?= nl2br(htmlspecialchars($project['description'])) ?></p>
            <?php endif; ?>
            <div class="project-meta-tags">
                <span class="badge badge-<?= $project['status'] ?>"><?= match($project['status']) { 'active' => '🟢 Actif', 'completed' => '✅ Terminé', 'archived' => '📦 Archivé' } ?></span>
                <?php if ($project['deadline']): ?>
                    <span class="badge">📅 <?= date('d/m/Y', strtotime($project['deadline'])) ?></span>
                <?php endif; ?>
                <?php if ($project['budget']): ?>
                    <span class="badge">💰 Budget: <?= number_format($project['budget'], 2, ',', ' ') ?> €</span>
                    <?php $remaining = $project['budget'] - $project['spent']; ?>
                    <span class="badge <?= $remaining < 0 ? 'badge-danger' : 'badge-ok' ?>">
                        Dépensé: <?= number_format($project['spent'], 2, ',', ' ') ?> € (<?= $remaining >= 0 ? number_format($remaining, 2, ',', ' ') . ' € restants' : number_format(abs($remaining), 2, ',', ' ') . ' € dépassement' ?>)
                    </span>
                <?php endif; ?>
            </div>
        </div>
        <div class="project-actions">
            <button class="btn btn-secondary" onclick="openEditProjectModal(<?= htmlspecialchars(json_encode($project)) ?>)">✏️ Modifier</button>
            <button class="btn btn-danger" onclick="deleteProject(<?= $project['id'] ?>)">🗑 Supprimer</button>
        </div>
    </div>

    <?php if ($project['budget']): ?>
    <div class="budget-progress">
        <?php $pct = min(100, round($project['spent'] / $project['budget'] * 100)); ?>
        <div class="progress-bar">
            <div class="progress-fill <?= $pct >= 100 ? 'progress-over' : '' ?>" style="width:<?= $pct ?>%;background:<?= htmlspecialchars($project['color']) ?>"></div>
        </div>
        <small><?= $pct ?>% du budget utilisé</small>
    </div>
    <?php endif; ?>

    <div class="project-detail-grid">
        <!-- Tasks -->
        <div class="card">
            <div class="card-header">
                <h3>Tâches</h3>
                <button class="btn btn-primary btn-sm" onclick="openTaskModal()">+ Tâche</button>
            </div>
            <?php
            $todo = array_filter($tasks, fn($t) => $t['status'] === 'todo');
            $inProgress = array_filter($tasks, fn($t) => $t['status'] === 'in_progress');
            $done = array_filter($tasks, fn($t) => $t['status'] === 'done');
            ?>
            <?php foreach ([['À faire', $todo, 'todo'], ['En cours', $inProgress, 'in_progress'], ['Terminé', $done, 'done']] as [$label, $group, $status]): ?>
                <?php if (!empty($group)): ?>
                <div class="kanban-column">
                    <h4 class="kanban-title kanban-<?= $status ?>"><?= $label ?></h4>
                    <?php foreach ($group as $task): ?>
                        <div class="project-task-item" data-id="<?= $task['id'] ?>">
                            <div class="task-body" onclick="openEditProjectTask(<?= htmlspecialchars(json_encode($task)) ?>)">
                                <span><?= htmlspecialchars($task['title']) ?></span>
                                <?php if ($task['assigned_name']): ?>
                                    <span class="tag" style="background:<?= htmlspecialchars($task['assigned_color']) ?>20;color:<?= htmlspecialchars($task['assigned_color']) ?>"><?= htmlspecialchars($task['assigned_name']) ?></span>
                                <?php endif; ?>
                            </div>
                            <button class="btn-icon-sm" onclick="deleteProjectTask(<?= $task['id'] ?>)">🗑</button>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            <?php endforeach; ?>
            <?php if (empty($tasks)): ?>
                <p class="empty-state">Aucune tâche.</p>
            <?php endif; ?>
        </div>

        <!-- Expenses -->
        <div class="card">
            <div class="card-header">
                <h3>Dépenses du projet</h3>
                <button class="btn btn-secondary btn-sm" onclick="openExpenseModal()">+ Dépense</button>
            </div>
            <div class="expenses-list" id="expenses-list">
                <?php foreach ($expenses as $exp): ?>
                    <div class="expense-item" data-id="<?= $exp['id'] ?>">
                        <div>
                            <strong><?= htmlspecialchars($exp['title']) ?></strong>
                            <small><?= date('d/m/Y', strtotime($exp['date'])) ?> · <?= htmlspecialchars($exp['user_name']) ?></small>
                        </div>
                        <span class="tx-amount amount-expense">-<?= number_format($exp['amount'], 2, ',', ' ') ?> €</span>
                        <button class="btn-icon-sm" onclick="deleteExpense(<?= $exp['id'] ?>)">🗑</button>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($expenses)): ?>
                    <p class="empty-state">Aucune dépense enregistrée.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Task Modal -->
<div class="modal-overlay" id="project-task-modal" style="display:none">
    <div class="modal">
        <div class="modal-header">
            <h3 id="ptask-modal-title">Nouvelle tâche</h3>
            <button onclick="closeModal('project-task-modal')">✕</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="ptask-id">
            <div class="form-group">
                <label>Titre *</label>
                <input type="text" id="ptask-title">
            </div>
            <div class="form-group">
                <label>Description</label>
                <textarea id="ptask-desc" rows="2"></textarea>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Statut</label>
                    <select id="ptask-status">
                        <option value="todo">À faire</option>
                        <option value="in_progress">En cours</option>
                        <option value="done">Terminé</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Priorité</label>
                    <select id="ptask-priority">
                        <option value="low">🟢 Basse</option>
                        <option value="medium" selected>🟡 Moyenne</option>
                        <option value="high">🔴 Haute</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>Assigné à</label>
                <select id="ptask-assigned">
                    <option value="">— Personne —</option>
                    <?php foreach ($members as $m): ?>
                        <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('project-task-modal')">Annuler</button>
            <button class="btn btn-primary" onclick="saveProjectTask()">Enregistrer</button>
        </div>
    </div>
</div>

<!-- Expense Modal -->
<div class="modal-overlay" id="expense-modal" style="display:none">
    <div class="modal modal-sm">
        <div class="modal-header">
            <h3>Nouvelle dépense</h3>
            <button onclick="closeModal('expense-modal')">✕</button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label>Description</label>
                <input type="text" id="exp-title" placeholder="Achat de matériaux…">
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Montant (€)</label>
                    <input type="number" id="exp-amount" step="0.01" placeholder="0.00">
                </div>
                <div class="form-group">
                    <label>Date</label>
                    <input type="date" id="exp-date" value="<?= date('Y-m-d') ?>">
                </div>
            </div>
            <div class="form-group">
                <label>Notes</label>
                <textarea id="exp-notes" rows="2"></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('expense-modal')">Annuler</button>
            <button class="btn btn-primary" onclick="saveExpense()">Enregistrer</button>
        </div>
    </div>
</div>

<!-- Edit project modal -->
<div class="modal-overlay" id="edit-project-modal" style="display:none">
    <div class="modal">
        <div class="modal-header">
            <h3>Modifier le projet</h3>
            <button onclick="closeModal('edit-project-modal')">✕</button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label>Nom</label>
                <input type="text" id="edit-proj-name">
            </div>
            <div class="form-group">
                <label>Description</label>
                <textarea id="edit-proj-desc" rows="3"></textarea>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Statut</label>
                    <select id="edit-proj-status">
                        <option value="active">🟢 Actif</option>
                        <option value="completed">✅ Terminé</option>
                        <option value="archived">📦 Archivé</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Couleur</label>
                    <input type="color" id="edit-proj-color">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Budget (€)</label>
                    <input type="number" id="edit-proj-budget" step="0.01">
                </div>
                <div class="form-group">
                    <label>Échéance</label>
                    <input type="date" id="edit-proj-deadline">
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('edit-project-modal')">Annuler</button>
            <button class="btn btn-primary" onclick="updateProject()">Enregistrer</button>
        </div>
    </div>
</div>

<script>
const BASE_URL = <?= json_encode(BASE_URL) ?>;
const PROJECT_ID = <?= json_encode($project['id']) ?>;
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
