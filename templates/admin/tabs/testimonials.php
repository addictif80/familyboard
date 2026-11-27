        <?php
            $pendingTestimonials = array_values(array_filter($testimonials, fn($t) => $t['status'] === 'pending'));
            $otherTestimonials = array_values(array_filter($testimonials, fn($t) => $t['status'] !== 'pending'));
        ?>
        <h2>💬 Témoignages</h2>
        <p style="color:var(--text-muted);font-size:.85rem;margin-bottom:1rem">
            Les témoignages soumis par les familles (réglages → « Partagez votre avis ») arrivent
            ici en attente de validation. Une fois approuvés, ils s'affichent sur la page d'accueil
            publique, dans l'ordre choisi (plus petit d'abord).
        </p>

        <?php if ($pendingTestimonials): ?>
        <h3>⏳ En attente (<?= count($pendingTestimonials) ?>)</h3>
        <table class="admin-table" style="margin-bottom:1.5rem">
            <thead><tr><th>Auteur</th><th>Témoignage</th><th>Note</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($pendingTestimonials as $t): ?>
                <tr>
                    <td><?= htmlspecialchars($t['author_name']) ?><?= $t['author_role'] ? '<div style="color:var(--text-muted);font-size:.8rem">' . htmlspecialchars($t['author_role']) . '</div>' : '' ?><?= $t['family_name'] ? '<div style="color:var(--text-muted);font-size:.75rem">' . htmlspecialchars($t['family_name']) . '</div>' : '' ?></td>
                    <td style="max-width:340px;white-space:pre-wrap"><?= htmlspecialchars($t['content']) ?></td>
                    <td><?= $t['rating'] ? str_repeat('⭐', (int)$t['rating']) : '—' ?></td>
                    <td style="display:flex;gap:.3rem;flex-wrap:wrap">
                        <form method="POST" action="<?= BASE_URL ?>/admin/testimonials/<?= $t['id'] ?>/approve"><?= \App\Core\Csrf::field() ?>
                            <button type="submit" class="btn btn-primary btn-sm">✅ Approuver</button>
                        </form>
                        <form method="POST" action="<?= BASE_URL ?>/admin/testimonials/<?= $t['id'] ?>/reject"><?= \App\Core\Csrf::field() ?>
                            <button type="submit" class="btn btn-secondary btn-sm">Rejeter</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>

        <div class="card" style="padding:1.25rem;max-width:680px;margin-bottom:1.5rem">
            <h3 style="margin-top:0">Ajouter un témoignage manuellement</h3>
            <form method="POST" action="<?= BASE_URL ?>/admin/testimonials"><?= \App\Core\Csrf::field() ?>
                <div class="form-row">
                    <div class="form-group">
                        <label>Auteur</label>
                        <input type="text" name="author_name" required>
                    </div>
                    <div class="form-group">
                        <label>Rôle (optionnel)</label>
                        <input type="text" name="author_role" placeholder="Papa de 3 enfants…">
                    </div>
                    <div class="form-group">
                        <label>Note</label>
                        <select name="rating">
                            <option value="">—</option>
                            <?php for ($i = 5; $i >= 1; $i--): ?><option value="<?= $i ?>"><?= str_repeat('⭐', $i) ?></option><?php endfor; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label>Témoignage</label>
                    <textarea name="content" rows="3" required></textarea>
                </div>
                <div class="form-group">
                    <label><input type="checkbox" name="approve_now" value="1"> Publier immédiatement</label>
                </div>
                <button type="submit" class="btn btn-primary btn-sm">Enregistrer</button>
            </form>
        </div>

        <h3>Approuvés / rejetés (<?= count($otherTestimonials) ?>)</h3>
        <table class="admin-table">
            <thead><tr><th>Auteur</th><th>Témoignage</th><th>Statut</th><th>Ordre</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($otherTestimonials as $t): ?>
                <tr>
                    <td><?= htmlspecialchars($t['author_name']) ?><?= $t['author_role'] ? '<div style="color:var(--text-muted);font-size:.8rem">' . htmlspecialchars($t['author_role']) . '</div>' : '' ?></td>
                    <td style="max-width:300px;white-space:pre-wrap"><?= htmlspecialchars(mb_strimwidth($t['content'], 0, 140, '…')) ?></td>
                    <td><?= $t['status'] === 'approved' ? '✅ Publié' : 'Rejeté' ?></td>
                    <td>
                        <form method="POST" action="<?= BASE_URL ?>/admin/testimonials/<?= $t['id'] ?>/order" style="display:flex;gap:.3rem"><?= \App\Core\Csrf::field() ?>
                            <input type="number" name="sort_order" value="<?= $t['sort_order'] ?>" style="width:70px">
                            <button type="submit" class="btn btn-secondary btn-sm">OK</button>
                        </form>
                    </td>
                    <td style="display:flex;gap:.3rem">
                        <?php if ($t['status'] !== 'approved'): ?>
                        <form method="POST" action="<?= BASE_URL ?>/admin/testimonials/<?= $t['id'] ?>/approve"><?= \App\Core\Csrf::field() ?>
                            <button type="submit" class="btn btn-secondary btn-sm">Approuver</button>
                        </form>
                        <?php endif; ?>
                        <form method="POST" action="<?= BASE_URL ?>/admin/testimonials/<?= $t['id'] ?>/delete" onsubmit="return confirmSubmit(this, 'Supprimer ce témoignage ?')"><?= \App\Core\Csrf::field() ?>
                            <button type="submit" class="btn btn-danger btn-sm">🗑</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($otherTestimonials)): ?>
                <tr><td colspan="5" class="empty-state">Aucun témoignage approuvé ou rejeté.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>

