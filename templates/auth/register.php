<?php
$pageTitle = 'Inscription - ' . APP_NAME;
ob_start();
?>
<div class="auth-card">
    <div class="auth-logo">
        <span class="logo-big">🏠</span>
        <h1><?= APP_NAME ?></h1>
        <p>Créez votre espace famille</p>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="<?= BASE_URL ?>/register" class="auth-form">
        <?= \App\Core\Csrf::field() ?>
        <div class="form-group">
            <label>Votre prénom / nom</label>
            <input type="text" name="name" required autofocus placeholder="Marie Dupont">
        </div>
        <div class="form-group">
            <label>Email</label>
            <input type="email" name="email" required placeholder="votre@email.fr">
        </div>
        <div class="form-group">
            <label>Mot de passe (min. 8 caractères)</label>
            <input type="password" name="password" required minlength="8" placeholder="••••••••" autocomplete="new-password">
        </div>

        <hr class="divider">
        <p class="section-label">Famille</p>

        <div id="family-choice">
            <div class="radio-group">
                <label class="radio-option">
                    <input type="radio" name="family_type" value="new" checked onchange="toggleFamilyType(this)">
                    <span>Créer une nouvelle famille</span>
                </label>
                <label class="radio-option">
                    <input type="radio" name="family_type" value="join" onchange="toggleFamilyType(this)">
                    <span>Rejoindre une famille existante</span>
                </label>
            </div>
        </div>

        <div id="new-family-section">
            <div class="form-group">
                <label>Nom de la famille</label>
                <input type="text" name="family_name" placeholder="Famille Dupont" id="family-name-input">
            </div>
        </div>

        <div id="join-family-section" style="display:none">
            <div class="form-group">
                <label>Code d'invitation</label>
                <input type="text" name="invite_code" placeholder="XXXXXXXX" value="<?= htmlspecialchars($inviteCode ?? '') ?>" id="invite-code-input" style="text-transform:uppercase;letter-spacing:2px">
            </div>
        </div>

        <hr class="divider">

        <div class="form-group">
            <label class="radio-option" style="align-items:flex-start">
                <input type="checkbox" name="accept_terms" value="1" required style="margin-top:.2rem">
                <span>J'accepte les <a href="<?= BASE_URL ?>/cgu" target="_blank">conditions générales d'utilisation</a>
                    et la <a href="<?= BASE_URL ?>/confidentialite" target="_blank">politique de confidentialité</a> de FamilyBoard.</span>
            </label>
        </div>

        <button type="submit" class="btn btn-primary btn-full">S'inscrire</button>
    </form>

    <p class="auth-link">Déjà inscrit ? <a href="<?= BASE_URL ?>/login">Se connecter</a></p>
</div>
<script>
function toggleFamilyType(el) {
    document.getElementById('new-family-section').style.display = el.value === 'new' ? '' : 'none';
    document.getElementById('join-family-section').style.display = el.value === 'join' ? '' : 'none';
    document.getElementById('family-name-input').required = el.value === 'new';
    document.getElementById('invite-code-input').required = el.value === 'join';
}
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
