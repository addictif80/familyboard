<?php
$pageTitle = 'Coffre-fort numérique';
$extraJs = ['vault.js'];
ob_start();

use App\Models\Vault;
?>
<div class="tasks-main" style="width:100%">
    <div class="tasks-header">
        <h2>🔐 Coffre-fort numérique</h2>
        <button class="btn btn-primary btn-sm" onclick="openNewEntryModal()">+ Nouvelle entrée</button>
    </div>
    <p style="color:var(--text-muted);font-size:.85rem;margin-top:-.5rem">Documents importants, comptes, contacts utiles et souhaits — accessibles à vos personnes de confiance uniquement après validation d'une demande d'accès d'urgence.</p>

    <div class="card settings-section">
        <?php foreach (Vault::CATEGORIES as $slug => $cat): ?>
            <?php $catEntries = array_values(array_filter($entries, fn($e) => $e['category'] === $slug)); ?>
            <?php if (!empty($catEntries)): ?>
                <h3><?= $cat['icon'] ?> <?= htmlspecialchars($cat['label']) ?></h3>
                <table class="admin-table" style="margin-bottom:1rem">
                    <tbody>
                    <?php foreach ($catEntries as $e): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($e['title']) ?></strong>
                                <?php if ($e['content']): ?><div style="color:var(--text-muted);font-size:.85rem;white-space:pre-wrap"><?= htmlspecialchars($e['content']) ?></div><?php endif; ?>
                                <?php if ($e['file_path']): ?><div><a href="<?= BASE_URL ?>/api/vault/entries/<?= $e['id'] ?>/file" target="_blank">📎 <?= htmlspecialchars($e['file_original']) ?></a></div><?php endif; ?>
                            </td>
                            <td style="width:80px;text-align:right">
                                <button class="btn-icon" title="Modifier" onclick='openEditEntryModal(<?= json_encode($e) ?>)'>✏️</button>
                                <button class="btn-icon" title="Supprimer" onclick="deleteEntry(<?= $e['id'] ?>)">🗑</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        <?php endforeach; ?>
        <?php if (empty($entries)): ?>
            <p class="empty-state">Aucune entrée dans le coffre-fort.</p>
        <?php endif; ?>
    </div>

    <div class="tasks-header" style="margin-top:1.5rem">
        <h2>🤝 Personnes de confiance</h2>
        <button class="btn btn-secondary btn-sm" onclick="openNewTrusteeModal()">+ Ajouter</button>
    </div>
    <div class="card settings-section">
        <table class="admin-table">
            <thead><tr><th>Nom</th><th>Lien</th><th>Statut</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($trustees as $t): ?>
                <tr>
                    <td><?= htmlspecialchars($t['name']) ?><div style="color:var(--text-muted);font-size:.8rem"><?= htmlspecialchars($t['email']) ?><?= $t['relationship'] ? ' — ' . htmlspecialchars($t['relationship']) : '' ?></div></td>
                    <td><a href="#" onclick="copyTrusteeLink('<?= htmlspecialchars($t['access_token']) ?>');return false;">🔗 Copier le lien</a></td>
                    <td>
                        <?php if ($t['status'] === 'none'): ?>
                            <span style="color:var(--text-muted)">Aucune demande</span>
                        <?php elseif ($t['status'] === 'requested'): ?>
                            <span style="color:#E67E22;font-weight:600">⏳ Demande en attente</span>
                            <div style="margin-top:.3rem">
                                <button class="btn btn-primary btn-sm" onclick="decideTrustee(<?= $t['id'] ?>, true)">Approuver</button>
                                <button class="btn btn-secondary btn-sm" onclick="decideTrustee(<?= $t['id'] ?>, false)">Refuser</button>
                            </div>
                        <?php elseif ($t['status'] === 'approved'): ?>
                            <span style="color:var(--success);font-weight:600">✅ Accès approuvé</span>
                            <div style="margin-top:.3rem">
                                <button class="btn btn-secondary btn-sm" onclick="revokeTrustee(<?= $t['id'] ?>)">Révoquer</button>
                            </div>
                        <?php else: ?>
                            <span style="color:var(--danger)">Refusé</span>
                        <?php endif; ?>
                    </td>
                    <td><button class="btn-icon" title="Supprimer" onclick="deleteTrustee(<?= $t['id'] ?>)">🗑</button></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($trustees)): ?>
                <tr><td colspan="4" class="empty-state">Aucune personne de confiance désignée.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Nouvelle/modifier entrée -->
<div class="modal-overlay" id="entry-modal" style="display:none">
    <div class="modal">
        <div class="modal-header">
            <h3 id="entry-modal-title">Nouvelle entrée</h3>
            <button onclick="closeModal('entry-modal')">✕</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="entry-id">
            <div class="form-row">
                <div class="form-group flex-2">
                    <label>Titre <span style="color:var(--danger)">*</span></label>
                    <input type="text" id="entry-title" placeholder="Contrat d'assurance-vie…">
                </div>
                <div class="form-group">
                    <label>Catégorie</label>
                    <select id="entry-category">
                        <?php foreach (Vault::CATEGORIES as $slug => $cat): ?>
                            <option value="<?= $slug ?>"><?= $cat['icon'] ?> <?= htmlspecialchars($cat['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>Contenu</label>
                <textarea id="entry-content" rows="4" placeholder="Numéro de contrat, identifiants, instructions…"></textarea>
            </div>
            <div class="form-group">
                <label>Fichier (image/PDF)</label>
                <input type="file" id="entry-file" accept="image/*,application/pdf">
                <div id="entry-current-file" style="font-size:.85rem;margin-top:.3rem"></div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('entry-modal')">Annuler</button>
            <button class="btn btn-primary" onclick="saveEntry()">Enregistrer</button>
        </div>
    </div>
</div>

<!-- Nouvelle personne de confiance -->
<div class="modal-overlay" id="trustee-modal" style="display:none">
    <div class="modal">
        <div class="modal-header">
            <h3>Ajouter une personne de confiance</h3>
            <button onclick="closeModal('trustee-modal')">✕</button>
        </div>
        <div class="modal-body">
            <div class="form-row">
                <div class="form-group">
                    <label>Nom <span style="color:var(--danger)">*</span></label>
                    <input type="text" id="trustee-name">
                </div>
                <div class="form-group">
                    <label>E-mail <span style="color:var(--danger)">*</span></label>
                    <input type="email" id="trustee-email">
                </div>
            </div>
            <div class="form-group">
                <label>Lien avec la famille</label>
                <input type="text" id="trustee-relationship" placeholder="Frère, notaire, ami proche…">
            </div>
            <div class="form-group">
                <label>Notes</label>
                <input type="text" id="trustee-notes">
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('trustee-modal')">Annuler</button>
            <button class="btn btn-primary" onclick="saveTrustee()">Enregistrer</button>
        </div>
    </div>
</div>

<script>
const BASE_URL = <?= json_encode(BASE_URL) ?>;
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
