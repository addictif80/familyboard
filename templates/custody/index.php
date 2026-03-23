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
                    <?php if ($schedule['recurrence_type'] !== 'none'): ?>
                        <span class="recurrence-badge" title="Périodicité active">🔄</span>
                    <?php endif; ?>
                    <button onclick="openEditScheduleModal(<?= htmlspecialchars(json_encode($schedule)) ?>)" class="btn-chip">✏️</button>
                    <button onclick="deleteSchedule(<?= $schedule['id'] ?>)" class="btn-chip">✕</button>
                </span>
            <?php endforeach; ?>
        </div>
        <div class="custody-actions">
            <button class="btn btn-secondary btn-sm" onclick="openScheduleModal()">+ Enfant</button>
            <button class="btn btn-primary btn-sm" onclick="openCustodyEventModal()">+ Exception de garde</button>
        </div>
    </div>

    <div id="custody-calendar"></div>
    <div id="custody-mobile-agenda" class="cal-agenda" style="display:none"></div>

    <!-- Legend -->
    <div class="custody-legend">
        <?php foreach ($members as $m): ?>
            <span class="legend-item">
                <span class="legend-dot" style="background:<?= htmlspecialchars($m['color']) ?>"></span>
                <?= htmlspecialchars($m['name']) ?>
            </span>
        <?php endforeach; ?>
        <span class="legend-item">
            <span class="legend-dot" style="background:#ccc;border:2px dashed #999"></span>
            Événement récurrent
        </span>
    </div>
</div>

<!-- Schedule Modal -->
<div class="modal-overlay" id="schedule-modal" style="display:none">
    <div class="modal">
        <div class="modal-header">
            <h3 id="schedule-modal-title">Ajouter un enfant</h3>
            <button onclick="closeModal('schedule-modal')">✕</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="schedule-id">

            <div class="form-row">
                <div class="form-group flex-2">
                    <label>Prénom de l'enfant</label>
                    <input type="text" id="schedule-child-name" placeholder="Emma, Lucas…">
                </div>
                <div class="form-group">
                    <label>Couleur</label>
                    <input type="color" id="schedule-color" value="#E67E22">
                </div>
            </div>

            <hr class="divider">
            <p class="section-label">Périodicité automatique</p>

            <div class="form-group">
                <label>Type de récurrence</label>
                <select id="schedule-recurrence-type" onchange="toggleRecurrenceFields(this.value)">
                    <option value="none">Aucune (saisie manuelle)</option>
                    <option value="every_other_day">1 jour sur 2</option>
                    <option value="every_other_week">1 semaine sur 2</option>
                    <option value="every_2weeks">2 semaines sur 2</option>
                    <option value="every_month">1 mois sur 2</option>
                </select>
            </div>

            <div id="recurrence-fields" style="display:none">
                <div class="form-group">
                    <label>Date de début de la périodicité</label>
                    <input type="date" id="schedule-recurrence-start">
                    <small class="field-hint">Premier jour où le parent 1 prend la garde</small>
                </div>

                <!-- Parent 1 -->
                <div class="parent-block">
                    <div class="parent-block-header">
                        <strong>Parent 1</strong> <small>(commence en premier)</small>
                        <select class="fill-from-member btn-text" onchange="fillParentFromMember(1, this)" title="Remplir depuis un membre">
                            <option value="">Remplir depuis un membre…</option>
                            <?php foreach ($members as $m): ?>
                                <option value="<?= $m['id'] ?>" data-name="<?= htmlspecialchars($m['name']) ?>" data-color="<?= htmlspecialchars($m['color']) ?>"><?= htmlspecialchars($m['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-row">
                        <div class="form-group flex-2">
                            <label>Nom</label>
                            <input type="text" id="schedule-parent1-label" placeholder="Ex: Marie, Papa…">
                        </div>
                        <div class="form-group">
                            <label>Couleur</label>
                            <input type="color" id="schedule-parent1-color" value="#4A90D9">
                        </div>
                    </div>
                    <input type="hidden" id="schedule-parent1-id">
                </div>

                <!-- Parent 2 -->
                <div class="parent-block">
                    <div class="parent-block-header">
                        <strong>Parent 2</strong>
                        <select class="fill-from-member btn-text" onchange="fillParentFromMember(2, this)" title="Remplir depuis un membre">
                            <option value="">Remplir depuis un membre…</option>
                            <?php foreach ($members as $m): ?>
                                <option value="<?= $m['id'] ?>" data-name="<?= htmlspecialchars($m['name']) ?>" data-color="<?= htmlspecialchars($m['color']) ?>"><?= htmlspecialchars($m['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-row">
                        <div class="form-group flex-2">
                            <label>Nom</label>
                            <input type="text" id="schedule-parent2-label" placeholder="Ex: Jean, Maman…">
                        </div>
                        <div class="form-group">
                            <label>Couleur</label>
                            <input type="color" id="schedule-parent2-color" value="#E74C3C">
                        </div>
                    </div>
                    <input type="hidden" id="schedule-parent2-id">
                </div>

                <div class="recurrence-info">
                    💡 Les périodes récurrentes s'affichent automatiquement. Utilisez <strong>"+ Exception de garde"</strong> pour modifier une période spécifique (vacances, échange ponctuel…).
                </div>
            </div>

            <div class="form-group" style="margin-top:.75rem">
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

<!-- Custody Event Modal (exceptions / manual) -->
<div class="modal-overlay" id="custody-event-modal" style="display:none">
    <div class="modal">
        <div class="modal-header">
            <h3 id="custody-event-modal-title">Exception / période manuelle</h3>
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
                <textarea id="custody-notes" rows="2" placeholder="Vacances, échange de week-end…"></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('custody-event-modal')">Annuler</button>
            <button class="btn btn-danger" id="custody-event-delete-btn" style="display:none" onclick="deleteCustodyEvent()">Supprimer</button>
            <button class="btn btn-primary" onclick="saveCustodyEvent()">Enregistrer</button>
        </div>
    </div>
</div>

<style>
.recurrence-info { background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 8px; padding: .65rem .9rem; font-size: .8rem; color: #1e40af; margin-top: .5rem; }
.field-hint { font-size: .75rem; color: var(--text-muted); margin-top: .2rem; display:block; }
.recurrence-badge { font-size: .8rem; }
.parent-block { background: var(--bg); border-radius: 8px; padding: .75rem; margin-bottom: .75rem; }
.parent-block-header { display: flex; align-items: center; gap: .75rem; margin-bottom: .5rem; font-size: .85rem; }
.parent-block-header small { color: var(--text-muted); flex:1; }
.fill-from-member { font-size: .75rem; border: 1px solid var(--border); border-radius: 6px; padding: .2rem .4rem; background: white; cursor: pointer; }
</style>

<script>
const SCHEDULES = <?= json_encode($schedules) ?>;

function toggleRecurrenceFields(value) {
    document.getElementById('recurrence-fields').style.display = value === 'none' ? 'none' : 'block';
}
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
