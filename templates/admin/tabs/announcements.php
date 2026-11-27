        <h2>📣 Centre d'annonces</h2>
        <p style="color:var(--text-muted);font-size:.85rem;margin-bottom:1rem">
            Nouveautés, maintenances programmées, avertissements — publiés ici, visibles de tous
            les membres de toutes les familles sur la page « Annonces » de l'application. Un
            brouillon (non publié) n'est visible que dans ce panneau.
        </p>

        <div class="card" style="padding:1.25rem;max-width:680px;margin-bottom:1.5rem">
            <h3 style="margin-top:0"><?= $editingAnnouncement ? 'Modifier l\'annonce' : 'Nouvelle annonce' ?></h3>
            <form method="POST" action="<?= BASE_URL ?>/admin/announcements<?= $editingAnnouncement ? '/' . $editingAnnouncement['id'] : '' ?>"><?= \App\Core\Csrf::field() ?>
                <div class="form-row">
                    <div class="form-group flex-2">
                        <label>Titre</label>
                        <input type="text" name="title" required value="<?= htmlspecialchars($editingAnnouncement['title'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Type</label>
                        <select name="type">
                            <?php foreach (\App\Models\Announcement::TYPES as $slug => $t): ?>
                                <option value="<?= $slug ?>" <?= ($editingAnnouncement['type'] ?? '') === $slug ? 'selected' : '' ?>><?= $t['icon'] ?> <?= htmlspecialchars($t['label']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label>Contenu</label>
                    <textarea name="content" rows="4" required><?= htmlspecialchars($editingAnnouncement['content'] ?? '') ?></textarea>
                </div>
                <?php if (!$editingAnnouncement): ?>
                <div class="form-group">
                    <label><input type="checkbox" name="publish_now" value="1"> Publier immédiatement (sinon enregistrée en brouillon)</label>
                </div>
                <?php endif; ?>
                <div style="display:flex;gap:.5rem">
                    <button type="submit" class="btn btn-primary btn-sm">Enregistrer</button>
                    <?php if ($editingAnnouncement): ?>
                        <a href="<?= BASE_URL ?>/admin?tab=announcements" class="btn btn-secondary btn-sm">Annuler</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <h3>Annonces (<?= count($announcements) ?>)</h3>
        <table class="admin-table">
            <thead><tr><th>Titre</th><th>Type</th><th>Statut</th><th>Date</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($announcements as $a): ?>
                <?php $t = \App\Models\Announcement::TYPES[$a['type']]; ?>
                <tr>
                    <td><strong><?= htmlspecialchars($a['title']) ?></strong><div style="color:var(--text-muted);font-size:.8rem;max-width:320px;white-space:pre-wrap"><?= htmlspecialchars(mb_strimwidth($a['content'], 0, 140, '…')) ?></div></td>
                    <td><?= $t['icon'] ?> <?= htmlspecialchars($t['label']) ?></td>
                    <td><?= $a['published_at'] ? '✅ Publiée' : '📝 Brouillon' ?></td>
                    <td><?= $a['published_at'] ? \App\Core\DateHelper::fromUtc($a['published_at'], 'd/m/Y') : \App\Core\DateHelper::fromUtc($a['created_at'], 'd/m/Y') ?></td>
                    <td style="display:flex;gap:.3rem;flex-wrap:wrap">
                        <a href="<?= BASE_URL ?>/admin?tab=announcements&edit=<?= $a['id'] ?>" class="btn btn-secondary btn-sm">✏️</a>
                        <?php if ($a['published_at']): ?>
                            <form method="POST" action="<?= BASE_URL ?>/admin/announcements/<?= $a['id'] ?>/unpublish"><?= \App\Core\Csrf::field() ?>
                                <button type="submit" class="btn btn-secondary btn-sm">Dépublier</button>
                            </form>
                        <?php else: ?>
                            <form method="POST" action="<?= BASE_URL ?>/admin/announcements/<?= $a['id'] ?>/publish"><?= \App\Core\Csrf::field() ?>
                                <button type="submit" class="btn btn-secondary btn-sm">Publier</button>
                            </form>
                        <?php endif; ?>
                        <form method="POST" action="<?= BASE_URL ?>/admin/announcements/<?= $a['id'] ?>/delete" onsubmit="return confirmSubmit(this, 'Supprimer cette annonce ?')"><?= \App\Core\Csrf::field() ?>
                            <button type="submit" class="btn btn-danger btn-sm">🗑</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($announcements)): ?>
                <tr><td colspan="5" class="empty-state">Aucune annonce.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>

