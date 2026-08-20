<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Écran mural — FamilyBoard</title>
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
    <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/app.css?v=<?= APP_VERSION ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&display=swap">
</head>
<body>
<div class="auth-wrapper">
    <div class="auth-card" style="max-width:420px">
        <div class="auth-logo">
            <span style="font-size:2rem">🖥️</span>
            <h2>Écran mural</h2>
            <p style="color:var(--text-muted);font-size:.9rem">
                Saisissez le code à 6 chiffres affiché dans les paramètres famille
                (Écran mural → mode kiosque).
            </p>
        </div>
        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="POST" action="<?= BASE_URL ?>/tv" class="auth-form">
            <div class="form-group">
                <label>Code</label>
                <input type="text" name="code" inputmode="numeric" pattern="[0-9]*" maxlength="6"
                       autofocus required
                       style="font-size:2.2rem;letter-spacing:.4rem;text-align:center;font-family:monospace">
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;font-size:1.1rem">Afficher</button>
        </form>
    </div>
</div>
</body>
</html>
