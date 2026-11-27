<?php
$pageTitle = 'Traitement médical';
ob_start();

$isAdmin = ($user['role'] ?? '') === 'admin';
$scheduleLabel = function (array $t): string {
    if ($t['schedule_type'] === 'interval') {
        return 'Toutes les ' . (int)$t['interval_hours'] . 'h';
    }
    $times = json_decode($t['fixed_times'] ?? '[]', true) ?: [];
    return $times ? implode(', ', $times) : '—';
};
?>
<div class="medication-container">
    <p class="body-text" style="color:var(--text-muted);margin-bottom:1rem">
        Votre traitement reste privé — aucun autre membre de la famille ne peut le voir. Vous recevrez une
        notification push (sur votre appareil, pas par e-mail) à chaque heure de prise.
    </p>

    <div class="warranty-toolbar" style="margin-bottom:1.25rem">
        <h3 style="margin:0">Mon traitement</h3>
        <button class="btn btn-primary" onclick="openTreatmentModal('user', null, null)">+ Ajouter un traitement</button>
    </div>

    <div class="medication-grid" id="medication-grid-user">
        <?php if (empty($myTreatments)): ?>
            <div class="empty-state">Aucun traitement enregistré.</div>
        <?php else: foreach ($myTreatments as $t): ?>
            <div class="medication-card <?= $t['active'] ? '' : 'medication-inactive' ?>" data-id="<?= $t['id'] ?>">
                <div class="medication-card-head">
                    <strong><?= htmlspecialchars($t['name']) ?></strong>
                    <?php if (!$t['active']): ?><span class="badge">Terminé</span><?php endif; ?>
                </div>
                <div class="body-text"><?= htmlspecialchars($t['dosage']) ?></div>
                <div class="body-text">⏰ <?= htmlspecialchars($scheduleLabel($t)) ?></div>
                <?php if ($t['notes']): ?><div class="body-text" style="font-style:italic"><?= htmlspecialchars($t['notes']) ?></div><?php endif; ?>
                <div class="medication-card-actions">
                    <button class="btn btn-secondary btn-sm" onclick='openTreatmentModal("user", null, <?= json_encode($t, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>Modifier</button>
                    <button class="btn btn-danger btn-sm" onclick="deleteTreatment(<?= $t['id'] ?>)">Supprimer</button>
                </div>
            </div>
        <?php endforeach; endif; ?>
    </div>

    <?php if ($isAdmin && $children): ?>
    <hr class="rule" style="margin:1.75rem 0">
    <h3>Traitement des enfants</h3>
    <p class="body-text" style="color:var(--text-muted);margin-bottom:1rem">
        Visible uniquement par les administrateurs de la famille. Les rappels de prise sont envoyés
        à tous les administrateurs, l'enfant n'ayant pas de compte dédié.
    </p>
    <?php foreach ($children as $c): ?>
    <div style="margin-bottom:1.5rem">
        <div class="warranty-toolbar">
            <h4 style="margin:0"><?= htmlspecialchars($c['name']) ?></h4>
            <button class="btn btn-secondary btn-sm" onclick="openTreatmentModal('child', <?= $c['id'] ?>, null)">+ Ajouter</button>
        </div>
        <div class="medication-grid">
            <?php if (empty($childTreatments[$c['id']])): ?>
                <div class="empty-state">Aucun traitement enregistré.</div>
            <?php else: foreach ($childTreatments[$c['id']] as $t): ?>
                <div class="medication-card <?= $t['active'] ? '' : 'medication-inactive' ?>" data-id="<?= $t['id'] ?>">
                    <div class="medication-card-head">
                        <strong><?= htmlspecialchars($t['name']) ?></strong>
                        <?php if (!$t['active']): ?><span class="badge">Terminé</span><?php endif; ?>
                    </div>
                    <div class="body-text"><?= htmlspecialchars($t['dosage']) ?></div>
                    <div class="body-text">⏰ <?= htmlspecialchars($scheduleLabel($t)) ?></div>
                    <?php if ($t['notes']): ?><div class="body-text" style="font-style:italic"><?= htmlspecialchars($t['notes']) ?></div><?php endif; ?>
                    <div class="medication-card-actions">
                        <button class="btn btn-secondary btn-sm" onclick='openTreatmentModal("child", <?= $c['id'] ?>, <?= json_encode($t, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>Modifier</button>
                        <button class="btn btn-danger btn-sm" onclick="deleteTreatment(<?= $t['id'] ?>)">Supprimer</button>
                    </div>
                </div>
            <?php endforeach; endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Modal: add/edit treatment -->
