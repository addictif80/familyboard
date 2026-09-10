<?php
$pageTitle = 'Voyages & réservations';
$extraJs = ['travels.js'];
ob_start();

use App\Models\Travel;
?>
<div class="tasks-container">
    <div class="tasks-sidebar">
        <div class="tasks-sidebar-header">
            <h3>Voyages</h3>
            <button class="btn-icon" onclick="openNewTravelModal()" title="Nouveau voyage">+</button>
        </div>
        <ul class="lists-menu">
            <?php foreach ($travels as $t): ?>
                <li class="list-item <?= $selected && (int)$selected['id'] === (int)$t['id'] ? 'active' : '' ?>">
                    <a href="<?= BASE_URL ?>/travels?id=<?= $t['id'] ?>" class="list-link">
                        <span class="list-name">✈️ <?= htmlspecialchars($t['title']) ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
            <?php if (empty($travels)): ?>
                <li class="empty-state">Aucun voyage.</li>
            <?php endif; ?>
        </ul>
    </div>

    <div class="tasks-main">
        <?php if ($selected): ?>
            <div class="tasks-header">
                <div>
                    <h2>✈️ <?= htmlspecialchars($selected['title']) ?></h2>
                    <?php if ($selected['destination']): ?>
                        <p style="color:var(--text-muted);margin:.2rem 0"><?= htmlspecialchars($selected['destination']) ?></p>
                    <?php endif; ?>
                </div>
                <div style="display:flex;gap:.4rem">
                    <button class="btn btn-secondary btn-sm" onclick='openEditTravelModal(<?= json_encode($selected) ?>)'>✏️ Modifier</button>
                    <button class="btn btn-danger btn-sm" onclick="deleteTravel(<?= $selected['id'] ?>)">🗑 Supprimer</button>
                </div>
            </div>

            <div class="card settings-section">
                <div class="form-row" style="margin:0">
                    <div class="form-group">
                        <div style="font-size:.8rem;color:var(--text-muted)">Dates</div>
                        <div style="font-weight:600">
                            <?= $selected['start_date'] ? (new DateTime($selected['start_date']))->format('d/m/Y') : '—' ?>
                            <?= $selected['end_date'] ? ' → ' . (new DateTime($selected['end_date']))->format('d/m/Y') : '' ?>
                        </div>
                    </div>
                    <div class="form-group">
                        <div style="font-size:.8rem;color:var(--text-muted)">Budget</div>
                        <div><?= $selected['budget_cents'] !== null ? number_format($selected['budget_cents'] / 100, 2, ',', ' ') . ' €' : '—' ?></div>
                    </div>
                </div>
                <?php if ($selected['notes']): ?><p style="margin-top:.6rem;white-space:pre-wrap"><?= htmlspecialchars($selected['notes']) ?></p><?php endif; ?>
            </div>

            <div class="card settings-section">
                <h3 style="margin-top:0">🧳 Réservations</h3>
                <div class="form-row">
                    <div class="form-group flex-2">
                        <label>Titre</label>
                        <input type="text" id="res-title" placeholder="Vol Paris-Lisbonne…">
                    </div>
                    <div class="form-group">
                        <label>Type</label>
                        <select id="res-type">
                            <?php foreach (Travel::RESERVATION_TYPES as $slug => $rt): ?>
                                <option value="<?= $slug ?>"><?= $rt['icon'] ?> <?= htmlspecialchars($rt['label']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Date</label>
                        <input type="date" id="res-date">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>N° confirmation</label>
                        <input type="text" id="res-confirmation">
                    </div>
                    <div class="form-group">
                        <label>Coût (€)</label>
                        <input type="text" id="res-cost">
                    </div>
                    <div class="form-group flex-2">
                        <label>Notes</label>
                        <input type="text" id="res-notes">
                    </div>
                </div>
                <button class="btn btn-secondary btn-sm" onclick="addReservation()">+ Ajouter</button>
                <table class="admin-table" style="margin-top:1rem">
                    <thead><tr><th>Date</th><th>Type</th><th>Réservation</th><th>Coût</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($reservations as $r): ?>
                        <?php $rt = Travel::RESERVATION_TYPES[$r['type']]; ?>
                        <tr>
                            <td><?= $r['reservation_date'] ? (new DateTime($r['reservation_date']))->format('d/m/Y') : '—' ?></td>
                            <td><?= $rt['icon'] ?> <?= htmlspecialchars($rt['label']) ?></td>
                            <td><?= htmlspecialchars($r['title']) ?><?= $r['confirmation_number'] ? ' — n° ' . htmlspecialchars($r['confirmation_number']) : '' ?><?= $r['notes'] ? ' — ' . htmlspecialchars($r['notes']) : '' ?></td>
                            <td><?= $r['cost_cents'] !== null ? number_format($r['cost_cents'] / 100, 2, ',', ' ') . ' €' : '—' ?></td>
                            <td><button class="btn-icon" title="Supprimer" onclick="deleteReservation(<?= $r['id'] ?>)">🗑</button></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($reservations)): ?>
                        <tr><td colspan="5" class="empty-state">Aucune réservation enregistrée.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="empty-state">Ajoutez un premier voyage.</p>
        <?php endif; ?>
    </div>
</div>

<!-- Nouveau/modifier voyage -->
<div class="modal-overlay" id="travel-modal" style="display:none">
    <div class="modal">
        <div class="modal-header">
            <h3 id="travel-modal-title">Nouveau voyage</h3>
            <button onclick="closeModal('travel-modal')">✕</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="travel-id">
            <div class="form-row">
                <div class="form-group flex-2">
                    <label>Titre <span style="color:var(--danger)">*</span></label>
                    <input type="text" id="travel-title" placeholder="Vacances d'été…">
                </div>
                <div class="form-group">
                    <label>Destination</label>
                    <input type="text" id="travel-destination">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Du</label>
                    <input type="date" id="travel-start">
                </div>
                <div class="form-group">
                    <label>Au</label>
                    <input type="date" id="travel-end">
                </div>
                <div class="form-group">
                    <label>Budget (€)</label>
                    <input type="text" id="travel-budget">
                </div>
            </div>
            <div class="form-group">
                <label>Notes</label>
                <textarea id="travel-notes" rows="2"></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('travel-modal')">Annuler</button>
            <button class="btn btn-primary" onclick="saveTravel()">Enregistrer</button>
        </div>
    </div>
</div>

<script>
const TRAVEL_ID = <?= json_encode($selected['id'] ?? null) ?>;
const BASE_URL = <?= json_encode(BASE_URL) ?>;
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
