<?php
$pageTitle = $meta['label'];
ob_start();
?>
<div class="support-page">
    <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:.5rem;flex-wrap:wrap">
        <a href="<?= BASE_URL ?>/" class="btn btn-secondary btn-sm">← Retour</a>
        <h2 style="margin:0;flex:1"><?= $meta['icon'] ?> <?= htmlspecialchars($meta['label']) ?></h2>
    </div>
    <p style="color:var(--text-muted);font-size:.82rem;margin-bottom:1.5rem">
        Articles des 30 derniers jours détectés pour cette catégorie d'alerte.
    </p>

    <?php if (!$alerts): ?>
        <div class="card" style="padding:1.25rem;max-width:700px">
            <p style="color:var(--text-muted);margin:0">Aucune alerte récente dans cette catégorie.</p>
        </div>
    <?php else: ?>
        <div style="display:flex;flex-direction:column;gap:.75rem;max-width:700px">
            <?php foreach ($alerts as $a): ?>
                <div class="card" style="padding:1rem">
                    <div style="display:flex;justify-content:space-between;gap:1rem;align-items:baseline;flex-wrap:wrap">
                        <strong><?= htmlspecialchars($a['title']) ?></strong>
                        <span style="color:var(--text-muted);font-size:.78rem;white-space:nowrap">
                            <?= \App\Core\DateHelper::fromUtc($a['published_at'] ?? $a['created_at'], 'd/m/Y H:i') ?>
                        </span>
                    </div>
                    <?php if (!empty($a['summary'])): ?>
                        <p style="margin:.5rem 0 0;color:var(--text-muted)"><?= htmlspecialchars($a['summary']) ?></p>
                    <?php endif; ?>
                    <div style="margin-top:.65rem;display:flex;justify-content:space-between;align-items:center;gap:1rem">
                        <span style="color:var(--text-muted);font-size:.78rem"><?= htmlspecialchars($a['source_name']) ?></span>
                        <a href="<?= htmlspecialchars($a['source_url']) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-secondary btn-sm">Lire l'article →</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
