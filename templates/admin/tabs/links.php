        <h2>🔗 Liens certifiés</h2>
        <p style="color:var(--text-muted);font-size:.85rem;margin-bottom:1rem">
            Ces liens sont ajoutés au portail de liens de <strong>toutes les familles</strong>
            simultanément, avec un badge « ✓ Certifié » — aucune famille ne peut les modifier
            ou les supprimer, seulement les consulter. Publiés immédiatement, sans circuit de
            validation (contrairement aux liens proposés par les membres d'une famille).
        </p>

        <div class="card" style="padding:1.25rem;max-width:640px;margin-bottom:1.5rem">
            <h3 style="margin-top:0">Ajouter</h3>
            <form method="POST" action="<?= BASE_URL ?>/admin/links"><?= \App\Core\Csrf::field() ?>
                <div class="form-group">
                    <label>Adresse du site</label>
                    <input type="url" name="url" required placeholder="https://...">
                </div>
                <div class="form-group">
                    <label>Titre</label>
                    <input type="text" name="title">
                    <small style="color:var(--text-muted)">Laissez vide pour utiliser le titre détecté automatiquement.</small>
                </div>
                <div class="form-group">
                    <label>Description (optionnel)</label>
                    <input type="text" name="description" maxlength="300">
                </div>
                <div class="form-group">
                    <label class="radio-option"><input type="checkbox" name="visible_to_coparent" checked> Visible par un accès co-parent</label>
                </div>
                <button type="submit" class="btn btn-primary btn-sm">Ajouter</button>
            </form>
        </div>

        <?php foreach ($certifiedLinks as $l): ?>
        <div class="card" style="padding:1.25rem;max-width:640px;margin-bottom:1rem">
            <h3 style="margin-top:0;display:flex;align-items:center;gap:.5rem">
                <?= htmlspecialchars($l['title']) ?>
                <span style="margin-left:auto;font-size:.78rem;color:var(--text-muted)">👆 <?= (int)$l['click_count'] ?> clic(s)</span>
            </h3>
            <?php if ($l['image_path']): ?>
                <img src="<?= htmlspecialchars(BASE_URL . $l['image_path']) ?>" alt="" style="max-height:60px;margin-bottom:.5rem">
            <?php endif; ?>
            <form method="POST" action="<?= BASE_URL ?>/admin/links/<?= (int)$l['id'] ?>"><?= \App\Core\Csrf::field() ?>
                <div class="form-group">
                    <label>Titre</label>
                    <input type="text" name="title" value="<?= htmlspecialchars($l['title']) ?>" required>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <input type="text" name="description" maxlength="300" value="<?= htmlspecialchars($l['description'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Lien</label>
                    <input type="url" name="url" value="<?= htmlspecialchars($l['url']) ?>" required>
                </div>
                <div class="form-group">
                    <label class="radio-option"><input type="checkbox" name="visible_to_coparent" <?= $l['visible_to_coparent'] ? 'checked' : '' ?>> Visible par un accès co-parent</label>
                </div>
                <button type="submit" class="btn btn-primary btn-sm">Enregistrer</button>
            </form>
            <div style="display:flex;gap:.5rem;margin-top:.5rem">
                <?php if (empty($l['image_path'])): ?>
                <form method="POST" action="<?= BASE_URL ?>/admin/links/<?= (int)$l['id'] ?>/refresh-preview"><?= \App\Core\Csrf::field() ?>
                    <button type="submit" class="btn btn-secondary btn-sm" title="Réessayer de récupérer l'image du site">🔄 Réessayer l'image</button>
                </form>
                <?php endif; ?>
                <form method="POST" action="<?= BASE_URL ?>/admin/links/<?= (int)$l['id'] ?>/delete" onsubmit="return confirmSubmit(this,'Supprimer ce lien certifié pour toutes les familles ?')"><?= \App\Core\Csrf::field() ?>
                    <button type="submit" class="btn btn-danger btn-sm">Supprimer</button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($certifiedLinks)): ?>
            <p style="color:var(--text-muted)">Aucun lien certifié pour l'instant.</p>
        <?php endif; ?>

