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

// Souhaits des familles amies (foyers séparés) : regroupés par famille puis par propriétaire,
// à partir des colonnes owner_*/family_name déjà jointes par WishlistItem::getByFamily() —
// pas besoin de la liste $members de ces familles-là.
$friendByFamily = [];
foreach ($friendItems as $it) {
    $fid = (int)$it['family_id'];
    if (!isset($friendByFamily[$fid])) $friendByFamily[$fid] = ['family_name' => $it['family_name'], 'byOwner' => []];
    $uid = (int)$it['user_id'];
    if (!isset($friendByFamily[$fid]['byOwner'][$uid])) {
        $friendByFamily[$fid]['byOwner'][$uid] = [
            'owner' => ['name' => $it['owner_name'], 'color' => $it['owner_color']],
            'items' => [],
        ];
    }
    $friendByFamily[$fid]['byOwner'][$uid]['items'][] = $it;
}
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
        <div class="wishlist-card <?= !empty($it['is_purchased']) ? 'wishlist-purchased' : '' ?>" data-item-id="<?= (int)$it['id'] ?>">
            <?php if ($it['image_path']): ?><img src="<?= BASE_URL . htmlspecialchars($it['image_path']) ?>" alt="" style="width:100%;aspect-ratio:16/9;object-fit:cover;border-radius:8px;margin-bottom:.4rem"><?php endif; ?>
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
                            <span class="badge-custom">🎀 Réservé par <?= htmlspecialchars($it['reserved_by_user_name']) ?></span>
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

<?php if (!empty($friendByFamily)): ?>
<h3 style="margin:1.5rem 0 .75rem">🤝 Familles amies</h3>
<p style="color:var(--text-muted);font-size:.82rem;margin-bottom:1rem">
    Lecture seule — vous pouvez réserver un cadeau pour éviter un doublon entre les deux foyers,
    mais pas modifier ni supprimer un souhait qui n'est pas le vôtre.
</p>
<?php foreach ($friendByFamily as $fgroup): ?>
<div class="card settings-section" style="margin-bottom:1.25rem">
    <h4 style="margin-bottom:.75rem"><?= htmlspecialchars($fgroup['family_name']) ?></h4>
    <?php foreach ($fgroup['byOwner'] as $group): $owner = $group['owner']; ?>
    <div style="margin-bottom:1rem">
        <div style="display:flex;align-items:center;gap:.5rem;margin-bottom:.5rem;font-weight:600">
            <span class="user-avatar" style="width:1.6rem;height:1.6rem;font-size:.8rem;background:<?= htmlspecialchars($owner['color']) ?>">
                <?= mb_substr($owner['name'], 0, 1) ?>
            </span>
            <?= htmlspecialchars($owner['name']) ?>
        </div>
        <div class="wishlist-grid">
            <?php foreach ($group['items'] as $it): ?>
            <div class="wishlist-card <?= !empty($it['is_purchased']) ? 'wishlist-purchased' : '' ?>" data-item-id="<?= (int)$it['id'] ?>">
                <?php if ($it['image_path']): ?><img src="<?= BASE_URL . htmlspecialchars($it['image_path']) ?>" alt="" style="width:100%;aspect-ratio:16/9;object-fit:cover;border-radius:8px;margin-bottom:.4rem"><?php endif; ?>
                <div class="wishlist-card-head">
                    <strong><?= htmlspecialchars($it['title']) ?></strong>
                    <span class="badge-custom"><?= $priorityLabel[$it['priority']] ?? '' ?></span>
                </div>
                <?php if ($it['description']): ?><p style="color:var(--text-muted);font-size:.85rem"><?= nl2br(htmlspecialchars($it['description'])) ?></p><?php endif; ?>
                <?php if ($it['price']): ?><p style="font-size:.85rem">💰 <?= number_format((float)$it['price'], 2, ',', ' ') ?> €</p><?php endif; ?>
                <?php if ($it['url']): ?><p><a href="<?= htmlspecialchars($it['url']) ?>" target="_blank" rel="noopener noreferrer nofollow">🔗 Voir le produit</a></p><?php endif; ?>
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
                            <span class="badge-custom">🎀 Réservé par <?= htmlspecialchars($it['reserved_by_user_name']) ?></span>
                        <?php endif; ?>
                    <?php else: ?>
                        <button class="btn btn-primary btn-sm" onclick="reserveWishlistItem(<?= (int)$it['id'] ?>)">🎀 Réserver ce cadeau</button>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endforeach; ?>
<?php endif; ?>

<h3 style="display:flex;align-items:center;justify-content:space-between;margin:1.5rem 0 .75rem">
    🧒 Listes des enfants
    <button class="btn btn-secondary btn-sm" onclick="openNewWishlistChildModal()">+ Ajouter un enfant</button>
</h3>
<p style="color:var(--text-muted);font-size:.82rem;margin-bottom:1rem">
    Pour un enfant qui n'a pas de compte : vous gérez sa liste depuis ici, et partagez le lien
    public à la famille/aux proches pour qu'ils réservent un cadeau en toute discrétion —
    <strong>vous ne voyez jamais ce qui a été réservé</strong>, la surprise est garantie, comme pour
    vos propres souhaits.
</p>
<?php if (empty($familyChildren)): ?>
    <p class="empty-state">Aucun enfant enregistré pour l'instant.</p>
