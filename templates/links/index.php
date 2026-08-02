<?php
$pageTitle = 'Portail de liens';
$extraJs   = ['links.js'];
$isAdmin   = $user['role'] === 'admin';
ob_start();

$approved = array_values(array_filter($links, fn($l) => $l['status'] === 'approved'));
$pending  = array_values(array_filter($links, fn($l) => $l['status'] === 'pending'));
$rejected = array_values(array_filter($links, fn($l) => $l['status'] === 'rejected'));

function _linkCard(array $l, bool $isAdmin, bool $showStatus): string
{
    ob_start();
    $domain = parse_url($l['url'], PHP_URL_HOST) ?: $l['url'];
    ?>
    <div class="link-card">
        <?php if ($showStatus && $l['status'] !== 'approved'): ?>
            <span class="link-card-status status-<?= $l['status'] ?>"><?= $l['status'] === 'pending' ? 'En attente' : 'Refusé' ?></span>
        <?php endif; ?>
        <?php if (!empty($l['visible_to_coparent'])): ?>
            <span class="link-card-coparent-badge" title="Visible par un accès co-parent">🔒</span>
        <?php endif; ?>
        <a href="<?= $l['status'] === 'approved' ? BASE_URL . '/links/' . (int)$l['id'] . '/go' : '#' ?>"
           <?= $l['status'] === 'approved' ? 'target="_blank" rel="noopener noreferrer"' : 'onclick="return false" style="cursor:default"' ?>>
            <div class="link-card-thumb <?= $l['image_path'] ? 'has-image' : '' ?>"
                 <?= $l['image_path'] ? 'style="background-image:url(\'' . htmlspecialchars(BASE_URL . $l['image_path']) . '\')"' : '' ?>>
                <?= $l['image_path'] ? '' : '🔗' ?>
            </div>
        </a>
        <div class="link-card-body">
            <div class="link-card-title">
                <?php if ($l['status'] === 'approved'): ?>
                <a href="<?= BASE_URL ?>/links/<?= (int)$l['id'] ?>/go" target="_blank" rel="noopener noreferrer" style="color:inherit;text-decoration:none">
                    <?= htmlspecialchars($l['title']) ?>
                </a>
                <?php else: ?>
                    <?= htmlspecialchars($l['title']) ?>
                <?php endif; ?>
            </div>
            <div class="link-card-domain">🌐 <?= htmlspecialchars($domain) ?></div>
            <?php if (!empty($l['description'])): ?><div class="link-card-desc"><?= htmlspecialchars($l['description']) ?></div><?php endif; ?>
            <?php if ($l['status'] === 'rejected' && $l['rejection_reason']): ?>
                <div class="link-card-reject-reason">Motif : <?= htmlspecialchars($l['rejection_reason']) ?></div>
            <?php endif; ?>
            <div class="link-card-footer">
                <span class="link-card-clicks">👆 <?= (int)$l['click_count'] ?> clic(s)</span>
                <?php if ($isAdmin && $l['status'] === 'pending'): ?>
                    <span style="display:flex;gap:.3rem">
                        <button class="btn btn-primary btn-sm" onclick="approveLink(<?= (int)$l['id'] ?>)">✓</button>
                        <button class="btn btn-danger btn-sm" onclick="rejectLink(<?= (int)$l['id'] ?>)">✕</button>
                    </span>
                <?php elseif ($isAdmin): ?>
                    <button class="btn btn-danger btn-sm" onclick="deleteLink(<?= (int)$l['id'] ?>)">🗑️</button>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
?>
<div class="content-header">
    <h2>🔗 Portail de liens</h2>
    <button class="btn btn-primary" onclick="openLinkModal()"><?= $isAdmin ? '+ Ajouter un lien' : '+ Proposer un lien' ?></button>
</div>
<p style="color:var(--text-muted);font-size:.85rem;margin-bottom:1.25rem">
    <?php if ($isAdmin): ?>
        Les liens que vous ajoutez sont publiés immédiatement. Les liens proposés par les membres
        de la famille (ou par un accès co-parent) attendent votre validation ci-dessous. Chaque
        lien est vérifié automatiquement avant publication (accessibilité, adresse autorisée).
    <?php else: ?>
        Proposez un lien utile à toute la famille — un administrateur doit le valider avant qu'il
        n'apparaisse pour tout le monde. Vous pouvez suivre le statut de vos propositions ici.
    <?php endif; ?>
</p>

<?php if ($isAdmin && !empty($pending)): ?>
<h3 style="margin-bottom:.25rem">⏳ En attente de validation (<?= count($pending) ?>)</h3>
<div class="links-grid" style="margin-bottom:1.75rem">
    <?php foreach ($pending as $l) echo _linkCard($l, $isAdmin, true); ?>
</div>
<?php endif; ?>

<?php if ($isAdmin): ?><h3 style="margin-bottom:.25rem">✅ Publiés (<?= count($approved) ?>)</h3><?php endif; ?>
<?php if (empty($approved)): ?>
    <div class="empty-state-full">
        <div style="font-size:3rem">🔗</div>
        <p>Aucun lien publié pour l'instant.</p>
    </div>
<?php else: ?>
<div class="links-grid">
    <?php foreach ($approved as $l) echo _linkCard($l, $isAdmin, false); ?>
</div>
<?php endif; ?>

<?php if (!$isAdmin && (!empty($pending) || !empty($rejected))): ?>
<h3 style="margin:1.75rem 0 .25rem">📤 Mes propositions</h3>
<div class="links-grid">
    <?php foreach (array_merge($pending, $rejected) as $l) echo _linkCard($l, $isAdmin, true); ?>
</div>
<?php endif; ?>

<!-- Add/propose modal -->
<div class="modal-overlay" id="link-modal" style="display:none">
    <div class="modal">
        <div class="modal-header">
            <h3><?= $isAdmin ? 'Ajouter un lien' : 'Proposer un lien' ?></h3>
            <button onclick="closeModal('link-modal')">✕</button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label>Adresse du site</label>
                <input type="url" id="link-url" placeholder="https://exemple.fr" required>
            </div>
            <div class="form-group">
                <label>Titre</label>
                <input type="text" id="link-title" placeholder="Portail de l'école, mutuelle...">
                <small style="color:var(--text-muted)">Laissez vide pour utiliser le titre détecté automatiquement.</small>
            </div>
            <div class="form-group">
                <label>Description (optionnel)</label>
                <textarea id="link-description" rows="2"></textarea>
            </div>
            <div class="form-group">
                <label class="radio-option">
                    <input type="checkbox" id="link-coparent">
                    <span>Visible par un accès co-parent</span>
                </label>
            </div>
            <p id="link-modal-error" style="color:var(--danger,#E74C3C);font-size:.82rem;display:none"></p>
        </div>
        <div class="modal-footer">
            <button class="btn btn-primary" id="link-modal-submit" onclick="submitLink()"><?= $isAdmin ? 'Ajouter' : 'Proposer' ?></button>
            <button class="btn btn-secondary" onclick="closeModal('link-modal')">Annuler</button>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
