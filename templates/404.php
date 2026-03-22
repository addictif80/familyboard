<?php
$pageTitle = 'Page introuvable';
ob_start();
?>
<div class="empty-state-card" style="text-align:center;padding:4rem">
    <h2>404 — Page introuvable</h2>
    <p>La page que vous cherchez n'existe pas.</p>
    <a href="<?= BASE_URL ?>/" class="btn btn-primary">Retour à l'accueil</a>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
