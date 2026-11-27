<?php
$pageTitle = 'Bienvenue';
ob_start();
?>
<div class="card" style="padding:1.75rem;max-width:700px;margin:0 auto">
    <h2 style="margin-top:0">🔒 Bienvenue sur votre espace « Garde partagée »</h2>
    <p style="color:var(--text-muted)">Votre accès est volontairement restreint : vous ne voyez que ce qui concerne la garde de l'enfant, jamais le reste de la vie privée de la famille (budget, autres membres, documents non liés…). Voici ce que vous pouvez faire ici.</p>

    <div style="display:grid;gap:1rem;margin:1.5rem 0">
        <div style="display:flex;gap:.75rem;align-items:flex-start">
            <span style="font-size:1.4rem">📅</span>
            <div>
                <strong>Calendrier de garde</strong>
                <p style="margin:.2rem 0 0;color:var(--text-muted);font-size:.9rem">Consultez le planning de garde et ses échéances, et proposez un échange ou un ajustement de jour à l'autre parent.</p>
            </div>
        </div>
        <div style="display:flex;gap:.75rem;align-items:flex-start">
            <span style="font-size:1.4rem">📝</span>
            <div>
                <strong>Journal parental</strong>
                <p style="margin:.2rem 0 0;color:var(--text-muted);font-size:.9rem">Échangez des messages courts (texte ou vocal) avec l'autre parent sur le quotidien de l'enfant — santé, humeur, événements notables.</p>
            </div>
        </div>
        <div style="display:flex;gap:.75rem;align-items:flex-start">
            <span style="font-size:1.4rem">📜</span>
            <div>
                <strong>Journal d'activité</strong>
                <p style="margin:.2rem 0 0;color:var(--text-muted);font-size:.9rem">Un historique horodaté de ce qui se passe autour de la garde, pour garder une trace claire et partagée.</p>
            </div>
        </div>
        <div style="display:flex;gap:.75rem;align-items:flex-start">
            <span style="font-size:1.4rem">🗂️</span>
            <div>
                <strong>Documents liés</strong>
                <p style="margin:.2rem 0 0;color:var(--text-muted);font-size:.9rem">Consultez les documents que l'autre parent a explicitement rattachés à ce planning de garde (jamais les autres documents de la famille).</p>
            </div>
        </div>
        <div style="display:flex;gap:.75rem;align-items:flex-start">
            <span style="font-size:1.4rem">🖼️</span>
            <div>
                <strong>Albums photo</strong>
                <p style="margin:.2rem 0 0;color:var(--text-muted);font-size:.9rem">Voyez les photos de l'enfant et ajoutez les vôtres aux albums partagés.</p>
            </div>
        </div>
        <div style="display:flex;gap:.75rem;align-items:flex-start">
            <span style="font-size:1.4rem">🧾</span>
            <div>
                <strong>Additions</strong>
                <p style="margin:.2rem 0 0;color:var(--text-muted);font-size:.9rem">Si l'autre parent l'a activé, suivez et réglez votre part des dépenses communes liées à l'enfant.</p>
            </div>
        </div>
        <div style="display:flex;gap:.75rem;align-items:flex-start">
            <span style="font-size:1.4rem">🏠</span>
            <div>
                <strong>Votre propre espace FamilyBoard</strong>
                <p style="margin:.2rem 0 0;color:var(--text-muted);font-size:.9rem">Vous pouvez aussi créer votre propre famille complète (calendrier, tâches, budget…) depuis <a href="<?= BASE_URL ?>/coparent">votre tableau de bord</a> — vous garderez cet accès « Garde partagée » en plus, pas à sa place.</p>
            </div>
        </div>
    </div>

    <p style="color:var(--text-muted);font-size:.85rem">Un doute sur ce que vous voyez ou ne voyez pas ? Le <a href="<?= BASE_URL ?>/aide">guide</a> et la <a href="<?= BASE_URL ?>/faq">FAQ</a> restent accessibles à tout moment depuis le bandeau du haut.</p>

    <div style="text-align:right;margin-top:1.5rem;padding-top:1rem;border-top:1px solid var(--border)">
        <button class="btn btn-primary" onclick="dismissCoparentWelcome()">Accéder à mon espace →</button>
    </div>
</div>

<script>
async function dismissCoparentWelcome() {
    await apiFetch(`${<?= json_encode(BASE_URL) ?>}/api/coparent/welcome/dismiss`, { method: 'POST' });
    window.location.href = `${<?= json_encode(BASE_URL) ?>}/coparent`;
}
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
