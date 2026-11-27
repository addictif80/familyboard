<?php
$pageTitle = 'Messages privés';
ob_start();
?>
<div class="wall-container">
    <div class="content-header">
        <h2>✉️ Messages privés</h2>
    </div>
    <p style="color:var(--text-muted);font-size:.85rem;margin-bottom:1.25rem">
        Réservé aux membres qui se suivent mutuellement sur le mur familial — abonnez-vous
        depuis <a href="<?= BASE_URL ?>/wall">le mur</a> pour pouvoir démarrer une conversation.
    </p>

    <?php if (empty($threads)): ?>
        <div class="empty-state-full">
            <div style="font-size:3rem">✉️</div>
            <p>Aucune conversation pour l'instant.</p>
        </div>
    <?php else: ?>
    <div class="card" style="padding:.5rem 1rem">
        <?php foreach ($threads as $t): ?>
        <a href="<?= BASE_URL ?>/messages/<?= (int)$t['other_id'] ?>" class="member-row" style="text-decoration:none;color:inherit">
            <?= \App\Core\Avatar::html($t['other_avatar'], $t['other_color'], $t['other_name'], 'user-avatar-sm') ?>
            <span class="member-row-name">
                <strong><?= htmlspecialchars($t['other_name']) ?></strong>
                <span style="display:block;color:var(--text-muted);font-size:.8rem;font-weight:400">
                    <?= htmlspecialchars(mb_strimwidth(strip_tags((string)$t['last_content']), 0, 60, '…')) ?>
                </span>
            </span>
            <?php if ((int)$t['unread_count'] > 0): ?><span class="badge"><?= (int)$t['unread_count'] ?></span><?php endif; ?>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
