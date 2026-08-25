<?php
$pageTitle = 'Tâches & Courses';
$extraJs = ['vendor/qrcode.min.js', 'tasks.js'];
ob_start();
?>
<div class="tasks-container">
    <div class="tasks-sidebar">
        <div class="tasks-sidebar-header">
            <h3>Listes</h3>
            <button class="btn-icon" onclick="openNewListModal()" title="Nouvelle liste">+</button>
        </div>
        <ul class="lists-menu">
            <?php foreach ($lists as $list): ?>
                <li class="list-item <?= $selectedListId === $list['id'] ? 'active' : '' ?>">
                    <a href="<?= BASE_URL ?>/tasks?list=<?= $list['id'] ?>" class="list-link">
                        <span class="list-dot" style="background:<?= htmlspecialchars($list['color']) ?>"></span>
                        <span class="list-name"><?= htmlspecialchars($list['name']) ?></span>
                        <span class="list-badge <?= $list['type'] === 'shopping' ? 'badge-shopping' : 'badge-task' ?>">
                            <?= $list['type'] === 'shopping' ? '🛒' : '✅' ?>
                        </span>
                        <?php if ($list['pending_count'] > 0): ?>
                            <span class="list-count"><?= $list['pending_count'] ?></span>
                        <?php endif; ?>
                    </a>
                    <?php if ($list['user_id'] === $user['id'] || $user['role'] === 'admin'): ?>
                        <form method="POST" action="<?= BASE_URL ?>/tasks/list/<?= $list['id'] ?>/delete" onsubmit="return confirmSubmit(this,'Supprimer cette liste ?')"><?= \App\Core\Csrf::field() ?>
                            <button type="submit" class="btn-icon-sm">🗑</button>
                        </form>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>

    <div class="tasks-main">
        <?php if ($selectedList): ?>
            <div class="tasks-header">
                <div style="display:flex;align-items:center;gap:.5rem">
                    <span class="list-dot-lg" style="background:<?= htmlspecialchars($selectedList['color']) ?>"></span>
                    <h2><?= htmlspecialchars($selectedList['name']) ?></h2>
                    <span class="badge"><?= $selectedList['type'] === 'shopping' ? '🛒 Courses' : '✅ Tâches' ?></span>
                </div>
                <div style="display:flex;gap:.4rem;flex-wrap:wrap">
                    <a class="btn btn-secondary btn-sm" href="<?= BASE_URL ?>/tasks/list/<?= $selectedListId ?>/pdf">📄 PDF</a>
                    <button class="btn btn-secondary btn-sm" onclick="openShareModal(<?= $selectedListId ?>)">🔗 Partager</button>
                    <button class="btn btn-primary btn-sm" onclick="openNewTaskModal()">+ Ajouter</button>
                </div>
            </div>

            <?php if ($selectedList['user_id'] === $user['id'] || $user['role'] === 'admin'): ?>
            <div class="list-event-link">
                <?php if (!empty($selectedList['linked_event_id'])): ?>
                    <span>🔗 Réinitialisée à chaque nouvelle occasion liée à
                        <strong><?= htmlspecialchars($selectedList['linked_event_title']) ?></strong>
                        (<?= date('d/m/Y', strtotime($selectedList['linked_event_start'])) ?>)</span>
                    <button type="button" class="btn-text" onclick="unlinkListEvent()">Délier</button>
                <?php else: ?>
                    <button type="button" class="btn-text" onclick="openLinkEventModal()">🔗 Lier à un événement (réinitialisation auto à chaque nouvelle occasion)</button>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <ul class="task-list" id="task-list">
                <?php foreach ($tasks as $task): ?>
                    <li class="task-item <?= $task['is_completed'] ? 'completed' : '' ?>" data-task-id="<?= $task['id'] ?>">
                        <button class="task-check" onclick="toggleTask(<?= $task['id'] ?>)">
                            <?= $task['is_completed'] ? '✅' : '⬜' ?>
                        </button>
                        <div class="task-body" onclick="openEditTaskModal(<?= htmlspecialchars(json_encode($task)) ?>)">
                            <span class="task-title"><?= htmlspecialchars($task['title']) ?></span>
                            <div class="task-meta">
                                <?php if ($task['assigned_name']): ?>
                                    <span class="tag" style="background:<?= htmlspecialchars($task['assigned_color']) ?>20;color:<?= htmlspecialchars($task['assigned_color']) ?>"><?= \App\Core\Avatar::html($task['assigned_avatar'], $task['assigned_color'], $task['assigned_name'], 'user-avatar-xs') ?><?= htmlspecialchars($task['assigned_name']) ?></span>
                                <?php endif; ?>
                                <?php if ($task['due_date']): ?>
                                    <span class="tag <?= $task['due_date'] < date('Y-m-d') && !$task['is_completed'] ? 'tag-overdue' : '' ?>">
                                        📅 <?= date('d/m', strtotime($task['due_date'])) ?>
                                    </span>
                                <?php endif; ?>
                                <span class="tag tag-<?= $task['priority'] ?>"><?= $task['priority'] === 'high' ? '🔴' : ($task['priority'] === 'medium' ? '🟡' : '🟢') ?></span>
                            </div>
                        </div>
                        <button class="btn-icon-sm" onclick="deleteTask(<?= $task['id'] ?>)">🗑</button>
                    </li>
                <?php endforeach; ?>
                <?php if (empty($tasks)): ?>
                    <li class="empty-state">Aucune tâche. Cliquez sur "+ Ajouter" pour commencer.</li>
                <?php endif; ?>
            </ul>
        <?php else: ?>
            <div class="empty-state-card">
                <p>Créez une liste pour commencer.</p>
                <button class="btn btn-primary" onclick="openNewListModal()">+ Nouvelle liste</button>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- New List Modal -->
