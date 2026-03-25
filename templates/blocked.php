<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Compte bloqué — FamilyBoard</title>
    <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/app.css?v=<?= APP_VERSION ?>">
</head>
<body>
<div class="auth-wrapper">
    <div class="auth-card" style="text-align:center">
        <div style="font-size:3rem;margin-bottom:1rem">🚫</div>
        <h2>Accès refusé</h2>
        <p style="color:var(--text-muted);margin:1rem 0">Votre compte a été bloqué. Contactez un administrateur.</p>
        <?php if (!empty($reason)): ?>
        <p style="font-size:.85rem;color:var(--text-muted);background:var(--bg);padding:.6rem .8rem;border-radius:8px;margin-bottom:1rem">
            Motif : <?= htmlspecialchars($reason) ?>
        </p>
        <?php endif; ?>
        <a href="<?= BASE_URL ?>/login" class="btn btn-secondary">← Retour à la connexion</a>
    </div>
</div>
</body>
</html>
