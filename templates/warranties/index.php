<?php
$pageTitle = 'Garanties & Factures';
$extraJs   = ['warranty.js'];
ob_start();

use App\Core\DateHelper;

$statusLabel = [
    'ok'       => ['label' => 'En cours',     'cls' => 'w-ok'],
    'soon'     => ['label' => 'Expire bientôt','cls' => 'w-soon'],
    'critical' => ['label' => 'Expire < 30j', 'cls' => 'w-critical'],
    'expired'  => ['label' => 'Expirée',       'cls' => 'w-expired'],
    'none'     => ['label' => 'Sans fin',      'cls' => 'w-none'],
];
?>
<div class="warranty-container">

    <!-- Toolbar -->
    <div class="warranty-toolbar">
        <div class="warranty-search-wrap">
            <input type="search" id="warranty-search" class="warranty-search" placeholder="🔍 Rechercher…" value="<?= htmlspecialchars($search) ?>" oninput="searchWarranties(this.value)">
        </div>
        <button class="btn btn-primary" onclick="openWarrantyModal()">+ Ajouter une garantie</button>
    </div>

    <!-- Expiring soon alert strip -->
    <?php if (!empty($expiringSoon)): ?>
    <div class="warranty-alert-strip">
        ⚠️ <strong><?= count($expiringSoon) ?> garantie<?= count($expiringSoon) > 1 ? 's' : '' ?> expire<?= count($expiringSoon) > 1 ? 'nt' : '' ?> dans les 90 jours :</strong>
        <?php foreach ($expiringSoon as $w): ?>
            <span class="warranty-alert-chip <?= $w['status'] === 'critical' ? 'w-critical' : 'w-soon' ?>">
                <?= htmlspecialchars($w['title']) ?>
                (<?= $w['days_remaining'] ?>j)
            </span>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Grid -->
    <div class="warranty-grid" id="warranty-grid">
        <?php if (empty($warranties)): ?>
            <div class="empty-state" style="grid-column:1/-1">
                Aucune garantie enregistrée. Ajoutez vos appareils, électroménagers, véhicules…
            </div>
        <?php else: ?>
        <?php foreach ($warranties as $w): ?>
        <?php $st = $statusLabel[$w['status']] ?? $statusLabel['none']; ?>
        <div class="warranty-card <?= $st['cls'] ?>" data-id="<?= $w['id'] ?>"
             data-search="<?= htmlspecialchars(strtolower($w['title'] . ' ' . $w['brand'] . ' ' . $w['model'] . ' ' . $w['serial_number'] . ' ' . $w['store'])) ?>">
            <div class="wc-header">
                <span class="wc-status-badge <?= $st['cls'] ?>"><?= $st['label'] ?></span>
                <div class="wc-actions">
                    <button class="btn-icon-sm" onclick="openEditWarrantyModal(<?= htmlspecialchars(json_encode($w)) ?>)" title="Modifier">✏️</button>
                    <button class="btn-icon-sm" onclick="deleteWarranty(<?= $w['id'] ?>)" title="Supprimer">🗑</button>
                </div>
            </div>

            <div class="wc-title"><?= htmlspecialchars($w['title']) ?></div>

            <?php if ($w['brand'] || $w['model']): ?>
                <div class="wc-meta"><?= htmlspecialchars(implode(' · ', array_filter([$w['brand'], $w['model']]))) ?></div>
            <?php endif; ?>

            <div class="wc-details">
                <?php if ($w['purchase_date']): ?>
                    <span>🛒 <?= DateHelper::format($w['purchase_date'], 'd/m/Y') ?></span>
                <?php endif; ?>
                <?php if ($w['warranty_end_date']): ?>
                    <span>📅 fin : <?= DateHelper::format($w['warranty_end_date'], 'd/m/Y') ?></span>
                <?php endif; ?>
                <?php if ($w['price']): ?>
                    <span>💶 <?= number_format((float)$w['price'], 2, ',', ' ') ?> €</span>
                <?php endif; ?>
                <?php if ($w['store']): ?>
                    <span>🏪 <?= htmlspecialchars($w['store']) ?></span>
                <?php endif; ?>
                <?php if ($w['serial_number']): ?>
                    <span title="N° de série">🔢 <?= htmlspecialchars($w['serial_number']) ?></span>
                <?php endif; ?>
            </div>

            <?php if ($w['days_remaining'] !== null): ?>
                <div class="wc-countdown <?= $st['cls'] ?>">
                    <?php if ($w['days_remaining'] < 0): ?>
                        Expirée depuis <?= abs($w['days_remaining']) ?> j
                    <?php elseif ($w['days_remaining'] === 0): ?>
                        Expire aujourd'hui !
                    <?php else: ?>
                        <?= $w['days_remaining'] ?> jour<?= $w['days_remaining'] > 1 ? 's' : '' ?> restant<?= $w['days_remaining'] > 1 ? 's' : '' ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($w['file_path']): ?>
                <a href="<?= BASE_URL ?>/warranties/file/<?= $w['id'] ?>" target="_blank" class="wc-file-link">
                    📎 <?= htmlspecialchars($w['file_original'] ?: 'Voir le document') ?>
                </a>
            <?php endif; ?>

            <?php if ($w['notes']): ?>
                <div class="wc-notes"><?= htmlspecialchars(mb_substr($w['notes'], 0, 100)) ?><?= mb_strlen($w['notes']) > 100 ? '…' : '' ?></div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- ── Add / Edit Modal ───────────────────────────────────────────────── -->
