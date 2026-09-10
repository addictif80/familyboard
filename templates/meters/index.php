<?php
$pageTitle = 'Compteurs';
$extraJs = ['meters.js'];
ob_start();

use App\Models\Meter;
?>
<div class="tasks-container">
    <div class="tasks-sidebar">
        <div class="tasks-sidebar-header">
            <h3>Compteurs</h3>
            <button class="btn-icon" onclick="openNewMeterModal()" title="Nouveau compteur">+</button>
        </div>
        <ul class="lists-menu">
            <?php foreach ($meters as $m): ?>
                <?php $t = Meter::TYPES[$m['meter_type']]; ?>
                <li class="list-item <?= $selected && (int)$selected['id'] === (int)$m['id'] ? 'active' : '' ?>">
                    <a href="<?= BASE_URL ?>/meters?id=<?= $m['id'] ?>" class="list-link">
                        <span class="list-name"><?= $t['icon'] ?> <?= htmlspecialchars($m['name']) ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
            <?php if (empty($meters)): ?>
                <li class="empty-state">Aucun compteur.</li>
            <?php endif; ?>
        </ul>
    </div>

    <div class="tasks-main">
        <?php if ($selected): ?>
            <?php $t = Meter::TYPES[$selected['meter_type']]; ?>
            <div class="tasks-header">
                <div>
                    <h2><?= $t['icon'] ?> <?= htmlspecialchars($selected['name']) ?></h2>
                    <p style="color:var(--text-muted);margin:.2rem 0"><?= htmlspecialchars($t['label']) ?><?= $selected['provider'] ? ' — ' . htmlspecialchars($selected['provider']) : '' ?></p>
                </div>
                <div style="display:flex;gap:.4rem">
                    <button class="btn btn-secondary btn-sm" onclick='openEditMeterModal(<?= json_encode($selected) ?>)'>✏️ Modifier</button>
                    <button class="btn btn-danger btn-sm" onclick="deleteMeter(<?= $selected['id'] ?>)">🗑 Supprimer</button>
                </div>
            </div>

            <div class="card settings-section">
                <div class="form-row">
                    <div class="form-group">
                        <label>Date du relevé</label>
                        <input type="date" id="reading-date">
                    </div>
                    <?php if ($selected['has_hp_hc']): ?>
                        <div class="form-group">
                            <label>Index heures pleines (<?= htmlspecialchars($selected['unit']) ?>)</label>
                            <input type="text" id="reading-value-hp">
                        </div>
                        <div class="form-group">
                            <label>Index heures creuses (<?= htmlspecialchars($selected['unit']) ?>)</label>
                            <input type="text" id="reading-value-hc">
                        </div>
                    <?php else: ?>
                        <div class="form-group">
                            <label>Index (<?= htmlspecialchars($selected['unit']) ?>)</label>
                            <input type="text" id="reading-value">
                        </div>
                    <?php endif; ?>
                    <div class="form-group flex-2">
                        <label>Notes</label>
                        <input type="text" id="reading-notes">
                    </div>
                </div>
                <button class="btn btn-primary btn-sm" onclick="addReading()">+ Ajouter un relevé</button>
                <table class="admin-table" style="margin-top:1rem">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <?php if ($selected['has_hp_hc']): ?>
                                <th>Index HP</th><th>Index HC</th><th>Conso HP</th><th>Conso HC</th><th>Conso totale</th>
                            <?php else: ?>
                                <th>Index</th><th>Consommation période</th>
                            <?php endif; ?>
                            <th>Notes</th><th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach (array_reverse($series) as $s): ?>
                        <tr>
                            <td><?= (new DateTime($s['reading_at']))->format('d/m/Y') ?></td>
                            <?php if ($selected['has_hp_hc']): ?>
                                <td><?= $s['value_hp'] !== null ? number_format($s['value_hp'], 2, ',', ' ') . ' ' . htmlspecialchars($selected['unit']) : '—' ?></td>
                                <td><?= $s['value_hc'] !== null ? number_format($s['value_hc'], 2, ',', ' ') . ' ' . htmlspecialchars($selected['unit']) : '—' ?></td>
                                <td><?= $s['consumption_hp'] !== null ? number_format($s['consumption_hp'], 2, ',', ' ') . ' ' . htmlspecialchars($selected['unit']) : '—' ?></td>
                                <td><?= $s['consumption_hc'] !== null ? number_format($s['consumption_hc'], 2, ',', ' ') . ' ' . htmlspecialchars($selected['unit']) : '—' ?></td>
                                <td><?= $s['consumption'] !== null ? number_format($s['consumption'], 2, ',', ' ') . ' ' . htmlspecialchars($selected['unit']) : '—' ?></td>
                            <?php else: ?>
                                <td><?= $s['value'] !== null ? number_format($s['value'], 2, ',', ' ') . ' ' . htmlspecialchars($selected['unit']) : '—' ?></td>
                                <td><?= $s['consumption'] !== null ? number_format($s['consumption'], 2, ',', ' ') . ' ' . htmlspecialchars($selected['unit']) : '—' ?></td>
                            <?php endif; ?>
                            <td><?= htmlspecialchars($s['notes'] ?? '') ?></td>
                            <td><button class="btn-icon" title="Supprimer" onclick="deleteReading(<?= $s['id'] ?>)">🗑</button></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($series)): ?>
                        <tr><td colspan="<?= $selected['has_hp_hc'] ? 8 : 5 ?>" class="empty-state">Aucun relevé enregistré.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="empty-state">Ajoutez un premier compteur.</p>
        <?php endif; ?>
    </div>
</div>

<!-- Nouveau/modifier compteur -->
<div class="modal-overlay" id="meter-modal" style="display:none">
    <div class="modal">
        <div class="modal-header">
            <h3 id="meter-modal-title">Nouveau compteur</h3>
            <button onclick="closeModal('meter-modal')">✕</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="meter-id">
            <div class="form-row">
                <div class="form-group flex-2">
                    <label>Nom <span style="color:var(--danger)">*</span></label>
                    <input type="text" id="meter-name" placeholder="Compteur électrique principal">
                </div>
                <div class="form-group">
                    <label>Type</label>
                    <select id="meter-type" onchange="onMeterTypeChange()">
                        <?php foreach (Meter::TYPES as $slug => $t): ?>
                            <option value="<?= $slug ?>" data-unit="<?= htmlspecialchars($t['unit']) ?>"><?= $t['icon'] ?> <?= htmlspecialchars($t['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Unité</label>
                    <input type="text" id="meter-unit" placeholder="kWh, m³…">
                </div>
                <div class="form-group flex-2">
                    <label>Fournisseur</label>
                    <input type="text" id="meter-provider">
                </div>
                <div class="form-group">
                    <label>N° de contrat</label>
                    <input type="text" id="meter-contract">
                </div>
            </div>
            <div class="form-group" id="meter-hp-hc-row" style="display:none">
                <label><input type="checkbox" id="meter-has-hp-hc"> Compteur à heures pleines / heures creuses</label>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('meter-modal')">Annuler</button>
            <button class="btn btn-primary" onclick="saveMeter()">Enregistrer</button>
        </div>
    </div>
</div>

<script>
const METER_ID = <?= json_encode($selected['id'] ?? null) ?>;
const METER_HAS_HP_HC = <?= json_encode((bool)($selected['has_hp_hc'] ?? false)) ?>;
const BASE_URL = <?= json_encode(BASE_URL) ?>;
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
