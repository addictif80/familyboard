        <?php $subsByFamily = []; foreach ($familySubscriptions ?? [] as $fs) { $subsByFamily[$fs['family_id']] = $fs; } ?>
        <h2>Familles (<?= count($families) ?>)</h2>
        <table class="admin-table">
            <thead><tr><th>#</th><th>Nom</th><th>Membres</th><th>Créé le</th><th>Abonnement</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($families as $f): ?>
            <?php $fs = $subsByFamily[$f['id']] ?? null; ?>
            <tr>
                <td><?= $f['id'] ?></td>
                <td><strong><?= htmlspecialchars($f['name']) ?></strong></td>
                <td><?= $f['member_count'] ?></td>
                <td><?= \App\Core\DateHelper::fromUtc($f['created_at'], 'd/m/Y') ?></td>
                <td>
                    <?php if ($fs): ?>
                        <span class="badge"><?= htmlspecialchars($fs['plan_name'] ?? '—') ?> · <?= htmlspecialchars($fs['status']) ?><?= $fs['manual'] ? ' (manuel)' : '' ?></span>
                    <?php else: ?>
                        <span style="color:var(--text-muted);font-size:.8rem">Gratuit</span>
                    <?php endif; ?>
                </td>
                <td style="display:flex;gap:.3rem;flex-wrap:wrap;align-items:center">
                    <a href="<?= BASE_URL ?>/admin?tab=users&family=<?= $f['id'] ?>" class="btn btn-secondary btn-sm">Voir membres</a>
                    <form method="POST" action="<?= BASE_URL ?>/admin/families/<?= $f['id'] ?>/storage-unlimited">
                        <?= \App\Core\Csrf::field() ?>
                        <input type="hidden" name="enabled" value="<?= $f['storage_quota_unlimited'] ? '0' : '1' ?>">
                        <button class="btn btn-secondary btn-sm" title="Quota familial partagé (stockage)">
                            <?= $f['storage_quota_unlimited'] ? '♾️ Stockage illimité (désactiver)' : '♾️ Stockage illimité' ?>
                        </button>
                    </form>
                    <?php if ($fs && $fs['manual']): ?>
                    <form method="POST" action="<?= BASE_URL ?>/admin/families/<?= $f['id'] ?>/subscription/revoke"><?= \App\Core\Csrf::field() ?>
                        <button type="submit" class="btn btn-danger btn-sm">Retirer le palier manuel</button>
                    </form>
                    <?php else: ?>
                    <form method="POST" action="<?= BASE_URL ?>/admin/families/<?= $f['id'] ?>/subscription/grant" style="display:flex;gap:.3rem;align-items:center"><?= \App\Core\Csrf::field() ?>
                        <select name="plan_id" style="min-width:170px">
                            <?php foreach ($plans as $p): ?><option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?> (<?= htmlspecialchars($p['code']) ?>)</option><?php endforeach; ?>
                        </select>
                        <input type="date" name="until" title="Valable jusqu'au (vide = sans limite)" style="max-width:140px">
                        <button type="submit" class="btn btn-secondary btn-sm">Offrir ce palier</button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

