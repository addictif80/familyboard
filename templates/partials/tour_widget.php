<?php
/**
 * Bouton d'aide "Comment ça marche ?" — place ce require dans la topbar. Doit être utilisé avec
 * templates/partials/tour_modal.php (requis séparément, plus bas dans la page, jamais à
 * l'intérieur de la topbar : .topbar a un backdrop-filter, qui "piège" tout élément
 * position:fixed placé dedans dans la boîte de la topbar au lieu du viewport entier).
 *
 * Attendu en entrée : $currentPath (défini plus haut dans templates/layout.php).
 */
$_tourRoute = trim((string)preg_replace('#^' . preg_quote(rtrim(BASE_URL, '/'), '#') . '#', '', (string)$currentPath), '/');
$_tourKey   = $_tourRoute === '' ? 'dashboard' : \App\Core\ModuleGuide::keyForRoute($_tourRoute);
?>
<button class="btn-icon" onclick="Tour.open(<?= json_encode($_tourKey ?: 'global') ?>)" title="Comment ça marche ?">❓</button>
