<?php
/**
 * Affichée une seule fois : à la première page vue après connexion (voir le flag
 * highlight_modal_pending posé par App\Core\Session::login() et consommé ici).
 */
if (!empty($_SESSION['highlight_modal_pending'])) {
    unset($_SESSION['highlight_modal_pending']);
    $_modalHighlight = \App\Models\AbhdHighlight::pickModal();
} else {
    $_modalHighlight = null;
}
if ($_modalHighlight):
?>
<div class="modal-overlay" id="abhd-spotlight-modal" style="display:none">
    <div class="modal" style="max-width:420px;text-align:center">
        <div class="modal-header" style="border-bottom:none">
            <span></span>
            <button onclick="closeModal('abhd-spotlight-modal')">✕</button>
        </div>
        <div class="modal-body">
            <a href="<?= BASE_URL ?>/highlights/<?= (int)$_modalHighlight['id'] ?>/go" target="_blank" rel="noopener noreferrer" style="text-decoration:none;color:inherit">
                <?php if ($_modalHighlight['image_path']): ?>
                    <img src="<?= htmlspecialchars(BASE_URL . $_modalHighlight['image_path']) ?>" alt="" style="max-width:100%;height:auto;border-radius:10px;margin-bottom:1rem">
                <?php endif; ?>
                <h3 style="margin:0 0 .4rem"><?= htmlspecialchars($_modalHighlight['title']) ?></h3>
                <?php if ($_modalHighlight['description']): ?>
                    <p style="color:var(--text-muted);font-size:.88rem"><?= htmlspecialchars($_modalHighlight['description']) ?></p>
                <?php endif; ?>
            </a>
            <span class="abhd-spotlight-tag"><?= htmlspecialchars(parse_url($_modalHighlight['url'], PHP_URL_HOST) ?: $_modalHighlight['url']) ?></span>
        </div>
    </div>
</div>
<script>
// Différé à DOMContentLoaded : openModal() est définie dans app.js, chargé en fin de
// page — un appel immédiat ici (avant app.js) échouerait silencieusement.
document.addEventListener('DOMContentLoaded', function () { openModal('abhd-spotlight-modal'); });
</script>
<?php unset($_modalHighlight); ?>
<?php endif; ?>
