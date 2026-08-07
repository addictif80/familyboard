<?php
$pageTitle = 'Mur familial';
$extraCss = ['quill.snow.css'];
$extraJs  = ['quill.min.js', 'wall.js'];
ob_start();
?>
<div class="wall-container">
    <div class="wall-toolbar">
        <div class="wall-tabs">
            <button class="wall-tab active" id="wall-tab-feed" onclick="switchWallTab('feed')">📰 Fil d'actualité</button>
            <button class="wall-tab" id="wall-tab-photos" onclick="switchWallTab('photos')">📷 Photos</button>
        </div>
        <button class="btn btn-secondary" onclick="openModal('members-modal')">
            👥 Membres
            <?php if (!empty($pendingFollowRequests)): ?><span class="badge"><?= count($pendingFollowRequests) ?></span><?php endif; ?>
        </button>
        <a href="<?= BASE_URL ?>/messages" class="btn btn-secondary">✉️ Messages privés</a>
    </div>

    <?php if ($user['role'] === 'admin' && !empty($pendingPosts)): ?>
    <div class="card post-form-card" style="border-left:4px solid var(--warning)">
        <h3 style="margin:0 0 .75rem">🕓 Publications "famille" en attente (<?= count($pendingPosts) ?>)</h3>
        <?php foreach ($pendingPosts as $pp): ?>
        <div class="post-card" style="margin-bottom:.75rem">
            <div class="post-header">
                <?= \App\Core\Avatar::html($pp['user_avatar'], $pp['user_color'], $pp['user_name']) ?>
                <div class="post-meta">
                    <strong><?= htmlspecialchars($pp['user_name']) ?></strong>
                    <span class="post-date"><?= \App\Core\DateHelper::fromUtc($pp['created_at'], 'd/m/Y \à H:i') ?></span>
                </div>
            </div>
            <?php if ($pp['content']): ?><div class="post-content ql-content"><?= $pp['content'] ?></div><?php endif; ?>
            <?php if ($pp['image_path']): ?><div class="post-image"><img src="<?= BASE_URL . htmlspecialchars($pp['image_path']) ?>" alt=""></div><?php endif; ?>
            <div style="display:flex;gap:.5rem;margin-top:.5rem">
                <form method="POST" action="<?= BASE_URL ?>/wall/<?= (int)$pp['id'] ?>/approve"><?= \App\Core\Csrf::field() ?>
                    <button type="submit" class="btn btn-primary btn-sm">✓ Publier</button>
                </form>
                <form method="POST" action="<?= BASE_URL ?>/wall/<?= (int)$pp['id'] ?>/reject"><?= \App\Core\Csrf::field() ?>
                    <button type="submit" class="btn btn-danger btn-sm">✕ Refuser</button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Fil d'actualité -->
    <div id="wall-panel-feed">
        <!-- Post form -->
        <div class="card post-form-card">
            <form method="POST" action="<?= BASE_URL ?>/wall" enctype="multipart/form-data" id="post-form"><?= \App\Core\Csrf::field() ?>
                <div class="post-input-row">
                    <div class="user-avatar" style="background:<?= htmlspecialchars($user['color']) ?>">
                        <?php if ($user['avatar']): ?>
                            <img src="<?= BASE_URL . htmlspecialchars($user['avatar']) ?>" alt="">
                        <?php else: ?>
                            <?= mb_substr($user['name'], 0, 1) ?>
                        <?php endif; ?>
                    </div>
                    <div class="post-quill-wrap">
                        <div id="post-quill-editor"></div>
                        <input type="hidden" name="content" id="post-content-hidden">
                    </div>
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
                    <button type="button" class="btn btn-secondary" onclick="openAlbumPicker()">🖼️ Depuis un album</button>
                    <select name="post_type" id="post-type-select" class="post-type-select" title="Publier en tant que">
                        <option value="personal">🙋 Amis (mes abonnés)</option>
                        <option value="family">🏠 Ma famille<?= $user['role'] === 'admin' ? '' : ' (soumis à validation)' ?></option>
                        <option value="network">🤝 Familles amies</option>
                    </select>
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
                    <p>🌟 Soyez le premier à publier quelque chose ! Abonnez-vous à d'autres membres (bouton « Membres ») pour voir leurs publications personnelles.</p>
                </div>
            <?php endif; ?>
        </div>

        <div class="load-more-container" id="load-more-container">
            <button class="btn btn-secondary" onclick="loadMorePosts()" id="load-more-btn">Charger plus</button>
        </div>
    </div>

    <!-- Photos -->
    <div id="wall-panel-photos" style="display:none">
        <?php if (empty($wallAlbums)): ?>
            <div class="empty-state-card">
                <p>📷 Aucun album affiché sur le mur pour l'instant. Depuis un album (page « Albums »), choisissez une portée dans le menu « Pas sur le mur » pour qu'il apparaisse ici.</p>
            </div>
        <?php endif; ?>
        <?php foreach ($wallAlbums as $wa): ?>
            <?php if (empty($wa['photos'])) continue; ?>
            <div class="wall-album-section">
                <h3 class="wall-album-title">
                    <a href="<?= BASE_URL ?>/albums/<?= (int)$wa['id'] ?>"><?= htmlspecialchars($wa['title']) ?></a>
                    <span class="wall-album-owner">par <?= htmlspecialchars($wa['user_name']) ?></span>
                </h3>
                <div class="album-photo-grid">
                    <?php foreach ($wa['photos'] as $photo): ?>
                        <div class="album-photo">
                            <img src="<?= BASE_URL . htmlspecialchars($photo['image_path']) ?>" alt="" loading="lazy" onclick="openLightbox(this.src)">
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Album photo picker (import depuis un album pour le formulaire de publication) -->
<div class="modal-overlay" id="wall-album-picker-modal" style="display:none">
    <div class="modal">
        <div class="modal-header">
            <h3>🖼️ Choisir une photo</h3>
            <button onclick="closeModal('wall-album-picker-modal')">✕</button>
        </div>
        <div class="modal-body">
            <div class="album-photo-grid" id="wall-album-picker-grid">
                <p style="color:var(--text-muted);font-size:.85rem">Chargement…</p>
            </div>
        </div>
    </div>
