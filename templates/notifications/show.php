<?php
$pageTitle = $notification['title'];
$extraCss = ['quill.snow.css'];
ob_start();
?>
<div class="support-page">
    <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:.5rem;flex-wrap:wrap">
        <a href="<?= BASE_URL ?>/" class="btn btn-secondary btn-sm">← Retour</a>
        <h2 style="margin:0;flex:1"><?= htmlspecialchars($notification['title']) ?></h2>
    </div>
    <p style="color:var(--text-muted);font-size:.82rem;margin-bottom:1.5rem">
        <?= \App\Core\DateHelper::fromUtc($notification['created_at'], 'd/m/Y \\à H:i') ?>
    </p>

    <div class="card" style="padding:1.25rem;max-width:700px">
        <div class="post-content ql-content"><?= $notification['content_html'] ?></div>
    </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';