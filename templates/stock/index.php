<?php
$pageTitle = 'Stock frigo/congélo';
$extraJs = ['stock.js'];
ob_start();

use App\Models\StockItem;
use App\Core\DateHelper;

$statusLabel = [
    'ok'       => ['label' => 'OK', 'color' => '#27AE60'],
    'soon'     => ['label' => 'Bientôt périmé', 'color' => '#F39C12'],
    'critical' => ['label' => 'Périme bientôt', 'color' => '#E67E22'],
    'expired'  => ['label' => 'Périmé', 'color' => '#E74C3C'],
    'none'     => ['label' => '', 'color' => 'var(--text-muted)'],
];
$expiringSoon = array_filter($items, fn($i) => in_array($i['status'], ['critical', 'expired'], true));
?>
<div class="tasks-main" style="width:100%">
    <div class="tasks-header">
        <h2>🧊 Stock frigo/congélo</h2>
        <button class="btn btn-primary" onclick="openStockModal()">+ Ajouter un article</button>
    </div>

    <?php if (!empty($expiringSoon)): ?>
    <div class="card" style="background:color-mix(in srgb, #E74C3C 10%, transparent);border-left:3px solid #E74C3C;border-radius:6px;padding:.75rem 1rem;margin-bottom:1rem">
        ⚠️ <strong><?= count($expiringSoon) ?> article(s)</strong> périmé(s) ou à consommer rapidement :
        <?= implode(', ', array_map(fn($i) => htmlspecialchars($i['name']), $expiringSoon)) ?>
    </div>
    <?php endif; ?>

    <div class="settings-tabs">
        <a href="<?= BASE_URL ?>/stock" class="settings-tab-btn <?= $location === '' ? 'active' : '' ?>">Tout</a>
        <?php foreach (StockItem::LOCATIONS as $slug => $meta): ?>
            <a href="<?= BASE_URL ?>/stock?location=<?= $slug ?>" class="settings-tab-btn <?= $location === $slug ? 'active' : '' ?>"><?= $meta['icon'] ?> <?= $meta['label'] ?></a>
        <?php endforeach; ?>
    </div>

    <div class="card settings-section">
        <form method="GET" action="<?= BASE_URL ?>/stock" style="margin-bottom:1rem">
            <input type="hidden" name="location" value="<?= htmlspecialchars($location) ?>">
            <input type="search" name="q" placeholder="🔍 Rechercher un article…" value="<?= htmlspecialchars($search) ?>" style="max-width:320px">
        </form>

        <?php if (empty($items)): ?>
            <p class="empty-state">Aucun article dans le stock. Ajoutez ce que vous avez au frigo ou au congélateur.</p>
        <?php else: ?>
        <table class="admin-table">
            <thead><tr><th>Article</th><th>Lieu</th><th>Catégorie</th><th>Quantité</th><th>Péremption</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($items as $it): ?>
                <?php $st = $statusLabel[$it['status']] ?? $statusLabel['none']; $loc = StockItem::LOCATIONS[$it['location']]; ?>
                <tr data-id="<?= $it['id'] ?>">
                    <td><strong><?= htmlspecialchars($it['name']) ?></strong></td>
                    <td><?= $loc['icon'] ?> <?= $loc['label'] ?></td>
                    <td><?= htmlspecialchars($it['category'] ?? '—') ?></td>
                    <td>
                        <div style="display:flex;align-items:center;gap:.4rem">
                            <button class="btn-icon-sm" onclick="adjustStockQuantity(<?= $it['id'] ?>, -1)" title="Retirer 1">−</button>
                            <span class="stock-qty-value" style="min-width:2.2rem;text-align:center"><?= $it['quantity'] ?><?= $it['unit'] ? ' ' . htmlspecialchars($it['unit']) : '' ?></span>
                            <button class="btn-icon-sm" onclick="adjustStockQuantity(<?= $it['id'] ?>, 1)" title="Ajouter 1">+</button>
                        </div>
                    </td>
                    <td>
                        <?php if ($it['expiry_date']): ?>
                            <span style="color:<?= $st['color'] ?>;font-weight:<?= in_array($it['status'], ['critical', 'expired'], true) ? '600' : '400' ?>">
                                <?= DateHelper::format($it['expiry_date'], 'd/m/Y') ?><?= $st['label'] ? ' — ' . $st['label'] : '' ?>
                            </span>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                    <td>
                        <button class="btn-icon" title="Modifier" data-item='<?= htmlspecialchars(json_encode($it), ENT_QUOTES) ?>' onclick="openEditStockModal(JSON.parse(this.dataset.item))">✏️</button>
                        <button class="btn-icon" title="Supprimer" onclick="deleteStockItem(<?= $it['id'] ?>)">🗑</button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<!-- Nouveau/modifier article -->
<div class="modal-overlay" id="stock-modal" style="display:none">
    <div class="modal">
        <div class="modal-header">
            <h3 id="stock-modal-title">Nouvel article</h3>
            <button onclick="closeModal('stock-modal')">✕</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="stock-id">
            <div class="form-group">
                <label>Nom</label>
                <input type="text" id="stock-name" placeholder="Lait, steaks hachés, épinards surgelés…">
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Emplacement</label>
                    <select id="stock-location">
                        <?php foreach (StockItem::LOCATIONS as $slug => $meta): ?>
                            <option value="<?= $slug ?>"><?= $meta['icon'] ?> <?= $meta['label'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Catégorie (optionnel)</label>
                    <input type="text" id="stock-category" placeholder="Produits laitiers, viande…">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Quantité</label>
                    <input type="number" id="stock-quantity" min="0" value="1">
                </div>
                <div class="form-group">
                    <label>Unité (optionnel)</label>
                    <input type="text" id="stock-unit" placeholder="pièces, g, L…">
                </div>
                <div class="form-group">
                    <label>Date de péremption (optionnel)</label>
                    <input type="date" id="stock-expiry">
                </div>
            </div>
            <div class="form-group">
                <label>Notes</label>
                <input type="text" id="stock-notes">
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('stock-modal')">Annuler</button>
            <button class="btn btn-primary" onclick="saveStockItem()">Enregistrer</button>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
