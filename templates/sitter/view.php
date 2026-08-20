<?php
$pageTitle = 'Accès baby-sitter';
ob_start();

/** "il y a 2h" / "il y a 20 min" à partir d'un datetime UTC — au jour près, suffisant ici. */
function sitterAgo(?string $utcDatetime): ?string
{
    if (!$utcDatetime) return null;
    $diffMin = (int)round((time() - strtotime($utcDatetime . ' UTC')) / 60);
    if ($diffMin < 1) return "à l'instant";
    if ($diffMin < 60) return "il y a {$diffMin} min";
    $h = intdiv($diffMin, 60);
    $m = $diffMin % 60;
    return $m > 0 ? "il y a {$h}h{$m}" : "il y a {$h}h";
}
?>
<div class="card" style="max-width:640px;margin:2rem auto;padding:1.5rem">
<?php if (!$link): ?>
    <h2>⛔ Lien invalide ou expiré</h2>
    <p style="color:var(--text-muted)">Ce lien d'accès n'est plus valable. Demandez-en un nouveau à la famille.</p>
<?php else: ?>
    <h2>👶 <?= htmlspecialchars($family['name'] ?? 'Famille') ?> — <?= htmlspecialchars($link['label']) ?></h2>
    <p style="color:var(--text-muted);font-size:.85rem">
        Accès valable jusqu'au <?= \App\Core\DateHelper::fromUtc($link['expires_at'], 'd/m/Y à H:i') ?>.
    </p>

    <?php if (!empty($link['instructions'])): ?>
    <div style="background:color-mix(in srgb, var(--accent) 12%, transparent);border-left:3px solid var(--accent);border-radius:6px;padding:.85rem 1rem;margin-top:1.25rem">
        <strong>📋 Consignes</strong>
        <p style="margin-top:.4rem;white-space:pre-wrap"><?= htmlspecialchars($link['instructions']) ?></p>
    </div>
    <?php endif; ?>

    <h3 style="margin-top:1.5rem">📞 Contacts des parents</h3>
    <?php if (empty($parents)): ?>
        <p style="color:var(--text-muted);font-size:.85rem">Aucun contact renseigné.</p>
    <?php else: ?>
        <ul style="padding-left:1.2rem">
            <?php foreach ($parents as $p): ?>
                <li>
                    <strong><?= htmlspecialchars($p['name']) ?></strong>
                    <?php if (!empty($p['phone'])): ?>
                        — <a href="tel:<?= htmlspecialchars($p['phone']) ?>"><?= htmlspecialchars($p['phone']) ?></a>
                    <?php else: ?>
                        <small style="color:var(--text-muted)">(téléphone non renseigné)</small>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <h3 style="margin-top:1.5rem">🚑 Numéros de secours</h3>
    <?php if (empty($emergencyContacts)): ?>
        <p style="color:var(--text-muted);font-size:.85rem">Aucun numéro d'urgence enregistré.</p>
    <?php else: ?>
        <ul style="padding-left:1.2rem">
            <?php foreach ($emergencyContacts as $c): ?>
                <li>
                    <strong><?= htmlspecialchars($c['first_name']) ?></strong>
                    — <a href="tel:<?= htmlspecialchars($c['phone']) ?>"><?= htmlspecialchars($c['phone']) ?></a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <?php if (!empty($babies)): ?>
    <h3 style="margin-top:1.5rem">🍼 Suivi bébé</h3>
    <?php foreach ($babies as $b): ?>
        <?php
            $le = $b['last_events'];
            $feeding = sitterAgo($le['feeding'] ?? null);
            $diaper  = sitterAgo(max($le['diaper'] ?? '', $le['stool'] ?? '', $le['urine'] ?? '') ?: null);
            $bath    = sitterAgo($le['bath'] ?? null);
        ?>
        <div style="border:1px solid var(--border);border-radius:8px;padding:.75rem 1rem;margin-bottom:.75rem">
            <strong><?= htmlspecialchars($b['baby']['name']) ?></strong>
            <div style="font-size:.85rem;margin-top:.4rem;display:flex;flex-direction:column;gap:.3rem">
                <div>🍼 Dernier repas : <?= $feeding ?: 'aucun enregistré' ?></div>
                <div>🧷 Dernier change : <?= $diaper ?: 'aucun enregistré' ?></div>
                <div>🛁 Dernier bain : <?= $bath ?: 'aucun enregistré' ?></div>
                <div>😴 Sommeil :
                    <?php if ($b['active_sleep']): ?>
                        en cours depuis <?= sitterAgo($b['active_sleep']['start_at']) ?>
                    <?php elseif ($b['last_sleep']): ?>
                        terminé <?= sitterAgo($b['last_sleep']['end_at'] ?? $b['last_sleep']['start_at']) ?>
                    <?php else: ?>
                        aucun enregistré
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
    <?php endif; ?>
<?php endif; ?>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
