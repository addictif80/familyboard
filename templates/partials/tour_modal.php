<?php
/**
 * Modale de la visite guidée "Comment ça marche" (une globale + une par module). Doit être
 * requise HORS de la topbar (voir templates/partials/tour_widget.php pour le bouton déclencheur) :
 * .topbar a un backdrop-filter, qui crée un nouveau "containing block" pour tout élément
 * position:fixed placé à l'intérieur — la modale serait alors piégée dans la fine barre du haut
 * au lieu de couvrir l'écran.
 *
 * S'ouvre aussi automatiquement, une seule fois par utilisateur, la première fois qu'il visite ce
 * module (ou l'application pour le guide global) — voir App\Models\User::hasSeenTour/markTourSeen.
 *
 * Attendu en entrée : $currentUser (utilisateur connecté), $currentPath (défini plus haut dans
 * templates/layout.php).
 */
$_tourRoute  = trim((string)preg_replace('#^' . preg_quote(rtrim(BASE_URL, '/'), '#') . '#', '', (string)$currentPath), '/');
$_tourKey    = $_tourRoute === '' ? 'dashboard' : \App\Core\ModuleGuide::keyForRoute($_tourRoute);
$_tourGlobal = \App\Core\ModuleGuide::get('global');
$_tourModule = $_tourKey ? \App\Core\ModuleGuide::get($_tourKey) : null;

$_tourAutoKey = null;
if ($_tourModule && !\App\Models\User::hasSeenTour($currentUser, $_tourKey)) {
    $_tourAutoKey = $_tourKey;
} elseif (!\App\Models\User::hasSeenTour($currentUser, 'global')) {
    $_tourAutoKey = 'global';
}
?>
<div class="modal-overlay" id="tour-modal" style="display:none">
    <div class="modal modal-sm">
        <div class="modal-header">
            <h3 id="tour-modal-title"></h3>
            <button onclick="Tour.close()">✕</button>
        </div>
        <div class="modal-body">
            <p class="tour-tagline" id="tour-modal-tagline"></p>
            <ul class="tour-points" id="tour-modal-points"></ul>
            <?php if ($_tourModule): ?>
            <button type="button" class="btn-text" id="tour-modal-global-link" onclick="Tour.open('global')" style="display:none">Voir aussi la visite générale de FamilyBoard →</button>
            <?php endif; ?>
        </div>
        <div class="modal-footer">
            <button class="btn btn-primary" onclick="Tour.close()">Compris, merci !</button>
        </div>
    </div>
</div>

<script>
var Tour = (function () {
    var guides = <?= json_encode(array_filter([
        'global' => $_tourGlobal,
        $_tourKey => $_tourModule,
    ])) ?>;
    var autoKey = <?= json_encode($_tourAutoKey) ?>;

    function render(key) {
        var g = guides[key];
        if (!g) return;
        document.getElementById('tour-modal-title').textContent = g.icon + ' ' + g.title;
        document.getElementById('tour-modal-tagline').textContent = g.tagline;
        var ul = document.getElementById('tour-modal-points');
        ul.innerHTML = '';
        (g.points || []).forEach(function (p) {
            var li = document.createElement('li');
            li.textContent = p;
            ul.appendChild(li);
        });
        var globalLink = document.getElementById('tour-modal-global-link');
        if (globalLink) globalLink.style.display = key === 'global' ? 'none' : '';
        document.getElementById('tour-modal').dataset.currentKey = key;
    }

    function open(key) {
        if (!guides[key]) key = 'global';
        if (!guides[key]) return;
        render(key);
        document.getElementById('tour-modal').style.display = 'flex';
    }

    function close() {
        var modal = document.getElementById('tour-modal');
        var key = modal.dataset.currentKey;
        modal.style.display = 'none';
        if (key) {
            fetch(<?= json_encode(BASE_URL) ?> + '/api/tour/seen', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({ key: key }),
            }).catch(function () {});
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        if (autoKey) open(autoKey);
    });

    return { open: open, close: close };
})();
</script>