</div>

<!-- Edit post modal -->
<div class="modal-overlay" id="edit-post-modal" style="display:none">
    <div class="modal">
        <div class="modal-header">
            <h3>✏️ Modifier la publication</h3>
            <button onclick="closeModal('edit-post-modal')">✕</button>
        </div>
        <div class="modal-body" style="padding-bottom:.5rem">
            <input type="hidden" id="edit-post-id">
            <div id="edit-quill-editor"></div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('edit-post-modal')">Annuler</button>
            <button class="btn btn-primary" id="edit-save-btn" onclick="saveEditPost()">Enregistrer</button>
        </div>
    </div>
</div>

<!-- Members / follow management modal -->
<div class="modal-overlay" id="members-modal" style="display:none">
    <div class="modal" style="max-width:520px">
        <div class="modal-header">
            <h3>👥 Membres de la famille</h3>
            <button onclick="closeModal('members-modal')">✕</button>
        </div>
        <div class="modal-body">
            <?php if (!empty($pendingFollowRequests)): ?>
            <h4 style="margin:0 0 .5rem">Demandes reçues</h4>
            <?php foreach ($pendingFollowRequests as $req): ?>
            <div class="member-row">
                <?= \App\Core\Avatar::html($req['avatar'], $req['color'], $req['name'], 'user-avatar-sm') ?>
                <span class="member-row-name">
                    <?= htmlspecialchars($req['name']) ?>
                    <?php if ((int)$req['family_id'] !== (int)$user['family_id']): ?><small style="color:var(--text-muted)">(<?= htmlspecialchars($req['family_name']) ?>)</small><?php endif; ?>
                </span>
                <button class="btn btn-primary btn-sm" onclick="acceptFollow(<?= (int)$req['follower_id'] ?>)">Accepter</button>
                <button class="btn btn-secondary btn-sm" onclick="removeFollower(<?= (int)$req['follower_id'] ?>)">Refuser</button>
            </div>
            <?php endforeach; ?>
            <hr class="divider">
            <?php endif; ?>

            <h4 style="margin:0 0 .5rem">Tous les membres</h4>
            <?php foreach ($members as $m): ?>
            <div class="member-row">
                <?= \App\Core\Avatar::html($m['avatar'], $m['color'], $m['name'], 'user-avatar-sm') ?>
                <span class="member-row-name">
                    <?= htmlspecialchars($m['name']) ?>
                    <?php if ($m['follows_me']): ?><small style="color:var(--text-muted)">vous suit</small><?php endif; ?>
                </span>
                <?php if ($m['follow_status'] === 'accepted'): ?>
                    <button class="btn btn-secondary btn-sm" onclick="unfollow(<?= (int)$m['id'] ?>)">Abonné(e) ✓</button>
                <?php elseif ($m['follow_status'] === 'pending'): ?>
                    <button class="btn btn-secondary btn-sm" disabled>Demande envoyée</button>
                <?php else: ?>
                    <button class="btn btn-primary btn-sm" onclick="requestFollow(<?= (int)$m['id'] ?>)">S'abonner</button>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>

            <?php if (!empty($friendFamilyMembers)): ?>
            <hr class="divider">
            <h4 style="margin:0 0 .5rem">Familles amies</h4>
            <p style="color:var(--text-muted);font-size:.78rem;margin:0 0 .5rem">S'abonner à un membre d'une famille amie fait apparaître ses publications « Amis » dans votre fil.</p>
            <?php foreach ($friendFamilyMembers as $group): ?>
                <div class="member-group-label"><?= htmlspecialchars($group['family_name']) ?></div>
                <?php foreach ($group['members'] as $m): ?>
                <div class="member-row">
                    <?= \App\Core\Avatar::html($m['avatar'], $m['color'], $m['name'], 'user-avatar-sm') ?>
                    <span class="member-row-name">
                        <?= htmlspecialchars($m['name']) ?>
                        <?php if ($m['follows_me']): ?><small style="color:var(--text-muted)">vous suit</small><?php endif; ?>
                    </span>
                    <?php if ($m['follow_status'] === 'accepted'): ?>
                        <button class="btn btn-secondary btn-sm" onclick="unfollow(<?= (int)$m['id'] ?>)">Abonné(e) ✓</button>
                    <?php elseif ($m['follow_status'] === 'pending'): ?>
                        <button class="btn btn-secondary btn-sm" disabled>Demande envoyée</button>
                    <?php else: ?>
                        <button class="btn btn-primary btn-sm" onclick="requestFollow(<?= (int)$m['id'] ?>)">S'abonner</button>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
