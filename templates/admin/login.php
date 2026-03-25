<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administration — FamilyBoard</title>
    <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/app.css?v=<?= APP_VERSION ?>">
</head>
<body>
<div class="auth-wrapper">
    <div class="auth-card">
        <div class="auth-logo">
            <span style="font-size:2rem">🔐</span>
            <h2>Administration</h2>
            <p style="color:var(--text-muted);font-size:.85rem">FamilyBoard — Accès réservé</p>
        </div>
        <?php if ($err = ($_SESSION['admin_error'] ?? null)): unset($_SESSION['admin_error']); ?>
            <div class="alert alert-error"><?= htmlspecialchars($err) ?></div>
        <?php endif; ?>
        <form method="POST" action="<?= BASE_URL ?>/admin/login" class="auth-form">
            <div class="form-group">
                <label>Identifiant</label>
                <input type="text" name="username" required autofocus autocomplete="username">
            </div>
            <div class="form-group">
                <label>Mot de passe</label>
                <input type="password" name="password" required autocomplete="current-password">
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%">Se connecter</button>
        </form>
        <div style="text-align:center;margin-top:1rem">
            <a href="<?= BASE_URL ?>/" style="font-size:.85rem;color:var(--text-muted)">← Retour au site</a>
        </div>
    </div>
</div>
</body>
</html>
