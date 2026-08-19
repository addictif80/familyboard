<?php
$pageTitle = 'Compte supprimé - ' . APP_NAME;
ob_start();
?>
<div class="auth-card">
    <div class="auth-logo">
        <span class="logo-big">📄</span>
        <h1><?= APP_NAME ?></h1>
        <p>Redemander mes données</p>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($info): ?>
        <div class="alert alert-success"><?= htmlspecialchars($info) ?></div>
    <?php else: ?>
        <p style="color:var(--text-muted);font-size:.85rem;margin-bottom:1rem">
            Votre compte FamilyBoard a été supprimé et vous n'y avez plus accès. Indiquez son
            adresse e-mail : si un compte supprimé lui correspond, le rapport de suppression
            (motif et récapitulatif de vos données personnelles) vous sera renvoyé.
        </p>
    <?php endif; ?>

    <?php if (!$info): ?>
    <form method="POST" action="<?= BASE_URL ?>/compte-supprime" class="auth-form">
        <?= \App\Core\Csrf::field() ?>
        <div class="form-group">
            <label>Email</label>
            <input type="email" name="email" required autofocus placeholder="votre@email.fr">
        </div>
        <button type="submit" class="btn btn-primary btn-full">Renvoyer mon rapport</button>
    </form>
    <?php endif; ?>

    <p class="auth-link"><a href="<?= BASE_URL ?>/login">&larr; Retour à la connexion</a></p>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
