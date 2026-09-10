<?php
$pageTitle = 'Congés familiaux';
$extraJs = ['leave.js'];
ob_start();

use App\Models\LeaveRequest;
?>
<div class="tasks-main" style="width:100%">
    <div class="tasks-header">
        <h2>🏖️ Congés familiaux</h2>
        <button class="btn btn-primary btn-sm" onclick="openNewLeaveModal()">+ Poser un congé</button>
    </div>

    <div class="card settings-section">
        <table class="admin-table">
            <thead><tr><th>Membre</th><th>Type</th><th>Du</th><th>Au</th><th>Titre</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($leaves as $l): ?>
                <?php $t = LeaveRequest::TYPES[$l['leave_type']]; ?>
                <tr>
                    <td><span class="list-dot" style="background:<?= htmlspecialchars($l['user_color']) ?>"></span> <?= htmlspecialchars($l['user_name']) ?></td>
                    <td><?= $t['icon'] ?> <?= htmlspecialchars($t['label']) ?></td>
                    <td><?= (new DateTime($l['start_date']))->format('d/m/Y') ?></td>
                    <td><?= (new DateTime($l['end_date']))->format('d/m/Y') ?></td>
                    <td><?= htmlspecialchars($l['title'] ?? '') ?></td>
                    <td><button class="btn-icon" title="Supprimer" onclick="deleteLeave(<?= $l['id'] ?>)">🗑</button></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($leaves)): ?>
                <tr><td colspan="6" class="empty-state">Aucun congé posé.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Poser un congé -->
<div class="modal-overlay" id="leave-modal" style="display:none">
    <div class="modal">
        <div class="modal-header">
            <h3>Poser un congé</h3>
            <button onclick="closeModal('leave-modal')">✕</button>
        </div>
        <div class="modal-body">
            <div class="form-row">
                <div class="form-group">
                    <label>Membre</label>
                    <select id="leave-user">
                        <?php foreach ($members as $m): ?>
                            <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Type</label>
                    <select id="leave-type">
                        <?php foreach (LeaveRequest::TYPES as $slug => $t): ?>
                            <option value="<?= $slug ?>"><?= $t['icon'] ?> <?= htmlspecialchars($t['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Du</label>
                    <input type="date" id="leave-start">
                </div>
                <div class="form-group">
                    <label>Au</label>
                    <input type="date" id="leave-end">
                </div>
            </div>
            <div class="form-group">
                <label>Titre (optionnel)</label>
                <input type="text" id="leave-title" placeholder="Vacances d'été…">
            </div>
            <div class="form-group">
                <label>Notes</label>
                <input type="text" id="leave-notes">
            </div>
            <div id="leave-overlap-warning" style="display:none;color:#E67E22;font-size:.85rem"></div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('leave-modal')">Annuler</button>
            <button class="btn btn-primary" onclick="saveLeave()">Enregistrer</button>
        </div>
    </div>
</div>

<script>
const BASE_URL = <?= json_encode(BASE_URL) ?>;
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
