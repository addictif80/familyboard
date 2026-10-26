<?php
$pageTitle = 'Coffre-fort numérique';
ob_start();

use App\Models\Vault;
?>
<!-- .auth-wrapper (layout.php, visiteur non connecté) est un flex-row pensé pour une seule
     carte centrée : ce wrapper column permet d'empiler la bannière promo au-dessus de la carte
     principale plutôt qu'à côté. -->
<div style="display:flex;flex-direction:column;align-items:center;gap:1rem;width:100%;margin-top:2rem">
<?php if ($trustee): $bannerFamilyId = (int)$trustee['family_id']; require BASE_PATH . '/templates/partials/public_signup_banner.php'; endif; ?>
<div class="card" style="max-width:640px;margin:0 auto;padding:1.5rem;width:100%">
<?php if (!$trustee): ?>
    <h2>⛔ Lien invalide</h2>
    <p style="color:var(--text-muted)">Ce lien d'accès n'existe pas. Contactez la famille pour en obtenir un nouveau.</p>
<?php else: ?>
    <h2>🔐 Coffre-fort numérique — <?= htmlspecialchars($family['name'] ?? 'Famille') ?></h2>
    <p style="color:var(--text-muted);font-size:.9rem">Bonjour <?= htmlspecialchars($trustee['name']) ?>, vous avez été désigné(e) comme personne de confiance.</p>

    <?php if ($trustee['status'] === 'none' || $trustee['status'] === 'denied'): ?>
        <div style="background:color-mix(in srgb, var(--accent) 12%, transparent);border-left:3px solid var(--accent);border-radius:6px;padding:.85rem 1rem;margin-top:1.25rem">
            <p style="margin:0">En cas de décès ou d'incapacité d'un membre de cette famille, vous pouvez demander l'accès au coffre-fort numérique. Un administrateur de la famille devra valider votre demande avant toute ouverture.</p>
        </div>
        <button class="btn btn-primary" style="margin-top:1rem" onclick="requestVaultAccess()">Demander l'accès</button>
    <?php elseif ($trustee['status'] === 'requested'): ?>
        <div style="background:color-mix(in srgb, #E67E22 12%, transparent);border-left:3px solid #E67E22;border-radius:6px;padding:.85rem 1rem;margin-top:1.25rem">
            <p style="margin:0">⏳ Votre demande d'accès est en attente de validation par un administrateur de la famille.</p>
        </div>
    <?php elseif ($trustee['status'] === 'approved'): ?>
        <div style="background:color-mix(in srgb, var(--success) 12%, transparent);border-left:3px solid var(--success);border-radius:6px;padding:.85rem 1rem;margin:1.25rem 0">
            <p style="margin:0">✅ Votre accès a été validé. Vous pouvez consulter le contenu du coffre-fort ci-dessous.</p>
        </div>
        <?php if (!empty($entries)): ?>
        <a class="btn btn-primary" href="<?= BASE_URL ?>/vault-access/<?= htmlspecialchars($params['token'] ?? '') ?>/download-all">⬇️ Tout télécharger (.zip)</a>
        <?php endif; ?>
        <?php foreach (Vault::CATEGORIES as $slug => $cat): ?>
            <?php $catEntries = array_values(array_filter($entries, fn($e) => $e['category'] === $slug)); ?>
            <?php if (!empty($catEntries)): ?>
                <h3 style="margin-top:1.5rem"><?= $cat['icon'] ?> <?= htmlspecialchars($cat['label']) ?></h3>
                <ul style="padding-left:1.2rem">
                    <?php foreach ($catEntries as $e): ?>
                        <li style="margin-bottom:.6rem">
                            <strong><?= htmlspecialchars($e['title']) ?></strong>
                            <?php if ($e['content']): ?><div style="white-space:pre-wrap;font-size:.9rem"><?= htmlspecialchars($e['content']) ?></div><?php endif; ?>
                            <?php if ($e['file_path']): ?><div style="font-size:.85rem">📎 <a href="<?= BASE_URL ?>/vault-access/<?= htmlspecialchars($params['token'] ?? '') ?>/entries/<?= $e['id'] ?>/file" target="_blank">📥 <?= htmlspecialchars($e['file_original']) ?></a></div><?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        <?php endforeach; ?>
        <?php if (empty($entries)): ?>
            <p class="empty-state">Le coffre-fort ne contient encore aucune entrée.</p>
        <?php endif; ?>
    <?php endif; ?>
<?php endif; ?>
</div>
</div>

<script>
const VAULT_TOKEN = <?= json_encode($params['token'] ?? '') ?>;

async function requestVaultAccess() {
    const r = await fetch(`${BASE_URL}/vault-access/${VAULT_TOKEN}/request`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    }).then(res => res.json());
    if (r.success) {
        window.location.reload();
    } else {
        alert(r.error || 'Erreur.');
    }
}
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
