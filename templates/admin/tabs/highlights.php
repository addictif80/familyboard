        <h2>🏢 Mises en avant ABHD</h2>
        <p style="color:var(--text-muted);font-size:.85rem;margin-bottom:1rem">
            Met en avant d'autres services proposés par ABHD (éditeur de FamilyBoard) — jamais
            appelé "publicité" dans l'interface, et strictement réservé à cet usage interne (pas
            de régie tierce). Choisissez où chaque mise en avant apparaît : tableau de bord, pied
            de chaque e-mail, page d'accueil de chaque module, et/ou vue co-parent. Si plusieurs
            sont actives pour un même emplacement, une seule s'affiche à chaque chargement, tirée
            au hasard.
        </p>

        <div class="card" style="padding:1.25rem;max-width:640px;margin-bottom:1.5rem">
            <h3 style="margin-top:0">Ajouter</h3>
            <form method="POST" action="<?= BASE_URL ?>/admin/highlights" enctype="multipart/form-data"><?= \App\Core\Csrf::field() ?>
                <div class="form-group">
                    <label>Titre</label>
                    <input type="text" name="title" required>
                </div>
                <div class="form-group">
                    <label>Description courte (optionnel)</label>
                    <input type="text" name="description" maxlength="300">
                </div>
                <div class="form-group">
                    <label>Lien (http/https)</label>
                    <input type="url" name="url" required placeholder="https://...">
                </div>
                <div class="form-group">
                    <label>Image (optionnel)</label>
                    <input type="file" name="image" accept="image/*">
                </div>
                <div class="form-group">
                    <label>Emplacements</label>
                    <label class="radio-option"><input type="checkbox" name="show_dashboard" checked> Tableau de bord</label>
                    <label class="radio-option"><input type="checkbox" name="show_module_pages" checked> Page d'accueil de chaque module</label>
                    <label class="radio-option"><input type="checkbox" name="show_email" checked> Pied de chaque e-mail</label>
                </div>
                <div class="form-group">
                    <label class="radio-option"><input type="checkbox" name="show_modal"> Fenêtre modale à la première page vue après connexion</label>
                </div>
                <div class="form-group">
                    <label class="radio-option"><input type="checkbox" name="is_active" checked> Active</label>
                </div>
                <button type="submit" class="btn btn-primary btn-sm">Ajouter</button>
            </form>
        </div>

        <?php foreach ($highlights as $h): ?>
        <div class="card" style="padding:1.25rem;max-width:640px;margin-bottom:1rem">
            <h3 style="margin-top:0;display:flex;align-items:center;gap:.5rem">
                <?= htmlspecialchars($h['title']) ?>
                <?php if (!$h['is_active']): ?><span class="badge-custom">Inactive</span><?php endif; ?>
                <span style="margin-left:auto;font-size:.78rem;color:var(--text-muted)">👆 <?= (int)$h['click_count'] ?> clic(s)</span>
            </h3>
            <?php if ($h['image_path']): ?>
                <img src="<?= htmlspecialchars(BASE_URL . $h['image_path']) ?>" alt="" style="max-height:60px;margin-bottom:.5rem">
            <?php endif; ?>
            <form method="POST" action="<?= BASE_URL ?>/admin/highlights/<?= (int)$h['id'] ?>" enctype="multipart/form-data"><?= \App\Core\Csrf::field() ?>
                <div class="form-group">
                    <label>Titre</label>
                    <input type="text" name="title" value="<?= htmlspecialchars($h['title']) ?>" required>
                </div>
                <div class="form-group">
                    <label>Description courte</label>
                    <input type="text" name="description" maxlength="300" value="<?= htmlspecialchars($h['description'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Lien</label>
                    <input type="url" name="url" value="<?= htmlspecialchars($h['url']) ?>" required>
                </div>
                <div class="form-group">
                    <label>Remplacer l'image (optionnel)</label>
                    <input type="file" name="image" accept="image/*">
                </div>
                <div class="form-group">
                    <label>Emplacements</label>
                    <label class="radio-option"><input type="checkbox" name="show_dashboard" <?= $h['show_dashboard'] ? 'checked' : '' ?>> Tableau de bord</label>
                    <label class="radio-option"><input type="checkbox" name="show_module_pages" <?= $h['show_module_pages'] ? 'checked' : '' ?>> Page d'accueil de chaque module</label>
                    <label class="radio-option"><input type="checkbox" name="show_email" <?= $h['show_email'] ? 'checked' : '' ?>> Pied de chaque e-mail</label>
                </div>
                <div class="form-group">
                    <label class="radio-option"><input type="checkbox" name="show_modal" <?= $h['show_modal'] ? 'checked' : '' ?>> Fenêtre modale à la première page vue après connexion</label>
                </div>
                <div class="form-group">
                    <label class="radio-option"><input type="checkbox" name="is_active" <?= $h['is_active'] ? 'checked' : '' ?>> Active</label>
                </div>
                <div style="display:flex;gap:.5rem">
                    <button type="submit" class="btn btn-primary btn-sm">Enregistrer</button>
                </div>
            </form>
            <form method="POST" action="<?= BASE_URL ?>/admin/highlights/<?= (int)$h['id'] ?>/delete" style="margin-top:.5rem" onsubmit="return confirmSubmit(this,'Supprimer cette mise en avant ?')"><?= \App\Core\Csrf::field() ?>
                <button type="submit" class="btn btn-danger btn-sm">Supprimer</button>
            </form>
        </div>
        <?php endforeach; ?>
        <?php if (empty($highlights)): ?>
            <p style="color:var(--text-muted)">Aucune mise en avant pour l'instant.</p>
        <?php endif; ?>

