<?php
$pageTitle = 'Animaux';
$extraJs = ['pets.js'];
ob_start();

use App\Models\Pet;
?>
<div class="tasks-container">
    <div class="tasks-sidebar">
        <div class="tasks-sidebar-header">
            <h3>Animaux</h3>
            <button class="btn-icon" onclick="openNewPetModal()" title="Nouvel animal">+</button>
        </div>
        <ul class="lists-menu">
            <?php foreach ($pets as $p): ?>
                <li class="list-item <?= $selected && (int)$selected['id'] === (int)$p['id'] ? 'active' : '' ?>">
                    <a href="<?= BASE_URL ?>/pets?id=<?= $p['id'] ?>" class="list-link">
                        <span class="list-name">🐾 <?= htmlspecialchars($p['name']) ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
            <?php if (empty($pets)): ?>
                <li class="empty-state">Aucun animal.</li>
            <?php endif; ?>
        </ul>
    </div>

    <div class="tasks-main">
        <?php if ($selected): ?>
            <div class="tasks-header">
                <div>
                    <h2>🐾 <?= htmlspecialchars($selected['name']) ?></h2>
                    <p style="color:var(--text-muted);margin:.2rem 0"><?= htmlspecialchars(trim(($selected['species'] ?: '') . ' ' . ($selected['breed'] ? '— ' . $selected['breed'] : ''))) ?></p>
                </div>
                <div style="display:flex;gap:.4rem">
                    <button class="btn btn-secondary btn-sm" onclick='openEditPetModal(<?= json_encode($selected) ?>)'>✏️ Modifier</button>
                    <button class="btn btn-danger btn-sm" onclick="deletePet(<?= $selected['id'] ?>)">🗑 Supprimer</button>
                </div>
            </div>

            <div class="card settings-section">
                <div class="form-row" style="margin:0">
                    <div class="form-group">
                        <div style="font-size:.8rem;color:var(--text-muted)">Date de naissance</div>
                        <div><?= $selected['birth_date'] ? (new DateTime($selected['birth_date']))->format('d/m/Y') : '—' ?></div>
                    </div>
                    <div class="form-group">
                        <div style="font-size:.8rem;color:var(--text-muted)">Vétérinaire</div>
                        <div><?= htmlspecialchars($selected['vet_name'] ?: '—') ?><?= $selected['vet_phone'] ? ' — ' . htmlspecialchars($selected['vet_phone']) : '' ?></div>
                    </div>
                </div>
                <?php if ($selected['notes']): ?><p style="margin-top:.6rem;white-space:pre-wrap"><?= htmlspecialchars($selected['notes']) ?></p><?php endif; ?>
            </div>

            <div class="card settings-section">
                <h3 style="margin-top:0">💉 Carnet de soins</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label>Type</label>
                        <select id="care-type">
                            <?php foreach (Pet::CARE_TYPES as $slug => $t): ?>
                                <option value="<?= $slug ?>"><?= $t['icon'] ?> <?= htmlspecialchars($t['label']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group flex-2">
                        <label>Titre</label>
                        <input type="text" id="care-title" placeholder="Rappel vaccin CHPPI…">
                    </div>
                    <div class="form-group">
                        <label>Date</label>
                        <input type="date" id="care-date">
                    </div>
                    <div class="form-group">
                        <label>Rappel</label>
                        <input type="date" id="care-reminder">
                    </div>
                </div>
                <button class="btn btn-secondary btn-sm" onclick="addCareEntry()">+ Ajouter</button>
                <table class="admin-table" style="margin-top:1rem">
                    <thead><tr><th>Date</th><th>Type</th><th>Titre</th><th>Rappel</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($careEntries as $c): ?>
                        <?php $t = Pet::CARE_TYPES[$c['entry_type']]; ?>
                        <tr>
                            <td><?= (new DateTime($c['done_at']))->format('d/m/Y') ?></td>
                            <td><?= $t['icon'] ?> <?= htmlspecialchars($t['label']) ?></td>
                            <td><?= htmlspecialchars($c['title']) ?></td>
                            <td><?= $c['reminder_date'] ? (new DateTime($c['reminder_date']))->format('d/m/Y') : '—' ?></td>
                            <td><button class="btn-icon" title="Supprimer" onclick="deleteCareEntry(<?= $c['id'] ?>)">🗑</button></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($careEntries)): ?>
                        <tr><td colspan="5" class="empty-state">Aucun soin enregistré.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="empty-state">Ajoutez un premier animal.</p>
        <?php endif; ?>
    </div>
</div>

<!-- Nouveau/modifier animal -->
<div class="modal-overlay" id="pet-modal" style="display:none">
    <div class="modal">
        <div class="modal-header">
            <h3 id="pet-modal-title">Nouvel animal</h3>
            <button onclick="closeModal('pet-modal')">✕</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="pet-id">
            <div class="form-row">
                <div class="form-group flex-2">
                    <label>Nom <span style="color:var(--danger)">*</span></label>
                    <input type="text" id="pet-name">
                </div>
                <div class="form-group">
                    <label>Espèce</label>
                    <input type="text" id="pet-species" placeholder="Chien, chat…">
                </div>
                <div class="form-group">
                    <label>Race</label>
                    <input type="text" id="pet-breed">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Date de naissance</label>
                    <input type="date" id="pet-birthdate">
                </div>
                <div class="form-group">
                    <label>Vétérinaire</label>
                    <input type="text" id="pet-vet-name">
                </div>
                <div class="form-group">
                    <label>Téléphone vétérinaire</label>
                    <input type="text" id="pet-vet-phone">
                </div>
            </div>
            <div class="form-group">
                <label>Notes</label>
                <textarea id="pet-notes" rows="2"></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('pet-modal')">Annuler</button>
            <button class="btn btn-primary" onclick="savePet()">Enregistrer</button>
        </div>
    </div>
</div>

<script>
const PET_ID = <?= json_encode($selected['id'] ?? null) ?>;
const BASE_URL = <?= json_encode(BASE_URL) ?>;
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
