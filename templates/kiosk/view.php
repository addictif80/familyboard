<?php
/* Standalone fullscreen kiosk page — does NOT use templates/layout.php.
   All content is rendered client-side by kiosk.js from /kiosk/:token/data,
   polled periodically so the tablet never needs a page reload. */
$kioskToken = $params['token'] ?? '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <title><?= htmlspecialchars($family['name'] ?? 'FamilyBoard') ?> — Écran mural</title>
    <script>
    (function () {
        try {
            var stored = localStorage.getItem('fb-theme');
            var theme = stored || (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            document.documentElement.setAttribute('data-theme', theme);
        } catch (e) {}
    })();
    </script>
    <link rel="icon" href="<?= BASE_URL ?>/public/icons/icon.svg" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&display=swap">
    <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/app.css?v=<?= APP_VERSION ?>">
    <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/kiosk.css?v=<?= APP_VERSION ?>">
</head>
<body class="kiosk-body">

<?php if (!$kiosk): ?>
    <div class="kiosk-error">
        <h1>⛔ Accès invalide</h1>
        <p>Ce lien d'écran mural est invalide ou a été révoqué. Demandez-en un nouveau depuis les paramètres de la famille.</p>
    </div>
<?php else: ?>

    <div id="kiosk-board" class="kiosk-board"></div>

    <div id="kiosk-privacy" class="kiosk-privacy" style="display:none">
        <div class="kiosk-privacy-card">
            <h1>👶 Mode baby-sitter actif</h1>
            <p>Les informations familiales sont masquées sur cet écran pendant la garde.</p>
            <div id="kiosk-qr"></div>
            <button type="button" class="btn btn-primary btn-lg" onclick="showFamilyInfo()">Afficher les infos de la famille</button>
        </div>
    </div>

    <div id="kiosk-iframe-overlay" class="kiosk-iframe-overlay" style="display:none">
        <button type="button" class="kiosk-iframe-close" onclick="closeFamilyInfo()">✕ Fermer</button>
        <iframe id="kiosk-iframe" src="about:blank"></iframe>
    </div>

    <script>
        const KIOSK_TOKEN = <?= json_encode($kioskToken) ?>;
        const BASE_URL = <?= json_encode(BASE_URL) ?>;
        const KIOSK_REFRESH_SECONDS = 20;
    </script>
    <script src="<?= ASSETS_URL ?>/js/vendor/qrcode.js?v=<?= APP_VERSION ?>"></script>
    <script src="<?= ASSETS_URL ?>/js/kiosk.js?v=<?= APP_VERSION ?>"></script>
<?php endif; ?>

</body>
</html>
