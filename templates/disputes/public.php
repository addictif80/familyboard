<?php
$pageTitle = $dispute ? $dispute['title'] : 'Dossier introuvable';
ob_start();
use App\Core\DateHelper;
?>
<div class="card" style="max-width:640px;margin:2rem auto;padding:1.5rem">
<?php if (!$dispute): ?>
    <h2>🔗 Lien introuvable</h2>
    <p style="color:var(--text-muted)">Ce lien de partage est invalide ou a été révoqué.</p>
<?php else: ?>
    <h2>⚖️ <?= htmlspecialchars($dispute['title']) ?></h2>
    <p style="color:var(--text-muted);font-size:.8rem;margin-bottom:1rem">Dossier partagé en lecture seule.</p>

    <div class="form-row" style="margin-bottom:1rem">
        <div><strong>Partie adverse</strong><br><?= htmlspecialchars($dispute['opposing_party']) ?></div>
        <div><strong>Date de début du litige</strong><br><?= DateHelper::format($dispute['start_date'], 'd/m/Y') ?></div>
    </div>

    <?php if (trim(strip_tags($dispute['details'] ?? ''))): ?>
    <div style="margin-bottom:1.25rem">
        <strong>Détails</strong>
        <div style="margin-top:.3rem;white-space:pre-wrap"><?= nl2br(htmlspecialchars($dispute['details'])) ?></div>
    </div>
    <?php endif; ?>

    <div>
        <strong>📎 Pièces jointes</strong>
        <div style="margin-top:.5rem">
            <?php if (empty($documents)): ?>
                <p style="color:var(--text-muted);font-size:.85rem">Aucune pièce jointe.</p>
            <?php endif; ?>
            <?php foreach ($documents as $doc): ?>
            <div style="display:flex;justify-content:space-between;align-items:center;padding:.4rem 0;border-bottom:1px solid var(--border)">
                <span><?= htmlspecialchars($doc['file_original']) ?></span>
                <a class="btn btn-secondary btn-sm" href="<?= BASE_URL ?>/share/dispute/<?= htmlspecialchars($token) ?>/documents/<?= $doc['id'] ?>" target="_blank">Télécharger</a>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
