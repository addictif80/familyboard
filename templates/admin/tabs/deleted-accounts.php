        <h2>Comptes supprimés</h2>
        <p style="color:var(--text-muted);font-size:.85rem;margin-bottom:1rem">
            Quand un compte (membre classique ou co-parent) est supprimé — par lui-même,
            l'administrateur de sa famille, ou un administrateur système — son contenu
            (documents, événements, journal parental, photos, liens proposés) est conservé,
            jamais supprimé automatiquement. La purge définitive ci-dessous est <strong>irréversible</strong>
            et supprime aussi les fichiers associés.
        </p>
        <?php if (empty($deletedUsers)): ?>
            <p class="empty-state">Aucun compte supprimé pour l'instant.</p>
        <?php else: ?>
        <table class="admin-table">
            <thead><tr><th>Nom</th><th>Email</th><th>Famille</th><th>Rôle</th><th>Supprimé par</th><th>Le</th><th>Statut</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($deletedUsers as $du): ?>
            <tr>
                <td><?= htmlspecialchars($du['name']) ?></td>
                <td><?= htmlspecialchars($du['email']) ?></td>
                <td><?= htmlspecialchars($du['family_name']) ?></td>
                <td><?= $du['role'] === 'admin' ? '👑' : ($du['role'] === 'coparent' ? '🔒' : '') ?> <?= htmlspecialchars($du['role']) ?></td>
                <td><?= ['self' => 'Lui-même', 'family_admin' => 'Admin famille', 'system_admin' => 'Admin système'][$du['deleted_by']] ?? htmlspecialchars($du['deleted_by']) ?></td>
                <td><?= htmlspecialchars(substr($du['deleted_at'], 0, 16)) ?></td>
                <td>
                    <?php if ($du['purged_at']): ?>
                        <span class="badge badge-danger" title="Purgé le <?= htmlspecialchars($du['purged_at']) ?>">🗑️ Purgé</span>
                    <?php else: ?>
                        <span class="badge badge-ok">📦 Données conservées</span>
                    <?php endif; ?>
                </td>
                <td style="display:flex;gap:.4rem;flex-wrap:wrap">
                    <?php if (!$du['purged_at']): ?>
                        <form method="POST" action="<?= BASE_URL ?>/admin/deleted-users/<?= $du['id'] ?>/purge"
                              onsubmit="return confirm('Purger définitivement les données de <?= htmlspecialchars(addslashes($du['name'])) ?> ? Cette action est irréversible : documents, événements, journal parental, photos et liens seront supprimés, fichiers compris.')">
                            <?= \App\Core\Csrf::field() ?>
                            <button class="btn btn-danger btn-sm">Purger les données</button>
                        </form>
                    <?php endif; ?>
                    <?php if (!empty($du['data_export_html']) || !empty($du['reason'])): ?>
                        <form method="POST" action="<?= BASE_URL ?>/admin/deleted-users/<?= $du['id'] ?>/resend-report"
                              onsubmit="return confirm('Renvoyer le rapport de suppression à <?= htmlspecialchars(addslashes($du['email'])) ?> ?')">
                            <?= \App\Core\Csrf::field() ?>
                            <button class="btn btn-secondary btn-sm">📧 Renvoyer le rapport</button>
                        </form>
                    <?php else: ?>
                        <span style="color:var(--text-muted);font-size:.72rem" title="Ce compte a été supprimé avant l'ajout de cette fonctionnalité">Rapport non disponible</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>

