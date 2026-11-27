<?php
/**
 * Version compacte de la mise en avant ABHD, pour la sidebar (PC uniquement — voir le CSS
 * .sidebar-spotlight, masquée sur mobile où la sidebar est un tiroir généralement fermé ; la
 * version pleine largeur reste affichée sur le tableau de bord mobile, voir templates/dashboard.php).
 * Largeur toujours contenue dans celle, fixe, de la sidebar — jamais l'inverse.
 */
$_spotlight = \App\Models\AbhdHighlight::pickForPlacement('dashboard');
if ($_spotlight):
?>
<a class="sidebar-spotlight" href="<?= BASE_URL ?>/highlights/<?= (int)$_spotlight['id'] ?>/go" target="_blank" rel="noopener noreferrer">
    <?php if ($_spotlight['image_path']): ?>
        <img class="sidebar-spotlight-img" src="<?= htmlspecialchars(BASE_URL . $_spotlight['image_path']) ?>" alt="">
    <?php endif; ?>
    <span class="sidebar-spotlight-body">
        <strong class="sidebar-spotlight-title"><?= htmlspecialchars($_spotlight['title']) ?></strong>
        <span class="sidebar-spotlight-tag"><?= htmlspecialchars(parse_url($_spotlight['url'], PHP_URL_HOST) ?: $_spotlight['url']) ?></span>
    </span>
</a>
<?php endif; unset($_spotlight); ?>