<div class="modal-overlay" id="modal-treatment" style="display:none">
    <div class="modal">
        <h3 id="treatment-modal-title">Ajouter un traitement</h3>
        <input type="hidden" id="treatment-id" value="">
        <input type="hidden" id="treatment-owner-type" value="user">
        <input type="hidden" id="treatment-owner-child-id" value="">

        <div class="form-group">
            <label>Médicament</label>
            <input type="text" id="treatment-name" maxlength="150" required>
        </div>
        <div class="form-group">
            <label>Posologie</label>
            <input type="text" id="treatment-dosage" maxlength="150" placeholder="ex. 1 comprimé" required>
        </div>
        <div class="form-group">
            <label>Notes (optionnel)</label>
            <textarea id="treatment-notes" rows="2"></textarea>
        </div>
        <div style="display:flex;gap:.75rem">
            <div class="form-group" style="flex:1">
                <label>Date de début</label>
                <input type="date" id="treatment-start-date" required>
            </div>
            <div class="form-group" style="flex:1">
                <label>Date de fin (optionnel)</label>
                <input type="date" id="treatment-end-date">
            </div>
        </div>

        <div class="form-group">
            <label>Rappels</label>
            <label class="radio-option">
                <input type="radio" name="treatment-schedule-type" value="fixed" checked onchange="toggleTreatmentScheduleType()">
                <span>Heures fixes dans la journée</span>
            </label>
            <label class="radio-option">
                <input type="radio" name="treatment-schedule-type" value="interval" onchange="toggleTreatmentScheduleType()">
                <span>Toutes les X heures</span>
            </label>
        </div>

        <div id="treatment-fixed-times-wrap" class="form-group">
            <label>Heures de prise</label>
            <div id="treatment-fixed-times-list" style="display:flex;flex-direction:column;gap:.4rem"></div>
            <button type="button" class="btn btn-secondary btn-sm" style="margin-top:.4rem" onclick="addTreatmentTimeRow()">+ Ajouter une heure</button>
        </div>

        <div id="treatment-interval-wrap" class="form-group" style="display:none">
            <label>Fréquence</label>
            <div style="display:flex;gap:.5rem;align-items:center">
                Toutes les <input type="number" id="treatment-interval-hours" min="1" max="48" value="8" style="width:70px"> heures,
                à partir du <input type="datetime-local" id="treatment-interval-anchor">
            </div>
        </div>

        <div style="display:flex;gap:.5rem;margin-top:1rem">
            <button class="btn btn-secondary" onclick="closeModal('modal-treatment')">Annuler</button>
            <button class="btn btn-primary" onclick="saveTreatment()">Enregistrer</button>
        </div>
    </div>
</div>

<style>
.medication-grid{ display:grid; grid-template-columns:repeat(auto-fill,minmax(230px,1fr)); gap:.75rem; }
.medication-card{
    background:var(--card-bg); border:1px solid var(--border); border-radius:10px; padding:.85rem 1rem;
    display:flex; flex-direction:column; gap:.3rem; box-shadow: var(--shadow);
    transition: transform .2s cubic-bezier(.2,1.4,.3,1), box-shadow .2s ease;
}
.medication-card:hover{ transform: translateY(-2px); box-shadow: var(--shadow-lg); }
.medication-card.medication-inactive{ opacity:.6 }
.medication-card-head{ display:flex; justify-content:space-between; align-items:center }
.medication-card-actions{ display:flex; gap:.4rem; margin-top:.5rem }
.treatment-time-row{ display:flex; gap:.4rem; align-items:center }
</style>

