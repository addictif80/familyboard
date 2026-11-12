        <h2>Tickets de support (<?= count(array_filter($tickets, fn($t) => $t['status'] !== 'closed')) ?> ouverts)</h2>
        <?php if (empty($tickets)): ?>
            <p class="empty-state">Aucun ticket.</p>
        <?php else: ?>
        <table class="admin-table">
            <thead><tr><th>#</th><th>Sujet</th><th>Utilisateur</th><th>Famille</th><th>Statut</th><th>Mis à jour</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($tickets as $t): ?>
            <tr class="<?= $t['status'] === 'closed' ? 'row-muted' : '' ?>">
                <td><?= $t['id'] ?></td>
                <td><strong><?= htmlspecialchars($t['subject']) ?></strong><?php if (($t['source'] ?? 'user') === 'auto'): ?> <span class="badge badge-warn" title="Créé automatiquement suite à une erreur technique">🤖 Auto</span><?php endif; ?></td>
                <td><?= htmlspecialchars($t['user_name']) ?></td>
                <td><?= htmlspecialchars($t['family_name']) ?></td>
                <td><span class="badge badge-<?= $t['status'] === 'closed' ? 'ok' : ($t['status'] === 'in_progress' ? 'warn' : 'new') ?>"><?= match($t['status']) { 'open'=>'🆕 Ouvert','in_progress'=>'💬 En cours','closed'=>'✅ Fermé',default=>$t['status'] } ?></span></td>
                <td><?= \App\Core\DateHelper::fromUtc($t['updated_at'], 'd/m/Y H:i') ?></td>
                <td><a href="<?= BASE_URL ?>/admin/tickets/<?= $t['id'] ?>" class="btn btn-secondary btn-sm">Voir</a></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>

