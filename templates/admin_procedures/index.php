<?php
$pageTitle = 'Démarches administratives';
$extraJs = ['admin_procedures.js'];
ob_start();

use App\Models\AdminProcedure;

$today = date('Y-m-d');
$soon = date('Y-m-d', strtotime('+30 days'));
$subjectLabel = function ($type, $id) use ($subjects) {
    foreach ($subjects as $s) {
        if ($s['type'] === $type && (int)$s['id'] === (int)$id) return $s['name'];
    }
    return '—';
};
?>
<div class="tasks-main" style="width:100%">
    <div class="tasks-header">
        <h2>🪪 Démarches administratives</h2>
        <?php if (!empty($subjects)): ?>
        <button class="btn btn-primary btn-sm" onclick="openNewProcedureModal()">+ Nouvelle démarche</button>
        <?php endif; ?>
    </div>

    <div class="card settings-section">
        <?php if (empty($subjects)): ?>
            <p class="empty-state">Ajoutez d'abord un enfant (Réglages → Famille) ou un membre.</p>
        <?php else: ?>
        <table class="admin-table">
            <thead><tr><th></th><th>Échéance</th><th>Concerne</th><th>Type</th><th>Titre</th><th>Notes</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($procedures as $p): ?>
                <?php
                    $t = AdminProcedure::TYPES[$p['procedure_type']];
                    $style = '';
                    if (!$p['done']) {
                        if ($p['deadline_date'] < $today) $style = 'color:var(--danger);font-weight:600';
                        elseif ($p['deadline_date'] <= $soon) $style = 'color:#E67E22;font-weight:600';
                    }
                ?>
                <tr style="<?= $p['done'] ? 'opacity:.5' : '' ?>">
                    <td><input type="checkbox" <?= $p['done'] ? 'checked' : '' ?> onchange="toggleProcedureDone(<?= $p['id'] ?>, this.checked)"></td>
                    <td style="<?= $style ?>"><?= (new DateTime($p['deadline_date']))->format('d/m/Y') ?></td>
                    <td><?= htmlspecialchars($subjectLabel($p['subject_type'], $p['subject_id'])) ?></td>
                    <td><?= $t['icon'] ?> <?= htmlspecialchars($t['label']) ?></td>
                    <td><?= htmlspecialchars($p['title']) ?></td>
                    <td><?= htmlspecialchars($p['notes'] ?? '') ?></td>
                    <td><button class="btn-icon" title="Supprimer" onclick="deleteProcedure(<?= $p['id'] ?>)">🗑</button></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($procedures)): ?>
                <tr><td colspan="7" class="empty-state">Aucune démarche enregistrée.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<!-- Nouvelle démarche -->
<div class="modal-overlay" id="procedure-modal" style="display:none">
    <div class="modal">
        <div class="modal-header">
            <h3>Nouvelle démarche</h3>
            <button onclick="closeModal('procedure-modal')">✕</button>
        </div>
        <div class="modal-body">
            <div class="form-row">
                <div class="form-group">
                    <label>Concerne</label>
                    <select id="proc-subject">
                        <?php foreach ($subjects as $s): ?>
                            <option value="<?= $s['type'] ?>:<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Type</label>
                    <select id="proc-type">
                        <?php foreach (AdminProcedure::TYPES as $slug => $t): ?>
                            <option value="<?= $slug ?>"><?= $t['icon'] ?> <?= htmlspecialchars($t['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group flex-2">
                    <label>Titre</label>
                    <input type="text" id="proc-title" placeholder="Renouvellement CNI">
                </div>
                <div class="form-group">
                    <label>Échéance</label>
                    <input type="date" id="proc-deadline">
                </div>
            </div>
            <div class="form-group">
                <label>Notes</label>
                <input type="text" id="proc-notes">
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('procedure-modal')">Annuler</button>
            <button class="btn btn-primary" onclick="saveProcedure()">Enregistrer</button>
        </div>
    </div>
</div>

<script>
const BASE_URL = <?= json_encode(BASE_URL) ?>;
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
