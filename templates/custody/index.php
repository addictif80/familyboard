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
                    <button onclick="openVacationModal(<?= (int)$schedule['id'] ?>, <?= htmlspecialchars(json_encode($schedule['child_name'])) ?>)" class="btn-chip" title="Périodes de vacances">🏖️</button>
                    <button onclick="openInviteCoparentModal(<?= (int)$schedule['id'] ?>)" class="btn-chip" title="Inviter un co-parent">🔒</button>
                    <button onclick="deleteSchedule(<?= $schedule['id'] ?>)" class="btn-chip">✕</button>
                </span>
            <?php endforeach; ?>
        </div>
        <div class="custody-actions">
            <button class="btn btn-secondary btn-sm" onclick="openScheduleModal()">+ Enfant</button>
            <button class="btn btn-secondary btn-sm" onclick="openProposalModal()">📅 Proposition de garde</button>
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
                    <optgroup label="Weekends uniquement">
                        <option value="weekends_every_2">1 weekend sur 2 (sam-dim alternés)</option>
                        <option value="weekends_every_2_plus_weekday">1 weekend sur 2 + 1 jour fixe en semaine</option>
                        <option value="weekends_monthly">1 weekend par mois (1er sam du mois)</option>
                    </optgroup>
                </select>
            </div>

            <div id="recurrence-fields" style="display:none">
                <div class="form-group">
                    <label>Date de début de la périodicité</label>
                    <input type="date" id="schedule-recurrence-start">
                    <small class="field-hint" id="recurrence-start-hint">Premier jour où le parent 1 prend la garde (pour weekends : choisir un samedi)</small>
                </div>
                <div class="form-group" id="schedule-handover-group">
                    <label>Jour de passation</label>
                    <select id="schedule-handover-weekday">
                        <option value="">Jour de recurrence_start (par défaut)</option>
                        <option value="1">Lundi</option>
                        <option value="2">Mardi</option>
                        <option value="3">Mercredi</option>
                        <option value="4">Jeudi</option>
                        <option value="5">Vendredi</option>
                        <option value="6">Samedi</option>
                        <option value="7">Dimanche</option>
                    </select>
                    <small class="field-hint">Le jour de la semaine où la garde bascule d'un parent à l'autre.</small>
                </div>
                <div class="form-group" id="schedule-extra-weekday-group" style="display:none">
                    <label>Jour supplémentaire chez l'autre parent</label>
                    <select id="schedule-extra-weekday">
                        <option value="">— Choisir —</option>
                        <option value="1">Lundi</option>
                        <option value="2">Mardi</option>
                        <option value="3">Mercredi</option>
                        <option value="4">Jeudi</option>
                        <option value="5">Vendredi</option>
                    </select>
                    <small class="field-hint">Chaque semaine, le parent qui n'a pas le weekend récupère l'enfant ce jour-là (ex : le mercredi).</small>
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

