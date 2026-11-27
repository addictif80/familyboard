        <h2>IPs bloquées</h2>
        <form method="POST" action="<?= BASE_URL ?>/admin/ips" class="admin-inline-form"><?= \App\Core\Csrf::field() ?>
            <input type="text" name="ip" placeholder="Adresse IP (ex: 1.2.3.4)" required style="width:180px">
            <input type="text" name="reason" placeholder="Raison (optionnel)" style="width:220px">
            <button class="btn btn-danger btn-sm">Bloquer cette IP</button>
        </form>
        <?php if (empty($blockedIps)): ?>
            <p class="empty-state">Aucune IP bloquée.</p>
        <?php else: ?>
        <table class="admin-table" style="margin-top:1rem">
            <thead><tr><th>IP</th><th>Raison</th><th>Ajoutée le</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($blockedIps as $ip): ?>
            <tr>
                <td><code><?= htmlspecialchars($ip['ip']) ?></code></td>
                <td><?= htmlspecialchars($ip['reason'] ?? '—') ?></td>
                <td><?= \App\Core\DateHelper::fromUtc($ip['created_at'], 'd/m/Y H:i') ?></td>
                <td>
                    <form method="POST" action="<?= BASE_URL ?>/admin/ips/<?= $ip['id'] ?>/delete"><?= \App\Core\Csrf::field() ?>
                        <button class="btn btn-secondary btn-sm">Débloquer</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>