<div class="modal-overlay" id="warranty-modal" style="display:none">
    <div class="modal modal-lg">
        <div class="modal-header">
            <h3 id="wm-title">Ajouter une garantie</h3>
            <button onclick="closeModal('warranty-modal')">✕</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="wm-id">

            <!-- File upload + OCR -->
            <div class="wm-upload-zone" id="wm-drop-zone"
                 ondragover="event.preventDefault();this.classList.add('drag-over')"
                 ondragleave="this.classList.remove('drag-over')"
                 ondrop="handleWarrantyDrop(event)">
                <input type="file" id="wm-file" accept="image/*,application/pdf" style="display:none" onchange="handleWarrantyFile(this.files[0])">
                <div id="wm-upload-label" onclick="document.getElementById('wm-file').click()" style="cursor:pointer">
                    <span style="font-size:2rem">📄</span>
                    <p>Glissez votre facture ici ou <u>cliquez pour choisir</u></p>
                    <small style="color:var(--text-muted)">JPEG, PNG, PDF — max 20 Mo</small>
                </div>
                <div id="wm-file-preview" style="display:none"></div>
            </div>

            <div id="wm-ocr-status" style="display:none;margin:.5rem 0;font-size:.8rem;color:var(--text-muted)"></div>

            <div class="form-row" style="margin-top:.75rem">
                <div class="form-group" style="flex:2">
                    <label>Désignation <span style="color:var(--danger)">*</span></label>
                    <input type="text" id="wm-name" placeholder="Lave-linge Samsung, TV LG…">
                </div>
                <div class="form-group">
                    <label>Prix d'achat (€)</label>
                    <input type="number" id="wm-price" step="0.01" min="0" placeholder="499.00">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Marque</label>
                    <input type="text" id="wm-brand" placeholder="Samsung, Bosch…">
                </div>
                <div class="form-group">
                    <label>Modèle</label>
                    <input type="text" id="wm-model" placeholder="WW90T684DLH…">
                </div>
                <div class="form-group">
                    <label>N° de série</label>
                    <input type="text" id="wm-serial" placeholder="SN-XXXXXXXXX">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Magasin / Vendeur</label>
                    <input type="text" id="wm-store" placeholder="Fnac, Amazon…">
                </div>
                <div class="form-group">
                    <label>Date d'achat</label>
                    <input type="date" id="wm-purchase-date">
                </div>
                <div class="form-group">
                    <label>Durée garantie (mois)</label>
                    <select id="wm-months">
                        <option value="6">6 mois</option>
                        <option value="12">12 mois</option>
                        <option value="24" selected>2 ans (24 mois)</option>
                        <option value="36">3 ans</option>
                        <option value="48">4 ans</option>
                        <option value="60">5 ans</option>
                        <option value="120">10 ans</option>
                        <option value="0">Illimitée / inconnue</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label>Notes</label>
                <textarea id="wm-notes" rows="2" placeholder="Conditions particulières, extensions…"></textarea>
            </div>

            <details id="wm-ocr-details" style="display:none">
                <summary style="cursor:pointer;font-size:.8rem;color:var(--text-muted)">Texte extrait par OCR (cliquez pour voir / modifier)</summary>
                <textarea id="wm-ocr-text" rows="6" style="font-size:.75rem;font-family:monospace;margin-top:.4rem"></textarea>
            </details>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('warranty-modal')">Annuler</button>
            <button class="btn btn-primary" id="wm-save-btn" onclick="saveWarranty()">Enregistrer</button>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
