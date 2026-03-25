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

    <!-- Calendrier du projet -->
    <div class="card" style="margin-top:1rem">
        <div class="card-header">
            <h3>📅 Calendrier du projet</h3>
            <div style="display:flex;gap:.5rem;align-items:center">
                <button onclick="projCalPrev()" class="btn btn-secondary btn-sm">‹</button>
                <span id="proj-cal-label" style="font-weight:600;min-width:160px;text-align:center"></span>
                <button onclick="projCalNext()" class="btn btn-secondary btn-sm">›</button>
                <button onclick="projCalToday()" class="btn btn-secondary btn-sm">Aujourd'hui</button>
            </div>
        </div>
        <div id="proj-calendar" style="padding:.5rem 0"></div>
    </div>

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

    <!-- Materials -->
    <?php
    $matTotal    = array_sum(array_map(fn($m) => ($m['price'] ?? 0) * ($m['quantity'] ?? 1), $materials));
    $matPurchased = count(array_filter($materials, fn($m) => $m['is_purchased']));
    ?>
    <div class="card" style="margin-top:1rem">
        <div class="card-header">
            <h3>🔩 Matériaux à acheter</h3>
            <div style="display:flex;align-items:center;gap:.75rem">
                <?php if (!empty($materials)): ?>
                    <span style="font-size:.8rem;color:var(--text-muted)"><?= $matPurchased ?>/<?= count($materials) ?> acheté<?= count($materials) > 1 ? 's' : '' ?><?php if ($matTotal > 0): ?> · <strong><?= number_format($matTotal, 2, ',', ' ') ?> €</strong> estimé<?php endif; ?></span>
                <?php endif; ?>
                <button class="btn btn-secondary btn-sm" onclick="openMaterialModal()">+ Matériau</button>
            </div>
        </div>

        <?php if (empty($materials)): ?>
            <p class="empty-state">Aucun matériau enregistré. Ajoutez des liens vers des articles à acheter.</p>
        <?php else: ?>
        <div class="materials-list" id="materials-list">
            <?php foreach ($materials as $mat): ?>
            <div class="material-item <?= $mat['is_purchased'] ? 'mat-purchased' : '' ?>" data-id="<?= $mat['id'] ?>">
                <label class="mat-check" title="Marquer comme acheté">
                    <input type="checkbox" onchange="toggleMaterial(<?= $mat['id'] ?>, this)" <?= $mat['is_purchased'] ? 'checked' : '' ?>>
                </label>
                <div class="mat-info">
                    <span class="mat-name">
                        <?php if ($mat['url']): ?>
                            <a href="<?= htmlspecialchars($mat['url']) ?>" target="_blank" rel="noopener" class="mat-link"><?= htmlspecialchars($mat['name']) ?> 🔗</a>
                        <?php else: ?>
                            <?= htmlspecialchars($mat['name']) ?>
                        <?php endif; ?>
                    </span>
                    <?php if ($mat['notes']): ?>
                        <small class="mat-notes"><?= htmlspecialchars($mat['notes']) ?></small>
                    <?php endif; ?>
                </div>
                <span class="mat-qty"><?= rtrim(rtrim(number_format((float)$mat['quantity'], 3, ',', ''), '0'), ',') ?><?= $mat['unit'] ? ' ' . htmlspecialchars($mat['unit']) : '' ?></span>
                <?php if ($mat['price'] !== null): ?>
                    <span class="mat-price"><?= number_format((float)$mat['price'], 2, ',', ' ') ?> €<small>/u</small></span>
                    <span class="mat-total"><?= number_format((float)$mat['price'] * (float)$mat['quantity'], 2, ',', ' ') ?> €</span>
                <?php else: ?>
                    <span class="mat-price" style="color:var(--text-muted)">—</span>
                    <span class="mat-total"></span>
                <?php endif; ?>
                <div class="mat-actions">
                    <button class="btn-icon-sm" onclick="openEditMaterialModal(<?= htmlspecialchars(json_encode($mat)) ?>)" title="Modifier">✏️</button>
                    <button class="btn-icon-sm" onclick="deleteMaterial(<?= $mat['id'] ?>)" title="Supprimer">🗑</button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
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
            <div class="form-row">
                <div class="form-group">
                    <label>Assigné à</label>
                    <select id="ptask-assigned">
                        <option value="">— Personne —</option>
                        <?php foreach ($members as $m): ?>
                            <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Échéance</label>
                    <input type="date" id="ptask-due-date">
                </div>
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

<!-- Material Modal -->
<div class="modal-overlay" id="material-modal" style="display:none">
    <div class="modal">
        <div class="modal-header">
            <h3 id="mat-modal-title">Nouveau matériau</h3>
            <button onclick="closeModal('material-modal')">✕</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="mat-id">
            <div class="form-group">
                <label>Nom *</label>
                <input type="text" id="mat-name" placeholder="Parquet chêne, Vis 5x60, Peinture blanche…">
            </div>
            <div class="form-group">
                <label>Lien (URL)</label>
                <input type="url" id="mat-url" placeholder="https://…">
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Prix unitaire (€)</label>
                    <input type="number" id="mat-price" step="0.01" min="0" placeholder="0.00">
                </div>
                <div class="form-group">
                    <label>Quantité</label>
                    <input type="number" id="mat-qty" step="any" min="0" value="1">
                </div>
                <div class="form-group">
                    <label>Unité</label>
                    <input type="text" id="mat-unit" placeholder="m², pcs, kg…" style="max-width:80px">
                </div>
            </div>
            <div class="form-group">
                <label>Notes</label>
                <textarea id="mat-notes" rows="2" placeholder="Référence, coloris, remarques…"></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('material-modal')">Annuler</button>
            <button class="btn btn-primary" onclick="saveMaterial()">Enregistrer</button>
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