<!-- Proposal Modal -->
<div class="modal-overlay" id="proposal-modal" style="display:none">
    <div class="modal modal-xl">
        <div class="modal-header">
            <h3>📅 Proposition de garde</h3>
            <button onclick="closeModal('proposal-modal')">✕</button>
        </div>
        <div class="modal-body">
            <!-- Setup bar -->
            <div class="proposal-setup">
                <div class="form-group">
                    <label>Enfant</label>
                    <select id="proposal-schedule-id" onchange="proposalUpdateParentInfo()">
                        <option value="">— Choisir —</option>
                        <?php foreach ($schedules as $s): ?>
                            <option value="<?= $s['id'] ?>"
                                data-p1-label="<?= htmlspecialchars($s['recurrence_parent1_label'] ?? '') ?>"
                                data-p1-color="<?= htmlspecialchars($s['recurrence_parent1_color'] ?? '#4A90D9') ?>"
                                data-p1-id="<?= (int)($s['recurrence_parent1_id'] ?? 0) ?>"
                                data-p2-label="<?= htmlspecialchars($s['recurrence_parent2_label'] ?? '') ?>"
                                data-p2-color="<?= htmlspecialchars($s['recurrence_parent2_color'] ?? '#E74C3C') ?>"
                                data-p2-id="<?= (int)($s['recurrence_parent2_id'] ?? 0) ?>"
                            ><?= htmlspecialchars($s['child_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Du</label>
                    <input type="date" id="proposal-start">
                </div>
                <div class="form-group">
                    <label>Au</label>
                    <input type="date" id="proposal-end">
                </div>
                <button class="btn btn-primary btn-sm" onclick="generateProposal()">Générer</button>
            </div>

            <!-- Parent legend + counters -->
            <div id="proposal-legend" style="display:none" class="proposal-legend">
                <div class="proposal-legend-item" id="proposal-p1-legend">
                    <span class="legend-dot" id="proposal-p1-dot"></span>
                    <span id="proposal-p1-name">Parent 1</span>
                    <strong id="proposal-p1-count">0 j</strong>
                    <button class="btn btn-xs" onclick="proposalFillWeekdays(1)" title="Attribuer tous les jours de semaine">Semaine</button>
                    <button class="btn btn-xs" onclick="proposalFillWeekends(1)" title="Attribuer tous les weekends">Weekend</button>
                    <button class="btn btn-xs" onclick="proposalFillAll(1)" title="Tout attribuer">Tout</button>
                </div>
                <div class="proposal-legend-item" id="proposal-p2-legend">
                    <span class="legend-dot" id="proposal-p2-dot"></span>
                    <span id="proposal-p2-name">Parent 2</span>
                    <strong id="proposal-p2-count">0 j</strong>
                    <button class="btn btn-xs" onclick="proposalFillWeekdays(2)" title="Attribuer tous les jours de semaine">Semaine</button>
                    <button class="btn btn-xs" onclick="proposalFillWeekends(2)" title="Attribuer tous les weekends">Weekend</button>
                    <button class="btn btn-xs" onclick="proposalFillAll(2)" title="Tout attribuer">Tout</button>
                </div>
                <button class="btn btn-xs btn-secondary" onclick="proposalClearAll()" title="Effacer toutes les attributions">Effacer tout</button>
            </div>

            <!-- Calendar grid -->
            <div id="proposal-calendar" class="proposal-calendar"></div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('proposal-modal')">Fermer</button>
            <button class="btn btn-secondary" id="proposal-export-btn" style="display:none" onclick="exportProposalAsImage()">📷 Exporter en image</button>
            <button class="btn btn-primary" id="proposal-apply-btn" style="display:none" onclick="applyProposal()">✅ Appliquer au calendrier</button>
        </div>
    </div>
</div>

<!-- Vacation periods modal -->
<div class="modal-overlay" id="vacation-modal" style="display:none">
    <div class="modal">
        <div class="modal-header">
            <h3 id="vacation-modal-title">Périodes de vacances</h3>
            <button onclick="closeModal('vacation-modal')">✕</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="vacation-schedule-id">
            <div id="vacation-list" style="margin-bottom:1rem"></div>
            <hr class="divider">
            <p class="section-label">Ajouter une période</p>
            <input type="hidden" id="vacation-id">
            <div class="form-group">
                <label>Libellé</label>
                <input type="text" id="vacation-label" placeholder="Grandes vacances 2026…">
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Du</label>
                    <input type="date" id="vacation-start">
                </div>
                <div class="form-group">
                    <label>Au</label>
                    <input type="date" id="vacation-end">
                </div>
            </div>
            <div class="form-group">
                <label>Répartition</label>
                <select id="vacation-distribution">
                    <option value="1week_2">1 semaine sur 2</option>
                    <option value="2weeks_4">2 semaines sur 4</option>
                    <option value="odd_even_weeks">Semaines paires / impaires</option>
                </select>
            </div>
            <div class="form-group">
                <label>Commence chez</label>
                <select id="vacation-starting-parent">
                    <option value="1">Parent 1</option>
                    <option value="2">Parent 2</option>
                </select>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="resetVacationForm()">Nouvelle période</button>
            <button class="btn btn-primary" onclick="saveVacationPeriod()">Enregistrer</button>
        </div>
    </div>
</div>

<!-- Invite co-parent modal -->
<div class="modal-overlay" id="invite-coparent-modal" style="display:none">
    <div class="modal">
        <div class="modal-header">
            <h3>Inviter un co-parent 🔒</h3>
            <button onclick="closeModal('invite-coparent-modal')">✕</button>
        </div>
        <div class="modal-body">
            <p style="font-size:.85rem;color:var(--text-muted);margin-bottom:1rem">
                Cette personne pourra se connecter avec un accès limité au calendrier de garde, aux
                propositions de garde, au journal parental et aux documents/évènements des enfants
                sélectionnés — pas au reste de vos données familiales.
            </p>
            <div class="form-group">
                <label>Email</label>
                <input type="email" id="invite-coparent-email" placeholder="autre-parent@email.fr">
            </div>
            <div class="form-group">
                <label>Enfants concernés</label>
                <div id="invite-coparent-children"></div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('invite-coparent-modal')">Annuler</button>
            <button class="btn btn-primary" onclick="sendCoparentInvite()">Envoyer l'invitation</button>
        </div>
    </div>
</div>

<style>
.modal-xl { max-width: 900px; width: 96vw; }
.proposal-setup { display: flex; gap: .75rem; align-items: flex-end; flex-wrap: wrap; margin-bottom: 1rem; }
.proposal-setup .form-group { margin-bottom: 0; }
.proposal-legend { display: flex; gap: 1rem; align-items: center; flex-wrap: wrap; padding: .6rem .9rem; background: var(--bg); border-radius: 8px; margin-bottom: .75rem; }
.proposal-legend-item { display: flex; align-items: center; gap: .4rem; font-size: .85rem; }
.proposal-calendar { display: flex; flex-direction: column; gap: .5rem; max-height: 55vh; overflow-y: auto; padding-right: 4px; }
.proposal-month { }
.proposal-month-title { font-weight: 600; font-size: .85rem; margin-bottom: .3rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: .05em; }
.proposal-week { display: grid; grid-template-columns: repeat(7, 1fr); gap: 3px; margin-bottom: 3px; }
.proposal-day {
    aspect-ratio: 1;
    border-radius: 6px;
    border: 2px solid transparent;
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    cursor: pointer; font-size: .75rem; font-weight: 500;
    transition: transform .1s, box-shadow .1s;
    min-height: 36px;
    user-select: none;
}
.proposal-day:hover { transform: scale(1.08); box-shadow: 0 2px 8px rgba(0,0,0,.15); }
.proposal-day.pd-empty { visibility: hidden; pointer-events: none; }
.proposal-day.pd-other-month { opacity: .35; }
.proposal-day.pd-weekend { background: #f8f9fa; }
.proposal-day.pd-p1 { color: white !important; }
.proposal-day.pd-p2 { color: white !important; }
.proposal-day .pd-num { font-size: .75rem; }
.proposal-day .pd-initial { font-size: .65rem; font-weight: 700; margin-top: 1px; opacity: .9; }
.btn-xs { font-size: .7rem; padding: .15rem .45rem; border-radius: 5px; border: 1px solid var(--border); background: var(--surface); cursor: pointer; white-space: nowrap; }
.btn-xs:hover { background: var(--bg); }
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
    const handoverApplicable = ['every_other_week', 'every_2weeks', 'every_month'].includes(value);
    document.getElementById('schedule-handover-group').style.display = handoverApplicable ? '' : 'none';
    document.getElementById('schedule-extra-weekday-group').style.display = value === 'weekends_every_2_plus_weekday' ? '' : 'none';
}
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
