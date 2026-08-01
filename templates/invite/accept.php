<?php
$pageTitle = 'Rejoindre la famille';
ob_start();
?>
<div class="auth-card" style="max-width:480px">
    <div class="auth-logo">
        <span style="font-size:2.5rem">🏠</span>
        <h1><?= APP_NAME ?></h1>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <p style="text-align:center;margin-top:1rem"><a href="<?= BASE_URL ?>/login">Se connecter</a></p>
    <?php else: ?>
        <?php $isCoparent = ($invitation['invite_role'] ?? 'member') === 'coparent'; ?>
        <div style="text-align:center;margin-bottom:1.5rem">
            <?php if ($isCoparent): ?>
                <p style="color:var(--text-muted);font-size:.9rem">
                    <strong><?= htmlspecialchars($invitation['invited_by_name']) ?></strong>
                    vous invite à un accès restreint sur FamilyBoard, pour le suivi de garde partagée de
                </p>
                <h2 style="color:var(--primary);margin:.5rem 0"><?= htmlspecialchars(implode(', ', $invitedChildren ?? [])) ?></h2>
                <p style="color:var(--text-muted);font-size:.85rem">
                    Vous aurez accès au calendrier de garde, aux propositions de garde, au journal parental
                    et aux documents/évènements liés à cet enfant — pas au reste des données de la famille
                    <strong><?= htmlspecialchars($invitation['family_name']) ?></strong>.
                </p>
            <?php else: ?>
                <p style="color:var(--text-muted);font-size:.9rem">
                    <strong><?= htmlspecialchars($invitation['invited_by_name']) ?></strong>
                    vous invite à rejoindre la famille
                </p>
                <h2 style="color:var(--primary);margin:.5rem 0"><?= htmlspecialchars($invitation['family_name']) ?></h2>
            <?php endif; ?>
            <p style="color:var(--text-muted);font-size:.85rem">
                Invitation pour : <strong><?= htmlspecialchars($invitation['email']) ?></strong>
            </p>
        </div>

        <?php $flash = \App\Core\Session::getFlash('error'); if ($flash): ?>
            <div class="alert alert-error"><?= htmlspecialchars($flash) ?></div>
        <?php endif; ?>

        <form method="POST" action="<?= BASE_URL ?>/invite/<?= htmlspecialchars($invitation['token']) ?>">
            <?= \App\Core\Csrf::field() ?>
            <div class="form-group">
                <label>Votre prénom / nom</label>
                <input type="text" name="name" required autofocus placeholder="Marie Dupont" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Créer un mot de passe</label>
                <input type="password" name="password" required placeholder="Mot de passe existant, ou nouveau mot de passe (min. 8 caractères)" autocomplete="current-password">
            </div>
            <div class="form-group">
                <label class="radio-option" style="align-items:flex-start">
                    <input type="checkbox" name="accept_terms" value="1" required style="margin-top:.2rem">
                    <span>J'accepte les <a href="<?= BASE_URL ?>/cgu" target="_blank">conditions générales d'utilisation</a>
                        et la <a href="<?= BASE_URL ?>/confidentialite" target="_blank">politique de confidentialité</a> de cette instance.</span>
                </label>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%">
                <?= $isCoparent ? "Activer l'accès" : 'Rejoindre la famille' ?>
            </button>
        </form>
        <p style="text-align:center;margin-top:1rem;font-size:.85rem;color:var(--text-muted)">
            Déjà un compte ? <a href="<?= BASE_URL ?>/login">Se connecter</a>
        </p>
    <?php endif; ?>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
