        <h2>Journal d'impersonation</h2>
        <p style="color:var(--text-muted);font-size:.85rem;margin-bottom:1rem">
            Historique des connexions d'un admin système en tant qu'un membre de famille, pour du support.
        </p>
        <?php $impersonationLog = \App\Models\ImpersonationLog::getRecent(100); ?>
        <?php if (empty($impersonationLog)): ?>
            <p class="empty-state">Aucune impersonation enregistrée.</p>
        <?php else: ?>
        <table class="admin-table">
            <thead><tr><th>Admin</th><th>Utilisateur ciblé</th><th>IP</th><th>Début</th><th>Fin</th></tr></thead>
            <tbody>
            <?php foreach ($impersonationLog as $l): ?>
            <tr>
                <td><?= htmlspecialchars($l['admin_username']) ?></td>
                <td><?= htmlspecialchars($l['target_name']) ?> <span style="color:var(--text-muted)">(<?= htmlspecialchars($l['target_email']) ?>)</span></td>
                <td><code><?= htmlspecialchars($l['ip'] ?? '—') ?></code></td>
                <td><?= \App\Core\DateHelper::fromUtc($l['started_at'], 'd/m/Y H:i') ?></td>
                <td><?= $l['ended_at'] ? \App\Core\DateHelper::fromUtc($l['ended_at'], 'd/m/Y H:i') : '<span class="badge badge-warn">En cours</span>' ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>

