<?php
$pageTitle = 'Nouveau mot de passe - ' . APP_NAME;
ob_start();
?>
<div class="auth-card">
    <div class="auth-logo">
        <span class="logo-big">🔑</span>
        <h1><?= APP_NAME ?></h1>
        <p>Choisissez un nouveau mot de passe</p>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="<?= BASE_URL ?>/reset-password/<?= htmlspecialchars($reset['token']) ?>" class="auth-form">
        <?= \App\Core\Csrf::field() ?>
        <div class="form-group">
            <label>Nouveau mot de passe</label>
            <input type="password" name="password" required minlength="8" autofocus placeholder="••••••••">
        </div>
        <div class="form-group">
            <label>Confirmer le mot de passe</label>
            <input type="password" name="password_confirm" required minlength="8" placeholder="••••••••">
        </div>
        <button type="submit" class="btn btn-primary btn-full">Mettre à jour le mot de passe</button>
    </form>

    <p class="auth-link"><a href="<?= BASE_URL ?>/login">&larr; Retour à la connexion</a></p>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
