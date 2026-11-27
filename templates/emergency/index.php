<?php
$pageTitle = 'Fiches urgence';
$extraJs   = ['vendor/qrcode.js', 'emergency.js'];
ob_start();
?>
<div class="content-header">
    <h2>🚑 Fiches urgence</h2>
</div>
<p style="color:var(--text-muted);font-size:.85rem;margin-bottom:1rem">
    Créez une fiche par personne (allergies, groupe sanguin, médecin, contact d'urgence) et partagez-la
    par QR code ou lien — utile pour une baby-sitter, l'école ou en cas d'urgence. La fiche est visible
    par quiconque a le lien, sans avoir besoin de se connecter.
</p>

<div class="settings-container">
    <?php foreach ($members as $m): ?>
    <?php $subjectType = 'user'; $subjectId = $m['id']; $subjectName = $m['name']; $card = $cards['user_' . $m['id']] ?? null; ?>
    <div class="card settings-section">
        <h3>👤 <?= htmlspecialchars($subjectName) ?></h3>
        <?php include __DIR__ . '/_card_form.php'; ?>
    </div>
    <?php endforeach; ?>

    <?php foreach ($babies as $b): ?>
    <?php $subjectType = 'baby'; $subjectId = $b['id']; $subjectName = $b['name']; $card = $cards['baby_' . $b['id']] ?? null; ?>
    <div class="card settings-section">
        <h3>🍼 <?= htmlspecialchars($subjectName) ?></h3>
        <?php include __DIR__ . '/_card_form.php'; ?>
    </div>
    <?php endforeach; ?>
</div>

<!-- QR modal -->
<div class="modal-overlay" id="emergency-qr-modal" style="display:none">
    <div class="modal">
        <div class="modal-header">
            <h3>Fiche urgence — QR code</h3>
            <button onclick="closeModal('emergency-qr-modal')">✕</button>
        </div>
        <div class="modal-body" style="text-align:center">
            <div id="emergency-qr-container" style="display:inline-block;margin-bottom:1rem"></div>
            <p style="word-break:break-all;font-size:.8rem" id="emergency-qr-link"></p>
        </div>
    </div>
</div>

<!-- Journal d'accès modal -->
<div class="modal-overlay" id="emergency-log-modal" style="display:none">
    <div class="modal">
        <div class="modal-header">
            <h3>Journal d'accès à la fiche</h3>
            <button onclick="closeModal('emergency-log-modal')">✕</button>
        </div>
        <div class="modal-body">
            <p style="color:var(--text-muted);font-size:.8rem;margin-bottom:.75rem">
                Chaque consultation du lien public (sans compte) est journalisée ici — 50 dernières consultations.
            </p>
            <div id="emergency-log-body"></div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
