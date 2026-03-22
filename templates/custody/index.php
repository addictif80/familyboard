<?php
$pageTitle = 'Garde alternée';
$extraJs = ['custody.js'];
ob_start();
?>
<div class="custody-container">
    <div class="custody-toolbar">
        <div class="schedule-list">
            <?php foreach ($schedules as $schedule): ?>
                <span class="schedule-chip" style="background:<?= htmlspecialchars($schedule['color']) ?>20;border-color:<?= htmlspecialchars($schedule['color']) ?>">
                    👶 <?= htmlspecialchars($schedule['child_name']) ?>
                    <button onclick="openEditScheduleModal(<?= htmlspecialchars(json_encode($schedule)) ?>)" class="btn-chip">✏️</button>
                    <button onclick="deleteSchedule(<?= $schedule['id'] ?>)" class="btn-chip">✕</button>
                </span>
            <?php endforeach; ?>
        </div>
        <div class="custody-actions">
            <button class="btn btn-secondary btn-sm" onclick="openScheduleModal()">+ Enfant</button>
            <button class="btn btn-primary btn-sm" onclick="openCustodyEventModal()">+ Période de garde</button>
        </div>
    </div>

    <div id="custody-calendar"></div>

    <!-- Legend -->
    <div class="custody-legend">
        <?php foreach ($members as $m): ?>
            <span class="legend-item">
                <span class="legend-dot" style="background:<?= htmlspecialchars($m['color']) ?>"></span>
                <?= htmlspecialchars($m['name']) ?>
            </span>
        <?php endforeach; ?>
    </div>
</div>

<!-- Schedule Modal -->
<div class="modal-overlay" id="schedule-modal" style="display:none">
    <div class="modal modal-sm">
        <div class="modal-header">
            <h3 id="schedule-modal-title">Ajouter un enfant</h3>
            <button onclick="closeModal('schedule-modal')">✕</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="schedule-id">
            <div class="form-group">
                <label>Prénom de l'enfant</label>
                <input type="text" id="schedule-child-name" placeholder="Emma, Lucas…">
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Couleur du planning</label>
                    <input type="color" id="schedule-color" value="#E67E22">
                </div>
            </div>
            <div class="form-group">
                <label>Notes</label>
                <textarea id="schedule-notes" rows="2"></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('schedule-modal')">Annuler</button>
            <button class="btn btn-primary" onclick="saveSchedule()">Enregistrer</button>
        </div>
    </div>
</div>

<!-- Custody Event Modal -->
<div class="modal-overlay" id="custody-event-modal" style="display:none">
    <div class="modal">
        <div class="modal-header">
            <h3 id="custody-event-modal-title">Période de garde</h3>
            <button onclick="closeModal('custody-event-modal')">✕</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="custody-event-id">
            <div class="form-group">
                <label>Enfant</label>
                <select id="custody-schedule-id">
                    <option value="">— Choisir un enfant —</option>
                    <?php foreach ($schedules as $s): ?>
                        <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['child_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Parent gardien</label>
                <select id="custody-parent">
                    <?php foreach ($members as $m): ?>
                        <option value="<?= $m['id'] ?>" style="color:<?= htmlspecialchars($m['color']) ?>"><?= htmlspecialchars($m['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Date d'arrivée</label>
                    <input type="date" id="custody-start">
                </div>
                <div class="form-group">
                    <label>Heure d'arrivée</label>
                    <input type="time" id="custody-arrival-time">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Date de départ</label>
                    <input type="date" id="custody-end">
                </div>
                <div class="form-group">
                    <label>Heure de départ</label>
                    <input type="time" id="custody-departure-time">
                </div>
            </div>
            <div class="form-group">
                <label>Notes</label>
                <textarea id="custody-notes" rows="2"></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('custody-event-modal')">Annuler</button>
            <button class="btn btn-danger" id="custody-event-delete-btn" style="display:none" onclick="deleteCustodyEvent()">Supprimer</button>
            <button class="btn btn-primary" onclick="saveCustodyEvent()">Enregistrer</button>
        </div>
    </div>
</div>

<script>
const BASE_URL = <?= json_encode(BASE_URL) ?>;
const SCHEDULES = <?= json_encode($schedules) ?>;
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
