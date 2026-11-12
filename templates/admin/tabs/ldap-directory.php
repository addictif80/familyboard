        <h2>Annuaire LDAP</h2>
        <p style="color:var(--text-muted);font-size:.85rem;margin-bottom:1rem">
            Lecture seule — restreint aux groupes gérés par FamilyBoard (interroge le serveur LDAP en direct,
            ne modifie rien).
        </p>
        <?php if (!$ldapDirectory['available']): ?>
            <p>Extension LDAP non disponible sur ce serveur.</p>
        <?php else: ?>
            <div class="card" style="padding:1rem;margin-bottom:1rem">
                <h3 style="margin-top:0">Groupes globaux</h3>
                <?php foreach ($ldapDirectory['global_groups'] as $g): ?>
                    <p style="margin-bottom:.5rem">
                        <strong><?= htmlspecialchars($g['name']) ?></strong>
                        (<?= count($g['members']) ?> membre<?= count($g['members']) === 1 ? '' : 's' ?>)
                        <?php if ($g['members']): ?>
                        <ul style="margin:.25rem 0 0">
                            <?php foreach ($g['members'] as $m): ?><li style="font-size:.85rem"><?= htmlspecialchars($m) ?></li><?php endforeach; ?>
                        </ul>
                        <?php endif; ?>
                    </p>
                <?php endforeach; ?>
            </div>

            <?php foreach ($ldapDirectory['families'] as $fam): ?>
            <div class="card" style="padding:1rem;margin-bottom:1rem">
                <h3 style="margin-top:0">Famille #<?= $fam['family_id'] ?> — <?= htmlspecialchars($fam['family_name']) ?></h3>
                <p style="margin-bottom:.5rem">
                    <strong><?= htmlspecialchars($fam['group']) ?></strong>
                    (<?= count($fam['group_members']) ?> membre<?= count($fam['group_members']) === 1 ? '' : 's' ?>)
                    <?php if ($fam['group_members']): ?>
                    <ul style="margin:.25rem 0 0">
                        <?php foreach ($fam['group_members'] as $m): ?><li style="font-size:.85rem"><?= htmlspecialchars($m) ?></li><?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                </p>
                <p style="margin-bottom:0">
                    <strong><?= htmlspecialchars($fam['admin_group']) ?></strong>
                    (<?= count($fam['admin_group_members']) ?> membre<?= count($fam['admin_group_members']) === 1 ? '' : 's' ?>)
                    <?php if ($fam['admin_group_members']): ?>
                    <ul style="margin:.25rem 0 0">
                        <?php foreach ($fam['admin_group_members'] as $m): ?><li style="font-size:.85rem"><?= htmlspecialchars($m) ?></li><?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                </p>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>

