<?php
$pageTitle = $album['title'];
$extraJs   = ['album.js'];
ob_start();
?>
<div class="album-header">
    <a href="<?= BASE_URL ?>/albums" class="btn-text">← Tous les albums</a>
    <div class="album-header-row">
        <div>
            <h2 style="margin:.25rem 0"><?= htmlspecialchars($album['title']) ?></h2>
            <?php if ($album['description']): ?><p style="color:var(--text-muted);margin:0 0 .25rem"><?= nl2br(htmlspecialchars($album['description'])) ?></p><?php endif; ?>
            <span class="album-meta">Créé par <?= htmlspecialchars($album['user_name']) ?><?php if ($album['custody_schedule_id']): ?> · 🔒 Partagé avec le co-parent<?php endif; ?></span>
        </div>
        <?php if ($canManage): ?>
        <div class="album-header-actions">
            <button class="btn btn-secondary btn-sm" onclick="albumOpenEditModal(<?= (int)$album['id'] ?>, <?= htmlspecialchars(json_encode($album['title']), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($album['description'] ?? ''), ENT_QUOTES) ?>)">Renommer</button>
            <?php if ($user['role'] === 'admin'): ?>
            <button class="btn btn-secondary btn-sm" onclick="albumOpenShareModal(<?= (int)$album['id'] ?>, <?= (int)($album['custody_schedule_id'] ?? 0) ?>)">Partager</button>
            <button class="btn btn-secondary btn-sm" onclick="albumOpenPublicLinkModal(<?= (int)$album['id'] ?>, <?= htmlspecialchars(json_encode($shareLink ?: null), ENT_QUOTES) ?>)">🔗 Lien public</button>
            <?php endif; ?>
            <button class="btn btn-danger btn-sm" onclick="albumDelete(<?= (int)$album['id'] ?>)">Supprimer l'album</button>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($canManage): ?>
<div class="card album-upload-card">
    <form method="POST" action="<?= BASE_URL ?>/albums/<?= (int)$album['id'] ?>/photos" enctype="multipart/form-data"><?= \App\Core\Csrf::field() ?>
        <label class="btn btn-secondary" for="album-photo-input">
            📷 Ajouter des photos
            <input type="file" id="album-photo-input" name="image" accept="image/*" style="display:none" onchange="this.form.submit()">
        </label>
    </form>
</div>
<?php endif; ?>

<div class="album-photo-grid">
    <?php foreach ($photos as $photo): ?>
        <?php
            $canDeletePhoto = $user['role'] === 'admin' || (int)$photo['user_id'] === (int)$user['id'];
            $photoAuthor = $photo['user_name'] ?? $photo['uploader_name'] ?? 'Invité';
            $photoColor  = $photo['user_color'] ?? '#95A5A6';
        ?>
        <div class="album-photo">
            <img src="<?= BASE_URL . htmlspecialchars($photo['image_path']) ?>" alt="" loading="lazy" onclick="albumViewPhoto(this.src)">
            <div class="album-photo-meta">
                <span title="<?= htmlspecialchars($photoAuthor) ?>" style="color:<?= htmlspecialchars($photoColor) ?>">● <?= htmlspecialchars($photoAuthor) ?></span>
                <?php if ($canDeletePhoto): ?>
                    <button class="album-photo-delete" onclick="albumDeletePhoto(<?= (int)$photo['id'] ?>, this)" title="Supprimer">✕</button>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
    <?php if (empty($photos)): ?>
        <div class="empty-state-card">
            <p>📷 Aucune photo pour l'instant.</p>
        </div>
    <?php endif; ?>
</div>

<!-- Edit album modal -->
<div class="modal-overlay" id="album-edit-modal" style="display:none">
    <div class="modal">
        <div class="modal-header">
            <h3>Renommer l'album</h3>
            <button onclick="closeModal('album-edit-modal')">✕</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="album-edit-id">
            <div class="form-group">
                <label>Titre</label>
                <input type="text" id="album-edit-title">
            </div>
            <div class="form-group">
                <label>Description (optionnel)</label>
                <textarea id="album-edit-description" rows="2"></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('album-edit-modal')">Annuler</button>
            <button class="btn btn-primary" onclick="albumSaveEdit()">Enregistrer</button>
        </div>
    </div>
