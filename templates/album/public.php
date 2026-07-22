<?php
$pageTitle = $album['title'] ?? 'Album partagé';
$publicToken = $params['token'] ?? '';
$extraJs = ['album-public.js'];
ob_start();
?>
<div class="card album-public-card">
<?php if (!$album): ?>
    <h2>⛔ Lien invalide ou révoqué</h2>
    <p style="color:var(--text-muted)">Ce lien de partage d'album n'est plus valable. Demandez-en un nouveau à la famille.</p>
<?php else: ?>
    <h2 style="margin-bottom:.25rem">🖼️ <?= htmlspecialchars($album['title']) ?></h2>
    <?php if ($album['description']): ?><p style="color:var(--text-muted);margin:0 0 .5rem"><?= nl2br(htmlspecialchars($album['description'])) ?></p><?php endif; ?>
    <span class="album-meta">Partagé par <?= htmlspecialchars($album['user_name']) ?></span>

    <?php if ($link['allow_upload']): ?>
    <div class="album-public-upload">
        <div class="form-group">
            <label>Votre nom (optionnel)</label>
            <input type="text" id="ap-uploader-name" placeholder="Votre nom" maxlength="100">
        </div>
        <label class="btn btn-primary" for="ap-photo-input">
            📷 Ajouter une photo
            <input type="file" id="ap-photo-input" accept="image/*" style="display:none">
        </label>
    </div>
    <?php endif; ?>

    <div class="album-photo-grid" id="ap-photo-grid" style="margin-top:1rem">
        <?php foreach ($photos as $photo): ?>
            <div class="album-photo">
                <img src="<?= BASE_URL . htmlspecialchars($photo['image_path']) ?>" alt="" loading="lazy" onclick="apViewPhoto(this.src)">
                <div class="album-photo-meta">● <?= htmlspecialchars($photo['user_name'] ?? $photo['uploader_name'] ?? 'Invité') ?></div>
            </div>
        <?php endforeach; ?>
        <?php if (empty($photos)): ?>
            <div class="empty-state-card"><p>📷 Aucune photo pour l'instant.</p></div>
        <?php endif; ?>
    </div>

    <div class="modal-overlay" id="ap-lightbox" style="display:none" onclick="closeModal('ap-lightbox')">
        <img id="ap-lightbox-img" src="" alt="" style="max-width:92vw;max-height:92vh;border-radius:8px">
    </div>
<?php endif; ?>
</div>

<style>
.album-public-card { max-width: 900px; margin: 2rem auto; padding: 1.5rem; }
.album-public-upload { display:flex; align-items:flex-end; gap:1rem; flex-wrap:wrap; margin-top:1rem; padding-top:1rem; border-top:1px solid var(--border); }
.album-photo-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(150px,1fr)); gap:.75rem; }
.album-photo { border-radius:10px; overflow:hidden; background:var(--card-bg); border:1px solid var(--border); }
.album-photo img { width:100%; aspect-ratio:1; object-fit:cover; cursor:pointer; display:block; }
.album-photo-meta { padding:.35rem .5rem; font-size:.72rem; color:var(--text-muted); }
#ap-lightbox { background:rgba(0,0,0,.85); display:flex; align-items:center; justify-content:center; }
.album-meta { font-size:.8rem; color:var(--text-muted); }
</style>

<script>
const AP_TOKEN = <?= json_encode($publicToken) ?>;
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
