<?php
$pageTitle = 'Liste de cadeaux';
$extraJs   = ['wishlist.js'];
ob_start();

$byOwner = [];
foreach ($members as $m) $byOwner[$m['id']] = ['owner' => $m, 'items' => []];
foreach ($items as $it) {
    if (isset($byOwner[$it['user_id']])) $byOwner[$it['user_id']]['items'][] = $it;
}
$priorityLabel = ['low' => 'Envie', 'medium' => 'Souhaité', 'high' => 'Coup de cœur'];
?>
<div class="content-header">
    <h2>🎁 Liste de cadeaux</h2>
    <button class="btn btn-primary" onclick="openWishlistModal()">+ Ajouter un souhait</button>
</div>
<p style="color:var(--text-muted);font-size:.85rem;margin-bottom:1.25rem">
    Notez ce qui vous ferait plaisir : les autres membres de la famille peuvent réserver un cadeau
    en secret pour éviter les doublons — <strong>vous ne voyez jamais si l'un de vos souhaits est
    réservé ou déjà acheté</strong>, pour préserver la surprise.
</p>

<?php foreach ($byOwner as $group): $owner = $group['owner']; $isMe = (int)$owner['id'] === (int)$user['id']; ?>
<div class="card settings-section" style="margin-bottom:1.25rem">
    <h3 style="display:flex;align-items:center;gap:.5rem">
        <span class="user-avatar" style="width:1.6rem;height:1.6rem;font-size:.8rem;background:<?= htmlspecialchars($owner['color']) ?>">
            <?= mb_substr($owner['name'], 0, 1) ?>
        </span>
        <?= htmlspecialchars($owner['name']) ?><?= $isMe ? ' (moi)' : '' ?>
    </h3>
    <?php if (empty($group['items'])): ?>
        <p class="empty-state">Aucun souhait pour l'instant.</p>
    <?php else: ?>
    <div class="wishlist-grid">
        <?php foreach ($group['items'] as $it): ?>
        <div class="wishlist-card <?= !empty($it['is_purchased']) ? 'wishlist-purchased' : '' ?>">
            <div class="wishlist-card-head">
                <strong><?= htmlspecialchars($it['title']) ?></strong>
                <span class="badge-custom"><?= $priorityLabel[$it['priority']] ?? '' ?></span>
            </div>
            <?php if ($it['description']): ?><p style="color:var(--text-muted);font-size:.85rem"><?= nl2br(htmlspecialchars($it['description'])) ?></p><?php endif; ?>
            <?php if ($it['price']): ?><p style="font-size:.85rem">💰 <?= number_format((float)$it['price'], 2, ',', ' ') ?> €</p><?php endif; ?>
            <?php if ($it['url']): ?><p><a href="<?= htmlspecialchars($it['url']) ?>" target="_blank" rel="noopener noreferrer nofollow">🔗 Voir le produit</a></p><?php endif; ?>

            <?php if ($isMe): ?>
                <div style="display:flex;gap:.4rem;margin-top:.5rem">
                    <button class="btn btn-secondary btn-sm" onclick="openWishlistModal(<?= htmlspecialchars(json_encode($it), ENT_QUOTES) ?>)">✏️ Modifier</button>
                    <button class="btn btn-danger btn-sm" onclick="deleteWishlistItem(<?= (int)$it['id'] ?>)">🗑️</button>
                </div>
            <?php else: ?>
                <div style="margin-top:.5rem">
                    <?php if (!empty($it['is_purchased'])): ?>
                        <span class="badge-custom" style="background:var(--success)">✅ Acheté</span>
                        <?php if (!empty($it['is_reserved_by_me'])): ?>
                            <button class="btn btn-secondary btn-sm" onclick="setWishlistPurchased(<?= (int)$it['id'] ?>, false)">Annuler « acheté »</button>
                        <?php endif; ?>
                    <?php elseif (!empty($it['reserved_by'])): ?>
                        <?php if (!empty($it['is_reserved_by_me'])): ?>
                            <span class="badge-custom">🎀 Réservé par vous</span>
                            <button class="btn btn-primary btn-sm" onclick="setWishlistPurchased(<?= (int)$it['id'] ?>, true)">Marquer acheté</button>
                            <button class="btn btn-secondary btn-sm" onclick="unreserveWishlistItem(<?= (int)$it['id'] ?>)">Annuler</button>
                        <?php else: ?>
                            <span class="badge-custom">🎀 Réservé par <?= htmlspecialchars($it['reserved_by_name']) ?></span>
                        <?php endif; ?>
                    <?php else: ?>
                        <button class="btn btn-primary btn-sm" onclick="reserveWishlistItem(<?= (int)$it['id'] ?>)">🎀 Réserver ce cadeau</button>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
<?php endforeach; ?>

<!-- Add/edit modal -->
<div class="modal-overlay" id="wishlist-modal" style="display:none">
    <div class="modal">
        <div class="modal-header">
            <h3 id="wishlist-modal-title">Ajouter un souhait</h3>
            <button onclick="closeModal('wishlist-modal')">✕</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="wl-id">
            <div class="form-group">
                <label>Titre</label>
                <input type="text" id="wl-title" required placeholder="Un livre, une console...">
            </div>
            <div class="form-group">
                <label>Description</label>
                <textarea id="wl-description" rows="2" placeholder="Couleur, taille, référence..."></textarea>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Lien (optionnel)</label>
                    <input type="url" id="wl-url" placeholder="https://...">
                </div>
                <div class="form-group">
                    <label>Prix approximatif (€)</label>
                    <input type="number" id="wl-price" min="0" step="0.01">
                </div>
            </div>
            <div class="form-group">
                <label>Priorité</label>
                <select id="wl-priority">
                    <option value="low">Envie</option>
                    <option value="medium" selected>Souhaité</option>
                    <option value="high">Coup de cœur</option>
                </select>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-primary" onclick="saveWishlistItem()">Enregistrer</button>
            <button class="btn btn-secondary" onclick="closeModal('wishlist-modal')">Annuler</button>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
