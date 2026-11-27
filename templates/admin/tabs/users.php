        <?php $filterFamily = (int)($_GET['family'] ?? 0); ?>
        <h2>Utilisateurs<?= $filterFamily ? ' · Famille #' . $filterFamily : '' ?></h2>

        <div class="card" style="padding:1rem;margin-bottom:1rem">
            <h3 style="margin-top:0">Migration LDAP</h3>
            <p style="color:var(--text-muted);font-size:.85rem">
                <?= $legacyLdapUserCount ?> compte(s) sans identité LDAP (créés avant cette intégration).
                Leur mot de passe FamilyBoard actuel est irrécupérable (hash) : la migration leur envoie un
                e-mail de réinitialisation et invalide leurs sessions en cours — la connexion suivante
                provisionnera leur compte LDAP/Nextcloud avec le nouveau mot de passe choisi.
            </p>
            <form method="POST" action="<?= BASE_URL ?>/admin/users/migrate-ldap" onsubmit="return confirm('Envoyer un e-mail de réinitialisation à <?= $legacyLdapUserCount ?> compte(s) et déconnecter leurs sessions en cours ?')">
                <?= \App\Core\Csrf::field() ?>
                <button type="submit" class="btn btn-secondary btn-sm" <?= $legacyLdapUserCount ? '' : 'disabled' ?>>📧 Migrer les comptes restants vers LDAP</button>
            </form>
            <?php if ($ldapMigrationReport): ?>
            <p style="margin-top:.75rem;font-size:.85rem">
                <strong><?= count($ldapMigrationReport['sent']) ?></strong> e-mail(s) envoyé(s).
                <?php if ($ldapMigrationReport['failed']): ?>
                <br><span style="color:var(--danger,#c53030)"><?= count($ldapMigrationReport['failed']) ?> échec(s) :</span>
                <ul>
                    <?php foreach ($ldapMigrationReport['failed'] as $f): ?><li><?= htmlspecialchars($f) ?></li><?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </p>
            <?php endif; ?>

            <form method="POST" action="<?= BASE_URL ?>/admin/users/resync-ldap-roles" style="margin-top:.75rem">
                <?= \App\Core\Csrf::field() ?>
                <button type="submit" class="btn btn-secondary btn-sm">🔄 Resynchroniser les rôles LDAP</button>
            </form>
            <?php if ($ldapRoleResyncReport): ?>
            <p style="margin-top:.75rem;font-size:.85rem">
                <strong><?= $ldapRoleResyncReport['count'] ?></strong> compte(s) resynchronisé(s).
                <?php if ($ldapRoleResyncReport['errors']): ?>
                <br><span style="color:var(--danger,#c53030)"><?= count($ldapRoleResyncReport['errors']) ?> échec(s) :</span>
                <ul>
                    <?php foreach ($ldapRoleResyncReport['errors'] as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </p>
            <?php endif; ?>

            <form method="POST" action="<?= BASE_URL ?>/admin/users/audit-ldap" style="margin-top:.75rem">
                <?= \App\Core\Csrf::field() ?>
                <button type="submit" class="btn btn-secondary btn-sm">🔍 Vérifier la cohérence LDAP</button>
            </form>
            <?php if ($ldapAuditReport): ?>
            <p style="margin-top:.75rem;font-size:.85rem">
                <?= $ldapAuditReport['checked'] ?> compte(s) vérifié(s).
                <?php if ($ldapAuditReport['issues']): ?>
                <br><span style="color:var(--danger,#c53030)"><?= count($ldapAuditReport['issues']) ?> écart(s) :</span>
                <ul>
                    <?php foreach ($ldapAuditReport['issues'] as $issue): ?><li><?= htmlspecialchars($issue) ?></li><?php endforeach; ?>
                </ul>
                <?php else: ?>
                <br>✅ Aucun écart constaté.
                <?php endif; ?>
            </p>
            <?php endif; ?>

            <form method="POST" action="<?= BASE_URL ?>/admin/users/resync-nextcloud-visibility" style="margin-top:.75rem">
                <?= \App\Core\Csrf::field() ?>
                <button type="submit" class="btn btn-secondary btn-sm">👁️ Resynchroniser la visibilité Nextcloud (familles amies)</button>
            </form>
            <p style="margin-top:.4rem;font-size:.8rem;color:var(--text-muted)">
                Recrée/repeuple le groupe Nextcloud dédié à chaque paire de familles déjà amies — à lancer une fois après
                activation du réglage « Restreindre le partage aux membres du groupe » côté Nextcloud, ou si une amitié
                acceptée avant la mise en place de cette isolation ne s'est jamais vue attribuer son groupe.
            </p>
            <?php if ($nextcloudVisibilityResyncReport): ?>
            <p style="margin-top:.75rem;font-size:.85rem">
                <strong><?= $nextcloudVisibilityResyncReport['count'] ?></strong> paire(s) de familles resynchronisée(s).
                <?php if ($nextcloudVisibilityResyncReport['errors']): ?>
                <br><span style="color:var(--danger,#c53030)"><?= count($nextcloudVisibilityResyncReport['errors']) ?> échec(s) :</span>
                <ul>
                    <?php foreach ($nextcloudVisibilityResyncReport['errors'] as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </p>
            <?php endif; ?>
        </div>

        <table class="admin-table">
            <thead><tr><th>#</th><th>Nom</th><th>Email</th><th>Famille</th><th>Rôle</th><th>Statut</th><th>Dernière connexion</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($users as $u): ?>
            <?php if ($filterFamily && $u['family_id'] !== $filterFamily) continue; ?>
            <tr class="<?= $u['blocked_at'] ? 'row-blocked' : '' ?>">
                <td><?= $u['id'] ?></td>
                <td><?= htmlspecialchars($u['name']) ?></td>
                <td><?= htmlspecialchars($u['email']) ?></td>
                <td><?= htmlspecialchars($u['family_name']) ?></td>
                <td><?= $u['role'] === 'admin' ? '👑' : '' ?> <?= $u['role'] ?></td>
                <td>
                    <?php if ($u['blocked_at']): ?>
                        <span class="badge badge-danger" title="<?= htmlspecialchars($u['blocked_reason'] ?? '') ?>">🚫 Bloqué</span>
                    <?php else: ?>
                        <span class="badge badge-ok">✅ Actif</span>
                    <?php endif; ?>
                    <?php if ($u['storage_quota_unlimited']): ?>
                        <span class="badge" title="Quota de stockage perso illimité (Go/Synology/Nextcloud)">♾️ Stockage illimité</span>
                    <?php endif; ?>
                </td>
                <td style="white-space:nowrap">
                    <?= $u['last_login_at'] ? date('d/m/Y H:i', strtotime($u['last_login_at'])) : '<span style="color:var(--text-muted)">Jamais</span>' ?>
                </td>
                <td style="display:flex;gap:.4rem;flex-wrap:wrap">
                    <form method="POST" action="<?= BASE_URL ?>/admin/users/<?= $u['id'] ?>/storage-unlimited">
                        <?= \App\Core\Csrf::field() ?>
                        <input type="hidden" name="enabled" value="<?= $u['storage_quota_unlimited'] ? '0' : '1' ?>">
                        <button class="btn btn-secondary btn-sm" title="Quota de stockage perso (Go/Synology/Nextcloud)">
                            <?= $u['storage_quota_unlimited'] ? '♾️ Retirer l\'illimité' : '♾️ Rendre illimité' ?>
                        </button>
                    </form>
                    <?php if ($u['blocked_at']): ?>
                        <form method="POST" action="<?= BASE_URL ?>/admin/users/<?= $u['id'] ?>/unblock"><?= \App\Core\Csrf::field() ?>
                            <button class="btn btn-secondary btn-sm">Débloquer</button>
                        </form>
                    <?php else: ?>
                        <form method="POST" action="<?= BASE_URL ?>/admin/users/<?= $u['id'] ?>/block" style="display:flex;gap:.3rem"><?= \App\Core\Csrf::field() ?>
                            <input type="text" name="reason" placeholder="Raison (optionnel)" style="font-size:.78rem;padding:.2rem .4rem;border:1px solid var(--border);border-radius:4px;width:140px">
                            <button class="btn btn-danger btn-sm">Bloquer</button>
                        </form>
                        <form method="POST" action="<?= BASE_URL ?>/admin/users/<?= $u['id'] ?>/impersonate" onsubmit="return confirm('Se connecter en tant que <?= htmlspecialchars(addslashes($u['name'])) ?> ? Cette action est journalisée.')"><?= \App\Core\Csrf::field() ?>
                            <button class="btn btn-secondary btn-sm" title="Se connecter en tant que cet utilisateur">🕵️ Impersoner</button>
                        </form>
                    <?php endif; ?>
                    <?php if ($u['role'] !== 'admin'): ?>
                        <form method="POST" action="<?= BASE_URL ?>/admin/users/<?= $u['id'] ?>/delete" style="display:flex;gap:.3rem;align-items:center;flex-wrap:wrap"
                              onsubmit="return confirmUserDelete(this, '<?= htmlspecialchars(addslashes($u['name']), ENT_QUOTES) ?>')">
                            <?= \App\Core\Csrf::field() ?>
                            <input type="text" name="reason" placeholder="Motif (requis)" required style="font-size:.78rem;padding:.2rem .4rem;border:1px solid var(--border);border-radius:4px;width:140px">
                            <label style="display:flex;align-items:center;gap:.25rem;font-size:.72rem;color:var(--text-muted);white-space:nowrap" title="Supprime aussi le contenu normalement conservé (documents, événements, photos…), pas seulement le compte">
                                <input type="checkbox" name="purge_all" value="1"> Tout supprimer
                            </label>
                            <button class="btn btn-danger btn-sm">🗑️ Supprimer</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

