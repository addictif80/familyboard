<?php
$pageTitle = 'Vérification en deux étapes - ' . APP_NAME;
ob_start();
?>
<div class="auth-card">
    <div class="auth-logo">
        <span class="logo-big">🔐</span>
        <h1><?= APP_NAME ?></h1>
        <p>Vérification en deux étapes</p>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($info): ?>
        <div class="alert alert-success"><?= htmlspecialchars($info) ?></div>
    <?php endif; ?>

    <?php if ($method === 'totp'): ?>
        <p style="color:var(--text-muted);font-size:.85rem">Ouvrez votre application d'authentification et saisissez le code à 6 chiffres affiché.</p>
    <?php else: ?>
        <p style="color:var(--text-muted);font-size:.85rem">Un code à 6 chiffres vient de vous être envoyé par email. Il expire dans 10 minutes.</p>
    <?php endif; ?>

    <form method="POST" action="<?= BASE_URL ?>/login/2fa" class="auth-form">
        <?= \App\Core\Csrf::field() ?>
        <div class="form-group">
            <label>Code de vérification</label>
            <input type="text" name="code" inputmode="numeric" pattern="[0-9]*" maxlength="6" required autofocus placeholder="123456" style="letter-spacing:4px;font-size:1.3rem;text-align:center">
        </div>
        <button type="submit" class="btn btn-primary btn-full">Vérifier</button>
    </form>

    <?php if ($method === 'email'): ?>
        <form method="POST" action="<?= BASE_URL ?>/login/2fa/resend" style="margin-top:.5rem">
            <?= \App\Core\Csrf::field() ?>
            <button type="submit" class="btn btn-secondary btn-full btn-sm">Renvoyer le code</button>
        </form>
    <?php endif; ?>

    <p class="auth-link"><a href="<?= BASE_URL ?>/login">← Annuler et revenir à la connexion</a></p>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
