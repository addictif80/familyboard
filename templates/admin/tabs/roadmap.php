        <h2>🗺️ Roadmap</h2>
        <p style="color:var(--text-muted);font-size:.85rem;margin-bottom:1rem">
            Liste interne d'idées de développement, visible uniquement ici (jamais montrée aux
            familles). Les colonnes reflètent l'avancement : idée, en cours, fait.
        </p>

        <div class="card" style="padding:1.25rem;max-width:640px;margin-bottom:1.5rem">
            <h3 style="margin-top:0">Ajouter une idée</h3>
            <form method="POST" action="<?= BASE_URL ?>/admin/roadmap"><?= \App\Core\Csrf::field() ?>
                <div class="form-group">
                    <label>Titre</label>
                    <input type="text" name="title" required>
                </div>
                <div class="form-group">
                    <label>Description (optionnel)</label>
                    <textarea name="description" rows="3"></textarea>
                </div>
                <div class="form-group">
                    <label>Statut</label>
                    <select name="status">
                        <option value="idea">💡 Idée</option>
                        <option value="in_progress">🔧 En cours</option>
                        <option value="done">✅ Fait</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary btn-sm">Ajouter</button>
            </form>
        </div>

        <?php
        $roadmapLabels = ['idea' => '💡 Idée', 'in_progress' => '🔧 En cours', 'done' => '✅ Fait'];
        if (empty($roadmapItems)):
        ?>
            <p style="color:var(--text-muted)">Aucune idée pour le moment.</p>
        <?php else: foreach ($roadmapItems as $ri): ?>
        <div class="card" style="padding:1.25rem;max-width:640px;margin-bottom:1rem">
            <h3 style="margin-top:0;display:flex;align-items:center;gap:.5rem">
                <?= htmlspecialchars($ri['title']) ?>
                <span class="badge-custom"><?= $roadmapLabels[$ri['status']] ?></span>
            </h3>
            <form method="POST" action="<?= BASE_URL ?>/admin/roadmap/<?= (int)$ri['id'] ?>"><?= \App\Core\Csrf::field() ?>
                <div class="form-group">
                    <label>Titre</label>
                    <input type="text" name="title" value="<?= htmlspecialchars($ri['title']) ?>" required>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" rows="3"><?= htmlspecialchars($ri['description'] ?? '') ?></textarea>
                </div>
                <div class="form-group">
                    <label>Statut</label>
                    <select name="status">
                        <?php foreach ($roadmapLabels as $sv => $sl): ?>
                        <option value="<?= $sv ?>" <?= $ri['status'] === $sv ? 'selected' : '' ?>><?= $sl ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="display:flex;gap:.5rem">
                    <button type="submit" class="btn btn-primary btn-sm">Enregistrer</button>
                </div>
            </form>
            <form method="POST" action="<?= BASE_URL ?>/admin/roadmap/<?= (int)$ri['id'] ?>/delete" style="margin-top:.5rem" onsubmit="return confirm('Supprimer cette idée ?')"><?= \App\Core\Csrf::field() ?>
                <button type="submit" class="btn btn-danger btn-sm">Supprimer</button>
            </form>
        </div>
        <?php endforeach; endif; ?>

