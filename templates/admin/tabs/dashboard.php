        <div class="admin-page-head">
            <h2>Tableau de bord</h2>
            <p class="admin-page-sub">Vue d'ensemble de la plateforme</p>
        </div>
        <div class="admin-stats-grid">
            <div class="admin-stat-card">
                <div class="admin-stat-label">Familles actives</div>
                <div class="stat-val"><?= $stats['families'] ?></div>
                <?php if ($stats['families_month'] > 0): ?><div class="admin-stat-delta admin-stat-delta-up">+<?= $stats['families_month'] ?> ce mois</div><?php endif; ?>
            </div>
            <div class="admin-stat-card">
                <div class="admin-stat-label">Utilisateurs</div>
                <div class="stat-val"><?= $stats['users'] ?></div>
                <?php if ($stats['users_month'] > 0): ?><div class="admin-stat-delta admin-stat-delta-up">+<?= $stats['users_month'] ?> ce mois</div><?php endif; ?>
            </div>
            <div class="admin-stat-card">
                <div class="admin-stat-label">Stockage utilisé</div>
                <div class="stat-val"><?= \App\Core\PlatformHealth::formatBytes($stats['storage_bytes']) ?></div>
            </div>
            <div class="admin-stat-card <?= $stats['tickets'] ? 'stat-warn' : '' ?>">
                <div class="admin-stat-label">Tickets ouverts</div>
                <div class="stat-val"><?= $stats['tickets'] ?></div>
            </div>
        </div>

        <div class="card admin-recent-families">
            <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;border:none;margin-bottom:.6rem;padding-bottom:0">
                <h3 style="margin:0">Familles récemment créées</h3>
                <a href="<?= BASE_URL ?>/admin?tab=families" style="font-size:.8rem;font-weight:600">Voir tout →</a>
            </div>
            <?php if (empty($recentFamilies)): ?>
                <p class="empty-state">Aucune famille pour le moment.</p>
            <?php else: ?>
            <div class="admin-recent-list">
                <?php foreach ($recentFamilies as $f): ?>
                <div class="admin-recent-row">
                    <div class="admin-recent-name"><span class="admin-recent-icon">🏠</span><?= htmlspecialchars($f['name']) ?></div>
                    <span class="badge <?= $f['plan_name'] ? 'badge-ok' : '' ?>"><?= htmlspecialchars($f['plan_name'] ?? 'Gratuit') ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <script>
        (function () {
            document.querySelectorAll('.admin-stats-grid .stat-val').forEach(function (el) {
                var raw = el.textContent.trim();
                if (!/^[0-9]+$/.test(raw)) return; // laisse les valeurs non-entières (ex. "1.2 To") telles quelles
                var target = parseInt(raw, 10);
                if (!target) return;
                var start = Date.now(), duration = 800;
                (function step() {
                    var t = Math.min(1, (Date.now() - start) / duration);
                    var eased = 1 - Math.pow(1 - t, 3);
                    el.textContent = Math.round(target * eased);
                    if (t < 1) requestAnimationFrame(step);
                })();
            });
        })();
        </script>
