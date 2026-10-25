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
<div class="card" style="max-width:720px;margin:1.5rem auto 0;padding:1rem 1.25rem;display:flex;align-items:center;gap:1rem;flex-wrap:wrap;background:color-mix(in srgb, var(--accent) 10%, var(--card-bg))">
    <div style="flex:1;min-width:220px">
        <strong>🏡 Propulsé par FamilyBoard</strong>
        <p style="margin:.25rem 0 0;color:var(--text-muted);font-size:.85rem">
            Calendrier, tâches, listes partagées, garde alternée... l'espace privé où toute votre famille s'organise.
        </p>
    </div>
    <a href="<?= htmlspecialchars($_bannerRegisterUrl) ?>" class="btn btn-primary btn-sm" style="white-space:nowrap">Créer mon espace gratuit</a>
</div>
<?php endif; ?>
