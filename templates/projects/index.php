<?php
$pageTitle = 'Projets';
$extraJs = ['projects.js'];
ob_start();
?>
<div class="projects-container">
    <div class="projects-header">
        <h2>Projets familiaux</h2>
        <button class="btn btn-primary" onclick="openProjectModal()">+ Nouveau projet</button>
    </div>

    <?php
    $active = array_filter($projects, fn($p) => $p['status'] === 'active');
    $completed = array_filter($projects, fn($p) => $p['status'] === 'completed');
    $archived = array_filter($projects, fn($p) => $p['status'] === 'archived');
    ?>

    <?php foreach ([['Actifs', $active], ['Terminés', $completed], ['Archivés', $archived]] as [$label, $group]): ?>
        <?php if (!empty($group)): ?>
        <h3 class="projects-section-title"><?= $label ?></h3>
        <div class="projects-grid">
            <?php foreach ($group as $p): ?>
                <a href="<?= BASE_URL ?>/projects/<?= $p['id'] ?>" class="project-card">
                    <div class="project-color-bar" style="background:<?= htmlspecialchars($p['color']) ?>"></div>
                    <div class="project-body">
                        <h4><?= htmlspecialchars($p['name']) ?></h4>
                        <?php if ($p['description']): ?>
                            <p><?= htmlspecialchars(mb_substr($p['description'], 0, 80)) ?>…</p>
                        <?php endif; ?>
                        <div class="project-stats">
                            <span>✅ <?= $p['done_count'] ?>/<?= $p['task_count'] ?> tâches</span>
                            <?php if ($p['budget']): ?>
                                <span>💰 <?= number_format($p['spent'], 0, ',', ' ') ?>/<?= number_format($p['budget'], 0, ',', ' ') ?> €</span>
                            <?php endif; ?>
                            <?php if ($p['deadline']): ?>
                                <span>📅 <?= date('d/m/Y', strtotime($p['deadline'])) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    <?php endforeach; ?>

    <?php if (empty($projects)): ?>
        <div class="empty-state-card">
            <p>Aucun projet pour l'instant.</p>
            <button class="btn btn-primary" onclick="openProjectModal()">Créer le premier projet</button>
        </div>
    <?php endif; ?>
</div>

<!-- Project Modal -->
<div class="modal-overlay" id="project-modal" style="display:none">
    <div class="modal">
        <div class="modal-header">
            <h3>Nouveau projet</h3>
            <button onclick="closeModal('project-modal')">✕</button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label>Nom du projet *</label>
                <input type="text" id="proj-name" placeholder="Rénovation cuisine, Voyage…">
            </div>
            <div class="form-group">
                <label>Description</label>
                <textarea id="proj-desc" rows="3"></textarea>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Budget (€)</label>
                    <input type="number" id="proj-budget" step="0.01" placeholder="Optionnel">
                </div>
                <div class="form-group">
                    <label>Échéance</label>
                    <input type="date" id="proj-deadline">
                </div>
            </div>
            <div class="form-group">
                <label>Couleur</label>
                <input type="color" id="proj-color" value="#4A90D9">
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('project-modal')">Annuler</button>
            <button class="btn btn-primary" onclick="createProject()">Créer</button>
        </div>
    </div>
</div>

<script>
const BASE_URL = <?= json_encode(BASE_URL) ?>;
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
