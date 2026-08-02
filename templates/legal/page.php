<?php
/** @var string $title */
/** @var string $body texte brut : une ligne vide sépare deux paragraphes */
$pageTitle = $title;
ob_start();
?>
<div class="card" style="max-width:760px;margin:2rem auto;padding:2rem;line-height:1.7">
    <h2 style="margin-top:0"><?= htmlspecialchars($title) ?></h2>
    <?php foreach (preg_split('/\n{2,}/', trim($body)) as $paragraph): ?>
        <?php if (preg_match('/^\d+\.\s/', $paragraph)): ?>
        <h3 style="margin-bottom:.4rem"><?= htmlspecialchars($paragraph) ?></h3>
        <?php else: ?>
        <p style="color:var(--text-muted);margin-top:0"><?= nl2br(htmlspecialchars($paragraph)) ?></p>
        <?php endif; ?>
    <?php endforeach; ?>
    <p style="margin-top:2rem;font-size:.82rem">
        <a href="<?= BASE_URL ?>/">&larr; Retour à l'accueil</a>
    </p>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
