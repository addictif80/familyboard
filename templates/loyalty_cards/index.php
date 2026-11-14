<?php
$pageTitle = 'Cartes de fidélité';
$extraJs = ['vendor/qrcode.min.js', 'vendor/jsbarcode.min.js', 'loyalty_cards.js'];
ob_start();

use App\Models\LoyaltyCard;
?>
<div class="tasks-container">
    <div class="tasks-sidebar">
        <div class="tasks-sidebar-header">
            <h3>Mes cartes</h3>
            <button class="btn-icon" onclick="openNewLoyaltyCardModal()" title="Nouvelle carte">+</button>
        </div>
        <ul class="lists-menu">
            <?php foreach ($cards as $c): ?>
                <li class="list-item <?= $selected && (int)$selected['id'] === (int)$c['id'] ? 'active' : '' ?>">
                    <a href="<?= BASE_URL ?>/loyalty-cards?id=<?= $c['id'] ?>" class="list-link">
                        <span class="list-dot" style="background:<?= htmlspecialchars($c['card_color']) ?>"></span>
                        <span class="list-name">💳 <?= htmlspecialchars($c['store_name']) ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
            <?php if (empty($cards)): ?>
                <li class="empty-state">Aucune carte de fidélité.</li>
            <?php endif; ?>
        </ul>
    </div>

    <div class="tasks-main">
        <?php if ($selected): ?>
            <div class="tasks-header">
                <div>
                    <h2>💳 <?= htmlspecialchars($selected['store_name']) ?></h2>
                </div>
                <div style="display:flex;gap:.4rem">
                    <button class="btn btn-secondary btn-sm" onclick='openEditLoyaltyCardModal(<?= json_encode($selected) ?>)'>✏️ Modifier</button>
                    <button class="btn btn-danger btn-sm" onclick="deleteLoyaltyCard(<?= $selected['id'] ?>)">🗑 Supprimer</button>
                </div>
            </div>

            <!-- Grande carte, prête à être présentée en caisse -->
            <div class="loyalty-card-display" style="background:<?= htmlspecialchars($selected['card_color']) ?>">
                <div class="loyalty-card-store"><?= htmlspecialchars($selected['store_name']) ?></div>
                <div class="loyalty-card-barcode-wrap" id="loyalty-barcode-wrap">
                    <?php if ($selected['barcode_format'] === 'qrcode'): ?>
                        <div id="loyalty-qrcode"></div>
                    <?php else: ?>
                        <svg id="loyalty-barcode"></svg>
                    <?php endif; ?>
                </div>
                <div class="loyalty-card-number"><?= htmlspecialchars($selected['barcode_value']) ?></div>
            </div>
            <?php if ($selected['notes']): ?>
                <p class="card settings-section" style="margin-top:1rem;white-space:pre-wrap"><?= htmlspecialchars($selected['notes']) ?></p>
            <?php endif; ?>

            <script>
            var LOYALTY_SELECTED = <?= json_encode(['value' => $selected['barcode_value'], 'format' => $selected['barcode_format']]) ?>;
            </script>
        <?php else: ?>
            <p class="empty-state">Ajoutez votre première carte de fidélité.</p>
        <?php endif; ?>
    </div>
</div>

<!-- Nouvelle/modifier carte -->
<div class="modal-overlay" id="loyalty-card-modal" style="display:none">
    <div class="modal">
        <div class="modal-header">
            <h3 id="loyalty-card-modal-title">Nouvelle carte</h3>
            <button onclick="closeModal('loyalty-card-modal')">✕</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="loyalty-card-id">
            <div class="form-row">
                <div class="form-group flex-2">
                    <label>Magasin / enseigne <span style="color:var(--danger)">*</span></label>
                    <input type="text" id="loyalty-card-store" placeholder="Carrefour, Fnac, Sephora…">
                </div>
                <div class="form-group">
                    <label>Couleur</label>
                    <input type="color" id="loyalty-card-color" value="#7C5CFF">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group flex-2">
                    <label>Numéro de carte <span style="color:var(--danger)">*</span></label>
                    <div style="display:flex;gap:.4rem">
                        <input type="text" id="loyalty-card-barcode" placeholder="Numéro inscrit sous le code-barres" style="flex:1">
                        <button type="button" class="btn btn-secondary" id="loyalty-card-scan-btn" onclick="LoyaltyCards.openScanner()" style="display:none" title="Scanner avec l'appareil photo">📷</button>
                    </div>
                </div>
                <div class="form-group">
                    <label>Type de code</label>
                    <select id="loyalty-card-format">
                        <?php foreach (LoyaltyCard::FORMATS as $slug => $label): ?>
                            <option value="<?= $slug ?>"><?= htmlspecialchars($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>Notes</label>
                <textarea id="loyalty-card-notes" rows="2" placeholder="Numéro de cagnotte, avantages, conditions…"></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('loyalty-card-modal')">Annuler</button>
            <button class="btn btn-primary" onclick="LoyaltyCards.save()">Enregistrer</button>
        </div>
    </div>
</div>

<!-- Scanner caméra (BarcodeDetector natif — n'apparaît que si le navigateur le supporte) -->
<div class="modal-overlay" id="loyalty-scanner-modal" style="display:none">
    <div class="modal">
        <div class="modal-header">
            <h3>📷 Scanner le code-barres</h3>
            <button onclick="LoyaltyCards.closeScanner()">✕</button>
        </div>
        <div class="modal-body">
            <video id="loyalty-scanner-video" autoplay playsinline muted style="width:100%;border-radius:12px;background:#000"></video>
            <p style="color:var(--text-muted);font-size:.82rem;margin-top:.6rem">Présentez le code-barres de la carte devant la caméra.</p>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="LoyaltyCards.closeScanner()">Annuler</button>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
