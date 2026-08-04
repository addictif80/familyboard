<?php
$pageTitle = 'Connexion - ' . APP_NAME;
ob_start();
?>
<div class="auth-card">
    <div class="auth-logo">
        <span class="logo-big">🏠</span>
        <h1><?= APP_NAME ?></h1>
        <p>Votre espace famille partagé</p>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <form method="POST" action="<?= BASE_URL ?>/login" class="auth-form">
        <?= \App\Core\Csrf::field() ?>
        <div class="form-group">
            <label>Email</label>
            <input type="email" name="email" required autofocus placeholder="votre@email.fr">
        </div>
        <div class="form-group">
            <label>Mot de passe</label>
            <input type="password" name="password" required placeholder="••••••••">
        </div>
        <button type="submit" class="btn btn-primary btn-full">Se connecter</button>
    </form>

    <p class="auth-link"><a href="<?= BASE_URL ?>/forgot-password">Mot de passe oublié ?</a></p>
    <p class="auth-link">Pas encore de compte ? <a href="<?= BASE_URL ?>/register">Créer une famille</a></p>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
