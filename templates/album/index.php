<?php
$pageTitle = 'Albums photo';
$extraJs   = ['album.js'];
ob_start();
?>
<div class="album-toolbar">
    <button class="btn btn-primary" onclick="albumOpenCreateModal()">+ Nouvel album</button>
</div>

<div class="album-grid">
    <?php foreach ($albums as $album): ?>
        <a class="album-card" href="<?= BASE_URL ?>/albums/<?= (int)$album['id'] ?>">
            <div class="album-cover" <?= $album['cover_path'] ? 'style="background-image:url(\'' . BASE_URL . htmlspecialchars($album['cover_path']) . '\')"' : '' ?>>
                <?php if (!$album['cover_path']): ?><span class="album-cover-placeholder">🖼️</span><?php endif; ?>
                <?php if ($album['custody_schedule_id']): ?><span class="album-shared-badge" title="Partagé avec le co-parent">🔒</span><?php endif; ?>
            </div>
            <div class="album-info">
                <strong><?= htmlspecialchars($album['title']) ?></strong>
                <span class="album-meta"><?= (int)$album['photo_count'] ?> photo<?= $album['photo_count'] > 1 ? 's' : '' ?> · <?= htmlspecialchars($album['user_name']) ?></span>
            </div>
        </a>
    <?php endforeach; ?>
    <?php if (empty($albums)): ?>
        <div class="empty-state-card">
            <p>🖼️ Aucun album pour l'instant. Créez le premier !</p>
        </div>
    <?php endif; ?>
</div>

<!-- Create album modal -->
<div class="modal-overlay" id="album-create-modal" style="display:none">
    <div class="modal">
        <div class="modal-header">
            <h3>🖼️ Nouvel album</h3>
            <button onclick="closeModal('album-create-modal')">✕</button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label>Titre</label>
                <input type="text" id="album-create-title" placeholder="Vacances d'été, anniversaire...">
            </div>
            <div class="form-group">
                <label>Description (optionnel)</label>
                <textarea id="album-create-description" rows="2"></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('album-create-modal')">Annuler</button>
            <button class="btn btn-primary" id="album-create-save-btn" onclick="albumCreate()">Créer</button>
        </div>
    </div>
</div>

<style>
.album-toolbar { margin-bottom: 1rem; display:flex; justify-content:flex-end; }
.album-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(180px,1fr)); gap:1rem; }
.album-card { display:block; background:var(--card-bg); border:1px solid var(--border); border-radius:12px; overflow:hidden; text-decoration:none; color:var(--text); transition:transform .15s; }
.album-card:hover { transform:translateY(-2px); }
.album-cover { aspect-ratio:1; background:var(--bg) center/cover no-repeat; display:flex; align-items:center; justify-content:center; position:relative; }
.album-cover-placeholder { font-size:2.5rem; opacity:.4; }
.album-shared-badge { position:absolute; top:.4rem; right:.4rem; background:rgba(0,0,0,.55); color:#fff; border-radius:50%; width:1.6rem; height:1.6rem; display:flex; align-items:center; justify-content:center; font-size:.85rem; }
.album-info { padding:.6rem .75rem; display:flex; flex-direction:column; gap:.15rem; }
.album-meta { font-size:.78rem; color:var(--text-muted); }
</style>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
