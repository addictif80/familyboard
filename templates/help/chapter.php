<?php
use App\Core\HelpGuide;
$pageTitle = $meta['title'] . ' — Guide d\'utilisation';
$role = $user['role'] ?? 'member';
ob_start();
require __DIR__ . '/_shell_open.php';
?>
<div style="font-size:.82rem;margin-bottom:.6rem"><a href="<?= BASE_URL ?>/aide">Guide</a> › <?= htmlspecialchars($meta['title']) ?></div>
<h1><?= $meta['icon'] ?> <?= htmlspecialchars($meta['title']) ?></h1>
<p class="hg-sum"><?= $chapter['summary'] ?></p>

<div class="hg-who">
    <?php foreach (($chapter['who'] ?? []) as $w): ?><span class="hg-badge"><?= htmlspecialchars($w) ?></span><?php endforeach; ?>
    <?php if ($isPremium): ?><span class="hg-badge prem" title="Selon la configuration de la plateforme">⭐ Offre Premium</span><?php endif; ?>
</div>

<?php $secs = $chapter['sections'] ?? []; if (count($secs) > 3): ?>
<div class="hg-note" style="margin-top:0"><strong>Dans cette page :</strong>
    <?php foreach ($secs as $i => $s): ?><a href="#<?= htmlspecialchars($s['id'] ?? ('s' . $i)) ?>"><?= htmlspecialchars($s['title']) ?></a><?= $i < count($secs) - 1 ? ' · ' : '' ?><?php endforeach; ?>
</div>
<?php endif; ?>

<?php foreach ($secs as $i => $s): ?>
<h2 id="<?= htmlspecialchars($s['id'] ?? ('s' . $i)) ?>"><?= htmlspecialchars($s['title']) ?></h2>
<?= $s['html'] ?>
<?php endforeach; ?>

<div class="hg-nav2">
    <?php if ($prev): $pm = HelpGuide::meta($prev); ?><a href="<?= BASE_URL ?>/aide/<?= $prev ?>">← <?= $pm['icon'] ?> <?= htmlspecialchars($pm['title']) ?></a><?php else: ?><span></span><?php endif; ?>
    <?php if ($next): $nm = HelpGuide::meta($next); ?><a href="<?= BASE_URL ?>/aide/<?= $next ?>"><?= $nm['icon'] ?> <?= htmlspecialchars($nm['title']) ?> →</a><?php endif; ?>
</div>
<?php
require __DIR__ . '/_shell_close.php';
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
