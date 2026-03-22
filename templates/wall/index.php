<?php
$pageTitle = 'Mur familial';
$extraJs = ['wall.js'];
ob_start();
?>
<div class="wall-container">
    <!-- Post form -->
    <div class="card post-form-card">
        <form method="POST" action="<?= BASE_URL ?>/wall" enctype="multipart/form-data" id="post-form">
            <div class="post-input-row">
                <div class="user-avatar" style="background:<?= htmlspecialchars($user['color']) ?>">
                    <?php if ($user['avatar']): ?>
                        <img src="<?= BASE_URL . htmlspecialchars($user['avatar']) ?>" alt="">
                    <?php else: ?>
                        <?= mb_substr($user['name'], 0, 1) ?>
                    <?php endif; ?>
                </div>
                <textarea name="content" placeholder="Partagez quelque chose avec votre famille…" rows="2" id="post-content"></textarea>
            </div>
            <div class="post-image-preview" id="image-preview" style="display:none">
                <img id="preview-img" src="" alt="">
                <button type="button" onclick="clearImage()">✕</button>
            </div>
            <div class="post-actions">
                <label class="btn btn-secondary" for="post-image">
                    📷 Photo
                    <input type="file" id="post-image" name="image" accept="image/*" style="display:none" onchange="previewImage(this)">
                </label>
                <button type="submit" class="btn btn-primary">Publier</button>
            </div>
        </form>
    </div>

    <!-- Posts feed -->
    <div id="posts-feed">
        <?php foreach ($posts as $post): ?>
            <?php include __DIR__ . '/post.php'; ?>
        <?php endforeach; ?>
        <?php if (empty($posts)): ?>
            <div class="empty-state-card">
                <p>🌟 Soyez le premier à publier quelque chose !</p>
            </div>
        <?php endif; ?>
    </div>

    <div class="load-more-container" id="load-more-container">
        <button class="btn btn-secondary" onclick="loadMorePosts()" id="load-more-btn">Charger plus</button>
    </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