<?php endif; ?>
<?php foreach ($familyChildren as $fc): $childId = (int)$fc['id']; ?>
<div class="card settings-section" style="margin-bottom:1.25rem">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.5rem">
        <h3 style="display:flex;align-items:center;gap:.5rem;margin:0">
            <span class="user-avatar" style="width:1.6rem;height:1.6rem;font-size:.8rem;background:<?= htmlspecialchars($fc['color']) ?>">
                <?= mb_substr($fc['name'], 0, 1) ?>
            </span>
            <?= htmlspecialchars($fc['name']) ?>
        </h3>
        <div style="display:flex;gap:.4rem">
            <button class="btn btn-secondary btn-sm" onclick="copyChildWishlistLink('<?= htmlspecialchars($childTokens[$childId]) ?>')">🔗 Copier le lien</button>
            <button class="btn btn-primary btn-sm" onclick="openWishlistModal(null, <?= $childId ?>)">+ Ajouter un souhait</button>
        </div>
    </div>
    <?php if (empty($childItems[$childId])): ?>
        <p class="empty-state">Aucun souhait pour l'instant.</p>
    <?php else: ?>
    <div class="wishlist-grid">
        <?php foreach ($childItems[$childId] as $it): ?>
        <div class="wishlist-card <?= !empty($it['is_purchased']) ? 'wishlist-purchased' : '' ?>" data-item-id="<?= (int)$it['id'] ?>">
            <?php if ($it['image_path']): ?><img src="<?= BASE_URL . htmlspecialchars($it['image_path']) ?>" alt="" style="width:100%;aspect-ratio:16/9;object-fit:cover;border-radius:8px;margin-bottom:.4rem"><?php endif; ?>
            <div class="wishlist-card-head">
                <strong><?= htmlspecialchars($it['title']) ?></strong>
                <span class="badge-custom"><?= $priorityLabel[$it['priority']] ?? '' ?></span>
            </div>
            <?php if ($it['description']): ?><p style="color:var(--text-muted);font-size:.85rem"><?= nl2br(htmlspecialchars($it['description'])) ?></p><?php endif; ?>
            <?php if ($it['price']): ?><p style="font-size:.85rem">💰 <?= number_format((float)$it['price'], 2, ',', ' ') ?> €</p><?php endif; ?>
            <?php if ($it['url']): ?><p><a href="<?= htmlspecialchars($it['url']) ?>" target="_blank" rel="noopener noreferrer nofollow">🔗 Voir le produit</a></p><?php endif; ?>
            <div style="display:flex;gap:.4rem;margin-top:.5rem">
                <button class="btn btn-secondary btn-sm" onclick="openWishlistModal(<?= htmlspecialchars(json_encode($it), ENT_QUOTES) ?>, <?= $childId ?>)">✏️ Modifier</button>
                <button class="btn btn-danger btn-sm" onclick="deleteWishlistItem(<?= (int)$it['id'] ?>)">🗑️</button>
            </div>
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
            <input type="hidden" id="wl-family-child-id">
            <div class="form-group">
                <label>Lien du produit</label>
                <input type="url" id="wl-url" placeholder="https://...">
                <p style="color:var(--text-muted);font-size:.78rem;margin:.3rem 0 0">
                    Collez le lien d'un article en ligne : le titre, le prix, la description et
                    l'image sont récupérés automatiquement si vous laissez les champs ci-dessous vides.
                </p>
            </div>
            <div id="wl-image-preview" style="display:none;margin-bottom:.5rem">
                <img id="wl-image-preview-img" src="" alt="" style="width:100%;max-height:160px;object-fit:cover;border-radius:8px">
                <label style="display:flex;align-items:center;gap:.4rem;font-size:.8rem;margin-top:.3rem;font-weight:normal">
                    <input type="checkbox" id="wl-remove-image"> Retirer cette image
                </label>
            </div>
            <div class="form-group">
                <label>Image (si elle n'a pas pu être récupérée automatiquement)</label>
                <input type="file" id="wl-image-file" accept="image/*">
            </div>
            <div class="form-group">
                <label>Titre</label>
                <input type="text" id="wl-title" placeholder="Un livre, une console... (ou laissez vide si un lien est collé)">
            </div>
            <div class="form-group">
                <label>Description</label>
                <textarea id="wl-description" rows="2" placeholder="Couleur, taille, référence..."></textarea>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Prix approximatif (€)</label>
                    <input type="number" id="wl-price" min="0" step="0.01">
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
        </div>
        <div class="modal-footer">
            <button class="btn btn-primary" onclick="saveWishlistItem()">Enregistrer</button>
            <button class="btn btn-secondary" onclick="closeModal('wishlist-modal')">Annuler</button>
        </div>
    </div>
</div>

<!-- Nouvel enfant -->
<div class="modal-overlay" id="wishlist-child-modal" style="display:none">
    <div class="modal">
        <div class="modal-header">
            <h3>Ajouter un enfant</h3>
            <button onclick="closeModal('wishlist-child-modal')">✕</button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label>Prénom</label>
                <input type="text" id="wlc-name" placeholder="Prénom de l'enfant">
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-primary" onclick="saveWishlistChild()">Enregistrer</button>
            <button class="btn btn-secondary" onclick="closeModal('wishlist-child-modal')">Annuler</button>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
