<?php
$pageTitle = 'Liste de cadeaux';
ob_start();
?>
<!-- .auth-wrapper (layout.php, visiteur non connecté) est un flex-row pensé pour une seule
     carte centrée : ce wrapper column permet d'empiler la carte principale et la bannière
     promo en dessous plutôt qu'à côté. -->
<div style="display:flex;flex-direction:column;align-items:center;gap:1rem;width:100%">
<div class="card" style="max-width:1100px;margin:2rem auto 0;padding:1.5rem;width:100%">
<?php if (!$child): ?>
    <h2>⛔ Lien invalide</h2>
    <p style="color:var(--text-muted)">Ce lien de liste de cadeaux n'existe pas. Contactez la famille pour en obtenir un nouveau.</p>
<?php else: ?>
    <h2>🎁 Liste de cadeaux de <?= htmlspecialchars($child['name']) ?> — <?= htmlspecialchars($family['name'] ?? 'Famille') ?></h2>
    <p style="color:var(--text-muted);font-size:.9rem">
        Cliquez sur « Je le prends » pour réserver un cadeau — les autres visiteurs verront
        immédiatement qu'il n'est plus disponible, pour éviter les doublons. La famille ne voit
        jamais ce qui a été réservé : la surprise est garantie !
    </p>

    <div id="wishlist-child-items" class="gift-grid">
        <?php foreach ($items as $item): ?>
            <?php $reserved = !empty($item['reservation_token']); ?>
            <div class="card" data-item-id="<?= $item['id'] ?>" style="padding:1rem;display:flex;flex-direction:column;gap:.5rem;<?= $reserved ? 'opacity:.6' : '' ?>">
                <?php if ($item['image_path']): ?>
                    <img src="<?= BASE_URL . htmlspecialchars($item['image_path']) ?>" alt="" style="width:100%;aspect-ratio:1;object-fit:cover;border-radius:8px">
                <?php endif; ?>
                <strong><?= htmlspecialchars($item['title']) ?></strong>
                <?php if ($item['price']): ?><div style="color:var(--text-muted);font-size:.85rem"><?= number_format((float)$item['price'], 2, ',', ' ') ?> €</div><?php endif; ?>
                <?php if ($item['description']): ?><div style="font-size:.85rem;white-space:pre-wrap"><?= htmlspecialchars($item['description']) ?></div><?php endif; ?>

                <div class="gift-actions">
                    <?php if ($item['url']): ?><a class="btn btn-secondary btn-sm" href="<?= htmlspecialchars($item['url']) ?>" target="_blank" rel="noopener noreferrer nofollow">🔗 Voir le produit</a><?php endif; ?>
                    <div class="item-status">
                        <?php if ($reserved): ?>
                            <span style="color:var(--danger)">🎁 Réservé par <?= htmlspecialchars($item['reserved_by_name']) ?></span>
                        <?php else: ?>
                            <button class="btn btn-primary btn-sm" onclick="reserveChildWishlistItem(<?= $item['id'] ?>)">Je le prends</button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        <?php if (empty($items)): ?>
            <p class="empty-state">La liste ne contient encore aucun article.</p>
        <?php endif; ?>
    </div>
<?php endif; ?>
</div>

<?php if ($child): $bannerFamilyId = (int)$child['family_id']; require BASE_PATH . '/templates/partials/public_signup_banner.php'; endif; ?>
</div>

<style>
/* minmax(0, 1fr) et non 1fr seul : un 1fr "nu" a un minimum implicite de auto, donc une
   colonne ne rétrécit jamais sous la taille de son contenu (titre long, image...) — c'est ce
   qui faisait déborder la dernière colonne hors du cadre blanc plutôt que de s'y adapter. */
.gift-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 1rem; margin-top: 1.25rem; }
.gift-grid > .card { min-width: 0; }
.gift-actions { display: flex; align-items: center; gap: .5rem; margin-top: auto; }
.gift-actions .btn, .gift-actions .item-status { flex: 1; min-width: 0; }
.gift-actions .item-status button { width: 100%; }
@media (max-width: 900px) { .gift-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
@media (max-width: 560px) { .gift-grid { grid-template-columns: minmax(0, 1fr); } }
</style>

<script>
const LIST_TOKEN = <?= json_encode($params['token'] ?? '') ?>;
const STORAGE_KEY = 'fb-child-wishlist-reservations-' + LIST_TOKEN;

function myReservations() {
    try { return JSON.parse(localStorage.getItem(STORAGE_KEY) || '{}'); } catch (e) { return {}; }
}
function saveMyReservation(itemId, token) {
    const map = myReservations();
    map[itemId] = token;
    try { localStorage.setItem(STORAGE_KEY, JSON.stringify(map)); } catch (e) {}
}
function forgetMyReservation(itemId) {
    const map = myReservations();
    delete map[itemId];
    try { localStorage.setItem(STORAGE_KEY, JSON.stringify(map)); } catch (e) {}
}

function renderMyReservations() {
    const map = myReservations();
    Object.keys(map).forEach(itemId => {
        const card = document.querySelector(`[data-item-id="${itemId}"] .item-status`);
        if (card && card.querySelector('button')) {
            card.innerHTML = '<span style="color:var(--success)">✅ Réservé par vous</span> '
                + `<button class="btn btn-secondary btn-sm" onclick="unreserveChildWishlistItem(${itemId})">Annuler</button>`;
        }
    });
}
renderMyReservations();

async function reserveChildWishlistItem(itemId) {
    const name = prompt('Votre prénom (pour que les autres visiteurs sachent qui a réservé) :');
    if (!name || !name.trim()) return;
    const res = await fetch(`${BASE_URL}/liste-cadeaux/${LIST_TOKEN}/items/${itemId}/reserve`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify({ name: name.trim() }),
    }).then(r => r.json());
    if (res.success) {
        saveMyReservation(itemId, res.reservation_token);
        window.location.reload();
    } else {
        alert(res.error || 'Erreur.');
        window.location.reload();
    }
}

async function unreserveChildWishlistItem(itemId) {
    const map = myReservations();
    const token = map[itemId];
    if (!token) return;
    const res = await fetch(`${BASE_URL}/liste-cadeaux/${LIST_TOKEN}/items/${itemId}/unreserve`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify({ reservation_token: token }),
    }).then(r => r.json());
    if (res.success) forgetMyReservation(itemId);
    window.location.reload();
}
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
