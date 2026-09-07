<?php
$pageTitle = 'Bons plans';
$extraJs = ['deals.js'];
ob_start();

use App\Models\Deal;

$today = date('Y-m-d');
?>
<div class="tasks-main" style="width:100%">
    <div class="tasks-header">
        <h2>🏷️ Bons plans</h2>
        <div style="display:flex;gap:.4rem;flex-wrap:wrap">
            <select id="deal-filter-category" onchange="filterDeals()">
                <option value="">— Toutes les catégories —</option>
                <?php foreach (Deal::CATEGORIES as $slug => $c): ?>
                    <option value="<?= $slug ?>"><?= $c['icon'] ?> <?= htmlspecialchars($c['label']) ?></option>
                <?php endforeach; ?>
            </select>
            <label style="display:flex;align-items:center;gap:.3rem;font-weight:normal">
                <input type="checkbox" id="deal-filter-hide-expired" checked onchange="filterDeals()"> Masquer les expirés
            </label>
            <button class="btn btn-primary btn-sm" onclick="openNewDealModal()">+ Nouveau bon plan</button>
        </div>
    </div>

    <div id="deals-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:1rem">
        <?php foreach ($deals as $d): ?>
        <?php
            $isExpired = $d['expires_at'] && $d['expires_at'] < $today;
            $cat = Deal::CATEGORIES[$d['category']] ?? Deal::CATEGORIES['autre'];
        ?>
        <div class="card deal-card" style="padding:1rem<?= $isExpired ? ';opacity:.55' : '' ?>" data-deal-id="<?= $d['id'] ?>" data-category="<?= htmlspecialchars($d['category']) ?>" data-expired="<?= $isExpired ? '1' : '0' ?>">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:.5rem">
                <div>
                    <span class="badge"><?= $cat['icon'] ?> <?= htmlspecialchars($cat['label']) ?></span>
                    <?php if ($isExpired): ?><span class="badge" style="color:var(--danger)">Expiré</span><?php endif; ?>
                </div>
                <div style="display:flex;gap:.3rem;flex-shrink:0">
                    <button class="btn-icon" title="Modifier" onclick='openEditDealModal(<?= json_encode($d) ?>)'>✏️</button>
                    <button class="btn-icon" title="Supprimer" onclick="deleteDeal(<?= $d['id'] ?>)">🗑</button>
                </div>
            </div>
            <h3 style="margin:.5rem 0 .25rem"><?= htmlspecialchars($d['title']) ?></h3>
            <?php if ($d['description']): ?>
                <p style="color:var(--text-muted);font-size:.85rem;white-space:pre-wrap"><?= htmlspecialchars($d['description']) ?></p>
            <?php endif; ?>
            <?php if ($d['discount'] || $d['promo_code']): ?>
            <div style="display:flex;gap:.4rem;flex-wrap:wrap;margin:.5rem 0">
                <?php if ($d['discount']): ?><span class="badge" style="background:var(--success);color:#fff"><?= htmlspecialchars($d['discount']) ?></span><?php endif; ?>
                <?php if ($d['promo_code']): ?><span class="badge" style="font-family:monospace">🎟️ <?= htmlspecialchars($d['promo_code']) ?></span><?php endif; ?>
            </div>
            <?php endif; ?>
            <?php if ($d['expires_at']): ?>
                <p style="font-size:.75rem;color:var(--text-muted);margin:.3rem 0">Valable jusqu'au <?= (new DateTime($d['expires_at']))->format('d/m/Y') ?></p>
            <?php endif; ?>
            <?php if ($d['url']): ?>
                <a href="<?= htmlspecialchars($d['url']) ?>" target="_blank" rel="noopener" class="btn btn-secondary btn-sm" style="margin-top:.4rem">Voir l'offre ↗</a>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php if (empty($deals)): ?>
        <p class="empty-state">Aucun bon plan enregistré. Ajoutez le premier avec le bouton ci-dessus.</p>
    <?php endif; ?>
</div>

<!-- Nouveau/modifier bon plan -->
<div class="modal-overlay" id="deal-modal" style="display:none">
    <div class="modal">
        <div class="modal-header">
            <h3 id="deal-modal-title">Nouveau bon plan</h3>
            <button onclick="closeModal('deal-modal')">✕</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="deal-id">
            <div class="form-group">
                <label>Titre <span style="color:var(--danger)">*</span></label>
                <input type="text" id="deal-title" placeholder="Réduction piscine municipale…">
            </div>
            <div class="form-group">
                <label>Description</label>
                <textarea id="deal-description" rows="3"></textarea>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Catégorie</label>
                    <select id="deal-category">
                        <?php foreach (Deal::CATEGORIES as $slug => $c): ?>
                            <option value="<?= $slug ?>"><?= $c['icon'] ?> <?= htmlspecialchars($c['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Date de validité</label>
                    <input type="date" id="deal-expires">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Réduction</label>
                    <input type="text" id="deal-discount" placeholder="-20%, 2 achetés = 1 offert…">
                </div>
                <div class="form-group">
                    <label>Code promo</label>
                    <input type="text" id="deal-promo-code" placeholder="ETE2026">
                </div>
            </div>
            <div class="form-group">
                <label>Lien</label>
                <input type="text" id="deal-url" placeholder="www.exemple.fr">
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('deal-modal')">Annuler</button>
            <button class="btn btn-primary" onclick="saveDeal()">Enregistrer</button>
        </div>
    </div>
</div>

<script>
const BASE_URL = <?= json_encode(BASE_URL) ?>;
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