<div class="modal-overlay" id="new-list-modal" style="display:none">
    <div class="modal modal-sm">
        <div class="modal-header">
            <h3>Nouvelle liste</h3>
            <button onclick="closeModal('new-list-modal')">✕</button>
        </div>
        <form method="POST" action="<?= BASE_URL ?>/tasks/list"><?= \App\Core\Csrf::field() ?>
            <div class="modal-body">
                <div class="form-group">
                    <label>Nom</label>
                    <input type="text" name="name" required placeholder="Courses du samedi">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Type</label>
                        <select name="type">
                            <option value="tasks">✅ Tâches</option>
                            <option value="shopping">🛒 Courses</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Couleur</label>
                        <input type="color" name="color" value="#4A90D9">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('new-list-modal')">Annuler</button>
                <button type="submit" class="btn btn-primary">Créer</button>
            </div>
        </form>
    </div>
</div>

<!-- Link to event Modal -->
<div class="modal-overlay" id="link-event-modal" style="display:none">
    <div class="modal modal-sm">
        <div class="modal-header">
            <h3>Lier la liste à un événement</h3>
            <button onclick="closeModal('link-event-modal')">✕</button>
        </div>
        <div class="modal-body">
            <p style="color:var(--text-muted);font-size:.85rem">
                À chaque fois que vous rattachez cette liste à un nouvel événement (ex. l'édition
                de l'an prochain de « Vacances d'été »), les cases sont automatiquement décochées —
                pratique pour une checklist réutilisée d'une occasion sur l'autre.
            </p>
            <div class="form-group">
                <label>Événement</label>
                <select id="link-event-select">
                    <option value="">— Choisir —</option>
                    <?php foreach ($selectableEvents as $ev): ?>
                        <option value="<?= $ev['id'] ?>"><?= htmlspecialchars($ev['title']) ?> — <?= date('d/m/Y', strtotime($ev['start_datetime'])) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('link-event-modal')">Annuler</button>
            <button type="button" class="btn btn-primary" onclick="linkListEvent()">Lier</button>
        </div>
    </div>
</div>

<!-- Task Modal -->
<div class="modal-overlay" id="task-modal" style="display:none">
    <div class="modal">
        <div class="modal-header">
            <h3 id="task-modal-title">Nouvelle tâche</h3>
            <button onclick="closeModal('task-modal')">✕</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="task-id">
            <div class="form-group">
                <label>Titre *</label>
                <input type="text" id="task-title" required placeholder="Acheter du lait…">
            </div>
            <div class="form-group">
                <label>Notes</label>
                <textarea id="task-notes" rows="2"></textarea>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Assigné à</label>
                    <select id="task-assigned">
                        <option value="">— Personne —</option>
                        <?php foreach ($members as $m): ?>
                            <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Priorité</label>
                    <select id="task-priority">
                        <option value="low">🟢 Basse</option>
                        <option value="medium" selected>🟡 Moyenne</option>
                        <option value="high">🔴 Haute</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>Échéance</label>
                <input type="date" id="task-due">
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('task-modal')">Annuler</button>
            <button class="btn btn-primary" onclick="saveTask()">Enregistrer</button>
        </div>
    </div>
</div>

<!-- Share Modal -->
<div class="modal-overlay" id="share-list-modal" style="display:none">
    <div class="modal">
        <div class="modal-header">
            <h3>Partager la liste</h3>
            <button onclick="closeModal('share-list-modal')">✕</button>
        </div>
        <div class="modal-body" style="text-align:center">
            <p style="color:var(--text-muted);font-size:.85rem;margin-bottom:1rem">
                Toute personne avec ce lien peut cocher/décocher les éléments de cette liste,
                sans avoir besoin de compte — pratique à envoyer à un proche qui fait les courses.
            </p>
            <div id="share-qr-container" style="display:inline-block;margin-bottom:1rem"></div>
            <p style="word-break:break-all;font-size:.8rem" id="share-link-text"></p>
            <div style="display:flex;gap:.5rem;justify-content:center;flex-wrap:wrap;margin-bottom:1rem">
                <button type="button" class="btn btn-secondary btn-sm" id="share-copy-btn">📋 Copier le lien</button>
                <a href="#" target="_blank" class="btn btn-secondary btn-sm" id="share-open-link">Ouvrir</a>
            </div>
            <div style="display:flex;gap:.5rem;justify-content:center">
                <button type="button" class="btn btn-secondary btn-sm" onclick="regenerateShareLink()">🔄 Régénérer</button>
                <button type="button" class="btn btn-danger btn-sm" onclick="revokeShareLink()">Révoquer</button>
            </div>
        </div>
    </div>
</div>

<script>
const LIST_ID = <?= json_encode($selectedListId) ?>;
const BASE_URL = <?= json_encode(BASE_URL) ?>;
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
