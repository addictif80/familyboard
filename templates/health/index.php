<?php
$pageTitle = 'Santé';
$extraJs = ['health.js'];
ob_start();

use App\Models\Health;

$subjectLabel = function ($type, $id) use ($subjects) {
    foreach ($subjects as $s) {
        if ($s['type'] === $type && (int)$s['id'] === (int)$id) return $s;
    }
    return null;
};
?>
<div class="tasks-main" style="width:100%">
    <div class="tasks-header">
        <h2>🏥 Santé</h2>
    </div>

    <div class="settings-tabs">
        <button type="button" class="settings-tab-btn active" data-tab="carnet" onclick="switchHealthTab('carnet')">📋 Carnet de santé</button>
        <button type="button" class="settings-tab-btn" data-tab="croissance" onclick="switchHealthTab('croissance')">📈 Croissance</button>
        <button type="button" class="settings-tab-btn" data-tab="medecins" onclick="switchHealthTab('medecins')">🩺 Médecins</button>
    </div>

    <!-- Carnet de santé -->
    <div class="settings-tab-panel active" data-tab="carnet">
        <div class="card settings-section">
            <?php if (empty($subjects)): ?>
                <p class="empty-state">Ajoutez d'abord un enfant (Réglages → Famille) ou un membre pour commencer.</p>
            <?php else: ?>
            <div class="form-row">
                <div class="form-group">
                    <label>Concerne</label>
                    <select id="entry-subject">
                        <?php foreach ($subjects as $s): ?>
                            <option value="<?= $s['type'] ?>:<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Type</label>
                    <select id="entry-type">
                        <?php foreach (Health::ENTRY_TYPES as $slug => $t): ?>
                            <option value="<?= $slug ?>"><?= $t['icon'] ?> <?= htmlspecialchars($t['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Date</label>
                    <input type="date" id="entry-date">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group flex-2">
                    <label>Titre</label>
                    <input type="text" id="entry-title" placeholder="DTP rappel, allergie arachide…">
                </div>
                <div class="form-group">
                    <label>Rappel (optionnel)</label>
                    <input type="date" id="entry-reminder">
                </div>
            </div>
            <div class="form-group">
                <label>Détails</label>
                <textarea id="entry-description" rows="2"></textarea>
            </div>
            <button class="btn btn-primary btn-sm" onclick="addHealthEntry()">+ Ajouter</button>
            <hr>
            <table class="admin-table">
                <thead><tr><th>Date</th><th>Concerne</th><th>Type</th><th>Titre</th><th>Rappel</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($entries as $e): ?>
                    <?php $s = $subjectLabel($e['subject_type'], $e['subject_id']); $t = Health::ENTRY_TYPES[$e['entry_type']]; ?>
                    <tr>
                        <td><?= $e['entry_date'] ? (new DateTime($e['entry_date']))->format('d/m/Y') : '—' ?></td>
                        <td><?= htmlspecialchars($s['name'] ?? '—') ?></td>
                        <td><?= $t['icon'] ?> <?= htmlspecialchars($t['label']) ?></td>
                        <td><?= htmlspecialchars($e['title']) ?></td>
                        <td><?= $e['reminder_date'] ? (new DateTime($e['reminder_date']))->format('d/m/Y') : '—' ?></td>
                        <td><button class="btn-icon" title="Supprimer" onclick="deleteHealthEntry(<?= $e['id'] ?>)">🗑</button></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($entries)): ?>
                    <tr><td colspan="6" class="empty-state">Aucune entrée.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- Croissance -->
    <div class="settings-tab-panel" data-tab="croissance">
        <div class="card settings-section">
            <?php if (empty($children)): ?>
                <p class="empty-state">Ajoutez d'abord un enfant (Réglages → Famille).</p>
            <?php else: ?>
            <form method="GET" action="<?= BASE_URL ?>/health" style="margin-bottom:1rem">
                <div class="form-group" style="max-width:280px">
                    <label>Enfant</label>
                    <select name="child_id" onchange="this.form.submit()">
                        <?php foreach ($children as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= (int)$selectedChildId === (int)$c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
            <div class="form-row">
                <input type="hidden" id="growth-child-id" value="<?= (int)$selectedChildId ?>">
                <div class="form-group">
                    <label>Date</label>
                    <input type="date" id="growth-date">
                </div>
                <div class="form-group">
                    <label>Taille (cm)</label>
                    <input type="text" id="growth-height" placeholder="102">
                </div>
                <div class="form-group">
                    <label>Poids (kg)</label>
                    <input type="text" id="growth-weight" placeholder="16.5">
                </div>
            </div>
            <button class="btn btn-primary btn-sm" onclick="addGrowthEntry()">+ Ajouter une mesure</button>
            <hr>
            <table class="admin-table">
                <thead><tr><th>Date</th><th>Taille</th><th>Poids</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($growth as $g): ?>
                    <tr>
                        <td><?= (new DateTime($g['measured_at']))->format('d/m/Y') ?></td>
                        <td><?= $g['height_cm'] !== null ? number_format((float)$g['height_cm'], 1, ',', ' ') . ' cm' : '—' ?></td>
                        <td><?= $g['weight_kg'] !== null ? number_format((float)$g['weight_kg'], 2, ',', ' ') . ' kg' : '—' ?></td>
                        <td><button class="btn-icon" title="Supprimer" onclick="deleteGrowthEntry(<?= $g['id'] ?>)">🗑</button></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($growth)): ?>
                    <tr><td colspan="4" class="empty-state">Aucune mesure enregistrée.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- Médecins -->
    <div class="settings-tab-panel" data-tab="medecins">
        <div class="card settings-section">
            <?php if (empty($subjects)): ?>
                <p class="empty-state">Ajoutez d'abord un enfant ou un membre.</p>
            <?php else: ?>
            <button class="btn btn-primary btn-sm" onclick="openNewDoctorModal()">+ Nouveau médecin</button>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:1rem;margin-top:1rem">
            <?php foreach ($doctors as $doc): ?>
                <?php $s = $subjectLabel($doc['subject_type'], $doc['subject_id']); ?>
                <div class="card" style="padding:1rem">
                    <div style="display:flex;justify-content:space-between;align-items:flex-start">
                        <div>
                            <strong><?= htmlspecialchars($doc['name']) ?></strong>
                            <?php if ($doc['specialty']): ?><div style="font-size:.8rem;color:var(--text-muted)"><?= htmlspecialchars($doc['specialty']) ?></div><?php endif; ?>
                        </div>
                        <div style="display:flex;gap:.3rem">
                            <button class="btn-icon" title="Modifier" onclick='openEditDoctorModal(<?= json_encode($doc) ?>)'>✏️</button>
                            <button class="btn-icon" title="Supprimer" onclick="deleteDoctor(<?= $doc['id'] ?>)">🗑</button>
                        </div>
                    </div>
                    <p style="font-size:.85rem;margin:.4rem 0 0">Pour : <?= htmlspecialchars($s['name'] ?? '—') ?></p>
                    <?php if ($doc['phone']): ?><p style="font-size:.85rem;margin:.2rem 0">📞 <?= htmlspecialchars($doc['phone']) ?></p><?php endif; ?>
                    <?php if ($doc['address']): ?><p style="font-size:.85rem;margin:.2rem 0">📍 <?= htmlspecialchars($doc['address']) ?></p><?php endif; ?>
                    <?php if ($doc['next_appointment']): ?><p style="font-size:.85rem;margin:.2rem 0">📅 Prochain RDV : <?= (new DateTime($doc['next_appointment']))->format('d/m/Y') ?></p><?php endif; ?>
                    <?php if ($doc['prescription_renewal_date']): ?><p style="font-size:.85rem;margin:.2rem 0">💊 Renouvellement ordonnance : <?= (new DateTime($doc['prescription_renewal_date']))->format('d/m/Y') ?></p><?php endif; ?>
                </div>
            <?php endforeach; ?>
            </div>
            <?php if (empty($doctors)): ?><p class="empty-state">Aucun médecin enregistré.</p><?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Nouveau/modifier médecin -->
<div class="modal-overlay" id="doctor-modal" style="display:none">
    <div class="modal">
        <div class="modal-header">
            <h3 id="doctor-modal-title">Nouveau médecin</h3>
            <button onclick="closeModal('doctor-modal')">✕</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="doctor-id">
            <div class="form-row">
                <div class="form-group">
                    <label>Concerne</label>
                    <select id="doctor-subject">
                        <?php foreach ($subjects as $s): ?>
                            <option value="<?= $s['type'] ?>:<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Spécialité</label>
                    <input type="text" id="doctor-specialty" placeholder="Généraliste, dentiste…">
                </div>
            </div>
            <div class="form-group">
                <label>Nom</label>
                <input type="text" id="doctor-name">
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Téléphone</label>
                    <input type="text" id="doctor-phone">
                </div>
                <div class="form-group flex-2">
                    <label>Adresse</label>
                    <input type="text" id="doctor-address">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Prochain rendez-vous</label>
                    <input type="date" id="doctor-next-appointment">
                </div>
                <div class="form-group">
                    <label>Renouvellement ordonnance</label>
                    <input type="date" id="doctor-renewal">
                </div>
            </div>
            <div class="form-group">
                <label>Notes</label>
                <input type="text" id="doctor-notes">
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('doctor-modal')">Annuler</button>
            <button class="btn btn-primary" onclick="saveDoctor()">Enregistrer</button>
        </div>
    </div>
</div>

<script>
const BASE_URL = <?= json_encode(BASE_URL) ?>;
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
