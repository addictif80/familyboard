        <h2>🩺 Santé plateforme</h2>
        <div class="admin-stats-grid">
            <div class="admin-stat-card <?= $cronStatus['stale'] ? 'stat-warn' : '' ?>">
                <div class="stat-val"><?= $cronStatus['last_run'] ? ($cronStatus['minutes_ago'] . ' min' ) : '—' ?></div>
                <div class="stat-label">Dernier passage cron<?= $cronStatus['stale'] ? ' ⚠️' : '' ?></div>
            </div>
            <div class="admin-stat-card <?= $errorStats['last_24h'] ? 'stat-warn' : '' ?>">
                <div class="stat-val"><?= $errorStats['last_24h'] ?></div>
                <div class="stat-label">Erreurs techniques (24h)</div>
            </div>
            <div class="admin-stat-card <?= $emailStats['last_24h']['fail'] ? 'stat-warn' : '' ?>">
                <div class="stat-val"><?= $emailStats['last_24h']['ok'] ?> / <?= $emailStats['last_24h']['ok'] + $emailStats['last_24h']['fail'] ?></div>
                <div class="stat-label">E-mails envoyés avec succès (24h)</div>
            </div>
            <div class="admin-stat-card"><div class="stat-val"><?= \App\Core\PlatformHealth::formatBytes($dbSizeBytes) ?></div><div class="stat-label">Taille base de données</div></div>
            <div class="admin-stat-card"><div class="stat-val"><?= \App\Core\PlatformHealth::formatBytes($storageSizeBytes) ?></div><div class="stat-label">Fichiers stockés</div></div>
        </div>

        <?php if ($cronStatus['stale']): ?>
        <div class="alert alert-error" style="margin-top:1rem">
            ⚠️ Le cron n'a pas tourné depuis <?= $cronStatus['last_run'] ? $cronStatus['minutes_ago'] . ' minutes' : 'que l\'on sache' ?> — les rappels par e-mail, la synchro CalDAV et les autres tâches planifiées sont à l'arrêt. Vérifiez la configuration crontab du serveur.
        </div>
        <?php endif; ?>

        <div class="card settings-section" style="margin-top:1rem">
            <h3>E-mails — 7 derniers jours</h3>
            <p>✅ <?= $emailStats['last_7d']['ok'] ?> envoyés · <?php if ($emailStats['last_7d']['fail']): ?><span style="color:var(--danger)">❌ <?= $emailStats['last_7d']['fail'] ?> échoués</span><?php else: ?>❌ 0 échoué<?php endif; ?></p>
        </div>

        <div class="card settings-section" style="margin-top:1rem">
            <h3>Erreurs techniques récentes (auto-détectées)</h3>
            <p style="color:var(--text-muted);font-size:.85rem"><?= $errorStats['last_7d'] ?> sur les 7 derniers jours.</p>
            <table class="admin-table">
                <thead><tr><th>Sujet</th><th>Famille</th><th>Date</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($errorStats['recent'] as $e): ?>
                <tr>
                    <td><?= htmlspecialchars($e['subject']) ?></td>
                    <td><?= htmlspecialchars($e['family_name']) ?></td>
                    <td><?= \App\Core\DateHelper::fromUtc($e['created_at'], 'd/m/Y H:i') ?></td>
                    <td><a href="<?= BASE_URL ?>/admin/tickets/<?= $e['id'] ?>" class="btn btn-secondary btn-sm">Voir</a></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($errorStats['recent'])): ?>
                    <tr><td colspan="4" class="empty-state">Aucune erreur technique détectée récemment.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

