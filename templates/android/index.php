<?php
$pageTitle = 'Application Android — ' . APP_NAME;
ob_start();
?>
<div class="card" style="max-width:680px;margin:2rem auto;padding:2rem">
    <h2 style="margin-top:0">🤖 Application Android</h2>
    <p style="color:var(--text-muted)">
        <?= APP_NAME ?> propose un client Android natif, distribué directement depuis ce site —
        sans passer par le Play Store. Il s'agit d'un simple lien de téléchargement, à installer
        comme n'importe quelle application « hors store » : votre téléphone demandera d'autoriser
        l'installation depuis cette source lors du premier lancement.
    </p>
    <p style="margin:1.5rem 0">
        <a href="<?= BASE_URL ?>/android/download" class="btn btn-primary">⬇️ Télécharger l'APK</a>
    </p>
    <h3>Mises à jour</h3>
    <p style="color:var(--text-muted)">
        L'application vérifie elle-même, au démarrage, si une nouvelle version est disponible sur
        GitHub. Si c'est le cas, elle vous propose de la télécharger et de l'installer en un
        geste — pas besoin de repasser par ce site à chaque mise à jour.
    </p>
    <h3>Alternative : la PWA</h3>
    <p style="color:var(--text-muted)">
        Vous pouvez aussi installer <?= APP_NAME ?> comme une PWA, sans APK et avec mise à jour
        automatique transparente, depuis <a href="<?= BASE_URL ?>/settings#pwa-install-section">Paramètres → 📲 Installer l'application</a>.
    </p>
    <p style="margin-top:2rem;font-size:.82rem">
        <a href="<?= BASE_URL ?>/">&larr; Retour à l'accueil</a>
    </p>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
