<?php
/**
 * Entrée "Comment ça marche ?" du sous-menu de la topbar. Doit être utilisée avec
 * templates/partials/tour_modal.php (requis séparément, plus bas dans la page, jamais à
 * l'intérieur de la topbar : .topbar a un backdrop-filter, qui "piège" tout élément
 * position:fixed placé dedans dans la boîte de la topbar au lieu du viewport entier).
 *
 * Attendu en entrée : $currentPath (défini plus haut dans templates/layout.php).
 */
$_tourRoute = trim((string)preg_replace('#^' . preg_quote(rtrim(BASE_URL, '/'), '#') . '#', '', (string)$currentPath), '/');
$_tourKey   = $_tourRoute === '' ? 'dashboard' : \App\Core\ModuleGuide::keyForRoute($_tourRoute);
?>
<button type="button" class="topbar-menu-item" onclick="Tour.open(<?= json_encode($_tourKey ?: 'global') ?>)">
    <span class="topbar-menu-item-icon">❓</span> Comment ça marche ?
</button>
