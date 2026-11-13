<?php
use App\Core\HelpGuide;
$pageTitle = 'Guide d\'utilisation';
$role = $user['role'] ?? 'member';
$isCoparent = $role === 'coparent';
ob_start();
require __DIR__ . '/_shell_open.php';

$startCards = [
    'demarrer'  => 'Créer votre compte, rejoindre une famille, vos premiers pas.',
    'profils'   => 'Qui peut voir et faire quoi : administrateur, membre, ado, accès co-parent.',
    'tableau-de-bord' => 'Votre page d\'accueil personnalisable.',
    'mon-compte' => 'Profil, sécurité, notifications, export de vos données.',
];
?>
<h1>🧭 Guide d'utilisation de <?= htmlspecialchars(APP_NAME) ?></h1>
<p class="hg-sum">Tout ce que vous pouvez faire dans <?= htmlspecialchars(APP_NAME) ?>, expliqué pas à pas : chaque module, chaque action, chaque réglage.
Utilisez la recherche à gauche pour retrouver une fonction en quelques mots, ou parcourez les thèmes ci-dessous.</p>

<?php if ($isCoparent): ?>
<div class="hg-note">Votre accès est un <strong>accès co-parent</strong> restreint : ce guide ne présente que ce que vous voyez réellement dans votre espace.</div>
<?php elseif ($role === 'ado'): ?>
<div class="hg-note">Votre compte est un compte <strong>ado</strong> : certains modules (finances, juridique, administratif) sont masqués pour vous et n'apparaissent donc pas dans ce guide.</div>
<?php endif; ?>

<h2 style="border-top:0;margin-top:1rem">Par où commencer ?</h2>
<div class="hg-cards">
<?php foreach ($startCards as $slug => $txt): if (!HelpGuide::exists($slug) || !HelpGuide::isVisible($slug, $user, [])) continue; $m = HelpGuide::meta($slug); ?>
    <a class="hg-card" href="<?= BASE_URL ?>/aide/<?= $slug ?>"><b><?= $m['icon'] ?> <?= htmlspecialchars($m['title']) ?></b><span><?= htmlspecialchars($txt) ?></span></a>
<?php endforeach; ?>
</div>

<?php foreach ($groups as $g): if ($g['key'] === 'start') continue; ?>
<h2><?= $g['icon'] ?> <?= htmlspecialchars($g['title']) ?></h2>
<div class="hg-cards">
    <?php foreach ($g['items'] as $slug):
        $m = HelpGuide::meta($slug);
        $c = HelpGuide::load($slug, $role);
        $sum = trim(strip_tags($c['summary'] ?? ''));
    ?>
    <a class="hg-card" href="<?= BASE_URL ?>/aide/<?= htmlspecialchars($slug) ?>">
        <b><?= $m['icon'] ?> <?= htmlspecialchars($m['title']) ?></b>
        <span><?= htmlspecialchars($sum) ?></span>
    </a>
    <?php endforeach; ?>
</div>
<?php endforeach; ?>

<p style="margin-top:2rem;font-size:.85rem;color:var(--text-muted)">
    Une question qui n'est pas couverte ? Consultez la <a href="<?= BASE_URL ?>/faq">FAQ</a>
    ou <a href="<?= BASE_URL ?>/support">contactez le support</a>.
</p>
<?php
require __DIR__ . '/_shell_close.php';
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