<script>
function toggleTreatmentScheduleType() {
    const type = document.querySelector('input[name=treatment-schedule-type]:checked').value;
    document.getElementById('treatment-fixed-times-wrap').style.display = type === 'fixed' ? '' : 'none';
    document.getElementById('treatment-interval-wrap').style.display = type === 'interval' ? '' : 'none';
}

function addTreatmentTimeRow(value) {
    const list = document.getElementById('treatment-fixed-times-list');
    const row = document.createElement('div');
    row.className = 'treatment-time-row';
    row.innerHTML = `<input type="time" value="${value || ''}" class="treatment-time-input">
        <button type="button" class="btn btn-secondary btn-sm" onclick="this.parentElement.remove()">✕</button>`;
    list.appendChild(row);
}

function openTreatmentModal(ownerType, childId, treatment) {
    document.getElementById('treatment-modal-title').textContent = treatment ? 'Modifier le traitement' : 'Ajouter un traitement';
    document.getElementById('treatment-id').value = treatment ? treatment.id : '';
    document.getElementById('treatment-owner-type').value = ownerType;
    document.getElementById('treatment-owner-child-id').value = childId || '';
    document.getElementById('treatment-name').value = treatment ? treatment.name : '';
    document.getElementById('treatment-dosage').value = treatment ? treatment.dosage : '';
    document.getElementById('treatment-notes').value = treatment ? (treatment.notes || '') : '';
    document.getElementById('treatment-start-date').value = treatment ? treatment.start_date : '';
    document.getElementById('treatment-end-date').value = treatment ? (treatment.end_date || '') : '';

    const scheduleType = treatment ? treatment.schedule_type : 'fixed';
    document.querySelector(`input[name=treatment-schedule-type][value="${scheduleType}"]`).checked = true;
    toggleTreatmentScheduleType();

    document.getElementById('treatment-fixed-times-list').innerHTML = '';
    const times = treatment && treatment.fixed_times ? JSON.parse(treatment.fixed_times) : [];
    (times.length ? times : ['']).forEach(t => addTreatmentTimeRow(t === '' ? '' : t));

    document.getElementById('treatment-interval-hours').value = treatment ? (treatment.interval_hours || 8) : 8;
    document.getElementById('treatment-interval-anchor').value = treatment && treatment.interval_anchor
        ? treatment.interval_anchor.replace(' ', 'T').slice(0, 16)
        : new Date().toISOString().slice(0, 16);

    openModal('modal-treatment');
}

async function saveTreatment() {
    const id = document.getElementById('treatment-id').value;
    const scheduleType = document.querySelector('input[name=treatment-schedule-type]:checked').value;
    const fixedTimes = Array.from(document.querySelectorAll('.treatment-time-input'))
        .map(i => i.value).filter(Boolean);

    const payload = {
        owner_type: document.getElementById('treatment-owner-type').value,
        owner_child_id: document.getElementById('treatment-owner-child-id').value,
        name: document.getElementById('treatment-name').value,
        dosage: document.getElementById('treatment-dosage').value,
        notes: document.getElementById('treatment-notes').value,
        start_date: document.getElementById('treatment-start-date').value,
        end_date: document.getElementById('treatment-end-date').value,
        schedule_type: scheduleType,
        fixed_times: fixedTimes,
        interval_hours: document.getElementById('treatment-interval-hours').value,
        interval_anchor: document.getElementById('treatment-interval-anchor').value.replace('T', ' '),
    };

    const url = id ? `<?= BASE_URL ?>/api/medication/${id}` : '<?= BASE_URL ?>/api/medication';
    const res = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
    });
    const data = await res.json();
    if (data.success) {
        location.reload();
    } else {
        Dialog.toast(data.error || 'Erreur lors de l\'enregistrement.', 'error');
    }
}

async function deleteTreatment(id) {
    const ok = await Dialog.confirm('Supprimer ce traitement ?');
    if (!ok) return;
    const res = await fetch(`<?= BASE_URL ?>/api/medication/${id}/delete`, { method: 'POST' });
    const data = await res.json();
    if (data.success) location.reload();
    else Dialog.toast(data.error || 'Erreur lors de la suppression.', 'error');
}
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
