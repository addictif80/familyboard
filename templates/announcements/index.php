<?php
$pageTitle = 'Annonces';
ob_start();

use App\Models\Announcement;
?>
<div class="tasks-main" style="width:100%;max-width:760px;margin:0 auto">
    <div class="tasks-header">
        <h2>📣 Annonces</h2>
    </div>

    <?php foreach ($announcements as $a): ?>
        <?php $t = Announcement::TYPES[$a['type']]; ?>
        <div class="card settings-section">
            <div style="display:flex;justify-content:space-between;align-items:baseline;gap:.6rem">
                <h3 style="margin:0"><?= $t['icon'] ?> <?= htmlspecialchars($a['title']) ?></h3>
                <span style="color:var(--text-muted);font-size:.8rem;white-space:nowrap"><?= \App\Core\DateHelper::fromUtc($a['published_at'], 'd/m/Y') ?></span>
            </div>
            <div style="margin-top:.6rem;white-space:pre-wrap"><?= nl2br(htmlspecialchars($a['content'])) ?></div>
        </div>
    <?php endforeach; ?>
    <?php if (empty($announcements)): ?>
        <p class="empty-state">Aucune annonce pour le moment.</p>
    <?php endif; ?>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