</div>

<!-- Share modal (admin only) -->
<div class="modal-overlay" id="album-share-modal" style="display:none">
    <div class="modal">
        <div class="modal-header">
            <h3>🔒 Partager avec le co-parent</h3>
            <button onclick="closeModal('album-share-modal')">✕</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="album-share-id">
            <p style="color:var(--text-muted);font-size:.85rem">Le co-parent ayant accès au planning de garde sélectionné pourra voir cet album et y ajouter ses propres photos (il ne pourra pas les modifier ni les supprimer).</p>
            <div class="form-group">
                <label>Planning de garde</label>
                <select id="album-share-schedule">
                    <option value="0">— Ne pas partager —</option>
                    <?php foreach ($schedules as $s): ?>
                        <option value="<?= (int)$s['id'] ?>"><?= htmlspecialchars($s['child_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('album-share-modal')">Annuler</button>
            <button class="btn btn-primary" onclick="albumSaveShare()">Enregistrer</button>
        </div>
    </div>
</div>

<!-- Public link modal (admin only) -->
<div class="modal-overlay" id="album-public-link-modal" style="display:none">
    <div class="modal">
        <div class="modal-header">
            <h3>🔗 Lien public</h3>
            <button onclick="closeModal('album-public-link-modal')">✕</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="album-public-link-album-id">
            <p style="color:var(--text-muted);font-size:.85rem">Toute personne disposant de ce lien peut voir les photos de l'album, sans avoir de compte FamilyBoard. Vous pouvez l'autoriser à ajouter ses propres photos.</p>

            <div id="album-public-link-inactive">
                <div class="form-group album-public-link-checkbox">
                    <label><input type="checkbox" id="album-public-link-allow-upload-new"> Autoriser l'ajout de photos par le destinataire</label>
                </div>
                <button class="btn btn-primary btn-sm" onclick="albumGeneratePublicLink()">Générer un lien public</button>
            </div>

            <div id="album-public-link-active" style="display:none">
                <div class="form-group">
                    <label>Lien à partager</label>
                    <div style="display:flex;gap:.5rem">
                        <input type="text" id="album-public-link-url" readonly style="flex:1">
                        <button class="btn btn-secondary btn-sm" onclick="albumCopyPublicLink()">Copier</button>
                    </div>
                </div>
                <div class="form-group album-public-link-checkbox">
                    <label><input type="checkbox" id="album-public-link-allow-upload" onchange="albumToggleAllowUpload()"> Autoriser l'ajout de photos par le destinataire</label>
                </div>
                <button class="btn btn-danger btn-sm" onclick="albumRevokePublicLink()">Révoquer le lien</button>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('album-public-link-modal')">Fermer</button>
        </div>
    </div>
</div>

<!-- Lightbox -->
<div class="modal-overlay" id="album-lightbox" style="display:none" onclick="closeModal('album-lightbox')">
    <img id="album-lightbox-img" src="" alt="" style="max-width:92vw;max-height:92vh;border-radius:8px">
</div>

<style>
.album-header { margin-bottom: 1rem; }
.album-header-row { display:flex; justify-content:space-between; align-items:flex-start; gap:1rem; flex-wrap:wrap; margin-top:.5rem; }
.album-header-actions { display:flex; gap:.5rem; flex-wrap:wrap; }
.album-upload-card { padding:1rem; margin-bottom:1rem; }
.album-meta { font-size:.8rem; color:var(--text-muted); }
.album-photo-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(160px,1fr)); gap:.75rem; }
.album-photo { position:relative; border-radius:10px; overflow:hidden; background:var(--card-bg); border:1px solid var(--border); }
.album-photo img { width:100%; aspect-ratio:1; object-fit:cover; cursor:pointer; display:block; }
.album-photo-meta { display:flex; justify-content:space-between; align-items:center; padding:.35rem .5rem; font-size:.72rem; }
.album-photo-delete { background:none; border:none; color:var(--text-muted); cursor:pointer; font-size:.85rem; }
.album-photo-delete:hover { color:#E74C3C; }
#album-lightbox { background:rgba(0,0,0,.85); display:flex; align-items:center; justify-content:center; }
</style>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
