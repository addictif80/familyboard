<?php
/**
 * Bannière promotionnelle FamilyBoard affichée sur les pages publiques "chaleureuses"
 * accessibles sans compte (liste de cadeaux, liste de naissance, album partagé...) — jamais
 * sur les pages sensibles (coffre-fort, accès babysitter, fiche d'urgence, litige), où une
 * pub serait déplacée. Désactivable globalement par l'admin système.
 *
 * Variables attendues :
 * - $bannerFamilyId (int|null) optionnel : si fourni, le lien d'inscription porte le code de
 *   parrainage de cette famille (programme de parrainage existant, voir App\Models\Referral).
 */
if ((\App\Models\AppSetting::get('public_signup_banner_enabled', '1')) !== '0'):
    $_bannerRegisterUrl = BASE_URL . '/register';
    if (!empty($bannerFamilyId)) {
        $_bannerRefCode = \App\Models\Family::ensureReferralCode((int)$bannerFamilyId);
        if ($_bannerRefCode) $_bannerRegisterUrl .= '?ref=' . urlencode($_bannerRefCode);
    }
?>
<div class="card" style="max-width:720px;width:100%;margin:0 auto 1.25rem;padding:1.25rem 1.5rem;display:flex;align-items:center;gap:1.25rem;flex-wrap:wrap;border:2px solid var(--accent);background:color-mix(in srgb, var(--accent) 16%, var(--card-bg));box-shadow:var(--shadow-lg)">
    <div style="flex:1;min-width:240px">
        <div style="font-size:1.05rem;font-weight:700">🏡 Cette liste est propulsée par FamilyBoard</div>
        <p style="margin:.3rem 0 0;color:var(--text-muted);font-size:.88rem">
            Calendrier, tâches, listes partagées, garde alternée... créez votre propre espace privé gratuit pour organiser toute votre famille.
        </p>
    </div>
    <a href="<?= htmlspecialchars($_bannerRegisterUrl) ?>" class="btn btn-primary" style="white-space:nowrap;font-weight:600">Créer mon espace gratuit →</a>
</div>
<?php endif; ?>
