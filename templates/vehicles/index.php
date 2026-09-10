<?php
$pageTitle = 'Véhicules';
$extraJs = ['vehicles.js'];
ob_start();

$today = date('Y-m-d');
$soon = date('Y-m-d', strtotime('+30 days'));
$expiryClass = function ($date) use ($today, $soon) {
    if (!$date) return '';
    if ($date < $today) return 'color:var(--danger);font-weight:600';
    if ($date <= $soon) return 'color:#E67E22;font-weight:600';
    return '';
};
?>
<div class="tasks-container">
    <div class="tasks-sidebar">
        <div class="tasks-sidebar-header">
            <h3>Véhicules</h3>
            <button class="btn-icon" onclick="openNewVehicleModal()" title="Nouveau véhicule">+</button>
        </div>
        <ul class="lists-menu">
            <?php foreach ($vehicles as $v): ?>
                <li class="list-item <?= $selected && (int)$selected['id'] === (int)$v['id'] ? 'active' : '' ?>">
                    <a href="<?= BASE_URL ?>/vehicles?id=<?= $v['id'] ?>" class="list-link">
                        <span class="list-name">🚗 <?= htmlspecialchars($v['name']) ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
            <?php if (empty($vehicles)): ?>
                <li class="empty-state">Aucun véhicule.</li>
            <?php endif; ?>
        </ul>
    </div>

    <div class="tasks-main">
        <?php if ($selected): ?>
            <div class="tasks-header">
                <div>
                    <h2>🚗 <?= htmlspecialchars($selected['name']) ?></h2>
                    <?php if ($selected['brand'] || $selected['model']): ?>
                        <p style="color:var(--text-muted);margin:.2rem 0"><?= htmlspecialchars(trim($selected['brand'] . ' ' . $selected['model'])) ?><?= $selected['plate'] ? ' — ' . htmlspecialchars($selected['plate']) : '' ?></p>
                    <?php endif; ?>
                </div>
                <div style="display:flex;gap:.4rem">
                    <button class="btn btn-secondary btn-sm" onclick='openEditVehicleModal(<?= json_encode($selected) ?>)'>✏️ Modifier</button>
                    <button class="btn btn-danger btn-sm" onclick="deleteVehicle(<?= $selected['id'] ?>)">🗑 Supprimer</button>
                </div>
            </div>

            <div class="card settings-section">
                <div class="form-row" style="margin:0">
                    <div class="form-group">
                        <div style="font-size:.8rem;color:var(--text-muted)">Kilométrage</div>
                        <div style="font-weight:600"><?= $selected['mileage'] !== null ? number_format((int)$selected['mileage'], 0, ',', ' ') . ' km' : '—' ?></div>
                    </div>
                    <div class="form-group">
                        <div style="font-size:.8rem;color:var(--text-muted)">Assurance</div>
                        <div><?= htmlspecialchars($selected['insurance_company'] ?: '—') ?></div>
                        <div style="<?= $expiryClass($selected['insurance_expiry']) ?>"><?= $selected['insurance_expiry'] ? 'Échéance : ' . (new DateTime($selected['insurance_expiry']))->format('d/m/Y') : '' ?></div>
                    </div>
                    <div class="form-group">
                        <div style="font-size:.8rem;color:var(--text-muted)">Contrôle technique</div>
                        <div style="<?= $expiryClass($selected['technical_control_expiry']) ?>"><?= $selected['technical_control_expiry'] ? (new DateTime($selected['technical_control_expiry']))->format('d/m/Y') : '—' ?></div>
                    </div>
                </div>
                <?php if ($selected['notes']): ?><p style="margin-top:.6rem;white-space:pre-wrap"><?= htmlspecialchars($selected['notes']) ?></p><?php endif; ?>
            </div>

            <div class="card settings-section">
                <h3 style="margin-top:0">🔧 Entretien</h3>
                <div class="form-row">
                    <div class="form-group flex-2">
                        <label>Titre</label>
                        <input type="text" id="maint-title" placeholder="Vidange, pneus…">
                    </div>
                    <div class="form-group">
                        <label>Date</label>
                        <input type="date" id="maint-date">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Kilométrage</label>
                        <input type="text" id="maint-mileage">
                    </div>
                    <div class="form-group">
                        <label>Coût (€)</label>
                        <input type="text" id="maint-cost">
                    </div>
                    <div class="form-group flex-2">
                        <label>Notes</label>
                        <input type="text" id="maint-notes">
                    </div>
                </div>
                <button class="btn btn-secondary btn-sm" onclick="addMaintenance()">+ Ajouter</button>
                <table class="admin-table" style="margin-top:1rem">
                    <thead><tr><th>Date</th><th>Intervention</th><th>Km</th><th>Coût</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($maintenance as $m): ?>
                        <tr>
                            <td><?= (new DateTime($m['done_at']))->format('d/m/Y') ?></td>
                            <td><?= htmlspecialchars($m['title']) ?><?= $m['notes'] ? ' — ' . htmlspecialchars($m['notes']) : '' ?></td>
                            <td><?= $m['mileage'] !== null ? number_format((int)$m['mileage'], 0, ',', ' ') . ' km' : '—' ?></td>
                            <td><?= $m['cost_cents'] !== null ? number_format($m['cost_cents'] / 100, 2, ',', ' ') . ' €' : '—' ?></td>
                            <td><button class="btn-icon" title="Supprimer" onclick="deleteMaintenance(<?= $m['id'] ?>)">🗑</button></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($maintenance)): ?>
                        <tr><td colspan="5" class="empty-state">Aucun entretien enregistré.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="empty-state">Ajoutez un premier véhicule.</p>
        <?php endif; ?>
    </div>
</div>

<!-- Nouveau/modifier véhicule -->
<div class="modal-overlay" id="vehicle-modal" style="display:none">
    <div class="modal">
        <div class="modal-header">
            <h3 id="vehicle-modal-title">Nouveau véhicule</h3>
            <button onclick="closeModal('vehicle-modal')">✕</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="vehicle-id">
            <div class="form-row">
                <div class="form-group flex-2">
                    <label>Nom <span style="color:var(--danger)">*</span></label>
                    <input type="text" id="vehicle-name" placeholder="Clio de Maman">
                </div>
                <div class="form-group">
                    <label>Immatriculation</label>
                    <input type="text" id="vehicle-plate">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Marque</label>
                    <input type="text" id="vehicle-brand">
                </div>
                <div class="form-group">
                    <label>Modèle</label>
                    <input type="text" id="vehicle-model">
                </div>
                <div class="form-group">
                    <label>Kilométrage</label>
                    <input type="text" id="vehicle-mileage">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Assureur</label>
                    <input type="text" id="vehicle-insurance-company">
                </div>
                <div class="form-group">
                    <label>Échéance assurance</label>
                    <input type="date" id="vehicle-insurance-expiry">
                </div>
                <div class="form-group">
                    <label>Contrôle technique</label>
                    <input type="date" id="vehicle-ct-expiry">
                </div>
            </div>
            <div class="form-group">
                <label>Notes</label>
                <textarea id="vehicle-notes" rows="2"></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('vehicle-modal')">Annuler</button>
            <button class="btn btn-primary" onclick="saveVehicle()">Enregistrer</button>
        </div>
    </div>
</div>

<script>
const VEHICLE_ID = <?= json_encode($selected['id'] ?? null) ?>;
const BASE_URL = <?= json_encode(BASE_URL) ?>;
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
