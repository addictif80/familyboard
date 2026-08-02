<?php
$pageTitle = 'Conversation avec ' . $other['name'];
$extraJs = ['messages.js'];
ob_start();
?>
<div class="wall-container">
    <div class="content-header">
        <h2><?= \App\Core\Avatar::html($other['avatar'], $other['color'], $other['name'], 'user-avatar-sm') ?> <?= htmlspecialchars($other['name']) ?></h2>
        <a href="<?= BASE_URL ?>/messages" class="btn btn-secondary btn-sm">← Toutes les conversations</a>
    </div>

    <div class="chat-container">
        <div class="chat-messages" id="dm-messages">
            <?php foreach ($messages as $msg): ?>
                <?php $isOwn = (int)$msg['sender_id'] === (int)$user['id']; ?>
                <div class="message-row <?= $isOwn ? 'own' : '' ?>" data-id="<?= $msg['id'] ?>">
                    <?php if (!$isOwn): ?>
                        <?= \App\Core\Avatar::html($msg['sender_avatar'], $msg['sender_color'], $msg['sender_name'], 'user-avatar-sm') ?>
                    <?php endif; ?>
                    <div class="message-bubble <?= $isOwn ? 'bubble-own' : 'bubble-other' ?>">
                        <div class="message-text"><?= nl2br(htmlspecialchars($msg['content'])) ?></div>
                        <div class="message-time"><?= \App\Core\DateHelper::fromUtc($msg['created_at'], 'H:i') ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="chat-input-bar">
            <input type="text" id="dm-input" placeholder="Votre message…" maxlength="2000"
                   onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();sendDirectMessage();}">
            <button onclick="sendDirectMessage()" class="btn btn-primary">➤</button>
        </div>
    </div>
</div>

<script>
const DM_OTHER_ID = <?= json_encode((int)$other['id']) ?>;
const DM_LAST_ID = <?= json_encode(!empty($messages) ? (int)end($messages)['id'] : 0) ?>;
const CURRENT_USER_ID = <?= json_encode((int)$user['id']) ?>;
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
