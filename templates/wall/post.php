<div class="post-card" id="post-<?= $post['id'] ?>" data-post-id="<?= $post['id'] ?>">
    <div class="post-header">
        <div class="user-avatar" style="background:<?= htmlspecialchars($post['user_color']) ?>">
            <?php if ($post['user_avatar']): ?>
                <img src="<?= BASE_URL . htmlspecialchars($post['user_avatar']) ?>" alt="">
            <?php else: ?>
                <?= mb_substr($post['user_name'], 0, 1) ?>
            <?php endif; ?>
        </div>
        <div class="post-meta">
            <strong><?= htmlspecialchars($post['user_name']) ?></strong>
            <span class="post-date"><?= \App\Core\DateHelper::fromUtc($post['created_at'], 'd/m/Y \à H:i') ?></span>
        </div>
        <?php if ($post['user_id'] === $user['id'] || $user['role'] === 'admin'): ?>
            <div class="post-actions-btns">
                <button class="btn-icon-sm" title="Modifier"
                    onclick="openEditPost(<?= htmlspecialchars(json_encode(['id' => $post['id'], 'content' => $post['content']])) ?>)">✏️</button>
                <form method="POST" action="<?= BASE_URL ?>/wall/<?= $post['id'] ?>/delete" class="post-delete" onsubmit="return confirmSubmit(this,'Supprimer ce post ?')"><?= \App\Core\Csrf::field() ?>
                    <button type="submit" class="btn-icon-sm">🗑</button>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($post['content']): ?>
        <div class="post-content ql-content">
            <?php if (str_starts_with(ltrim($post['content']), '<')): ?>
                <?= $post['content'] ?>
            <?php else: ?>
                <?= nl2br(htmlspecialchars($post['content'])) ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($post['image_path']): ?>
        <div class="post-image">
            <img src="<?= BASE_URL . htmlspecialchars($post['image_path']) ?>" alt="Photo" loading="lazy" onclick="openLightbox(this.src)">
        </div>
    <?php endif; ?>

    <div class="post-footer">
        <button class="reaction-btn <?= $post['user_reaction'] ? 'reacted' : '' ?>"
                onclick="toggleReaction(<?= $post['id'] ?>, this)"
                data-count="<?= $post['reaction_count'] ?>">
            ❤️ <span class="reaction-count"><?= $post['reaction_count'] ?></span>
        </button>
        <button class="comment-toggle-btn" onclick="toggleComments(<?= $post['id'] ?>)">
            💬 <?= $post['comment_count'] ?> commentaire<?= $post['comment_count'] != 1 ? 's' : '' ?>
        </button>
    </div>

    <div class="comments-section" id="comments-<?= $post['id'] ?>" style="display:none">
        <div class="comments-list" id="comment-list-<?= $post['id'] ?>">
            <?php foreach ($post['comments'] as $comment): ?>
                <div class="comment-item">
                    <div class="user-avatar-sm" style="background:<?= htmlspecialchars($comment['user_color']) ?>">
                        <?= mb_substr($comment['user_name'], 0, 1) ?>
                    </div>
                    <div class="comment-body">
                        <strong><?= htmlspecialchars($comment['user_name']) ?></strong>
                        <p><?= nl2br(htmlspecialchars($comment['content'])) ?></p>
                        <small><?= \App\Core\DateHelper::fromUtc($comment['created_at'], 'd/m \à H:i') ?></small>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="comment-form">
            <input type="text" placeholder="Ajouter un commentaire…" class="comment-input"
                   id="comment-input-<?= $post['id'] ?>"
                   onkeydown="if(event.key==='Enter')addComment(<?= $post['id'] ?>)">
            <button onclick="addComment(<?= $post['id'] ?>)" class="btn btn-sm btn-primary">Envoyer</button>
        </div>
    </div>
</div>
