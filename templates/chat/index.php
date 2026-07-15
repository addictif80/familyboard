<?php
$pageTitle = 'Chat familial';
$extraJs = ['chat.js'];
ob_start();
?>
<div class="chat-container">
    <div class="chat-messages" id="chat-messages">
        <?php foreach ($messages as $msg): ?>
            <?php $isOwn = $msg['user_id'] === $user['id']; ?>
            <div class="message-row <?= $isOwn ? 'own' : '' ?>" data-id="<?= $msg['id'] ?>">
                <?php if (!$isOwn): ?>
                    <div class="user-avatar-sm" style="background:<?= htmlspecialchars($msg['user_color']) ?>" title="<?= htmlspecialchars($msg['user_name']) ?>">
                        <?php if ($msg['user_avatar']): ?>
                            <img src="<?= BASE_URL . htmlspecialchars($msg['user_avatar']) ?>" alt="">
                        <?php else: ?>
                            <?= mb_substr($msg['user_name'], 0, 1) ?>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <div class="message-bubble <?= $isOwn ? 'bubble-own' : 'bubble-other' ?>">
                    <?php if (!$isOwn): ?>
                        <div class="message-author" style="color:<?= htmlspecialchars($msg['user_color']) ?>"><?= htmlspecialchars($msg['user_name']) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($msg['audio_path'])): ?>
                        <div class="voice-msg">🎤<audio controls preload="none" src="<?= BASE_URL ?>/api/chat/<?= $msg['id'] ?>/audio"></audio><?php if ($msg['audio_duration']): ?><span class="voice-duration"><?= sprintf('%02d:%02d', intdiv($msg['audio_duration'], 60), $msg['audio_duration'] % 60) ?></span><?php endif; ?></div>
                    <?php endif; ?>
                    <?php if ($msg['content'] !== ''): ?>
                        <div class="message-text"><?= nl2br(htmlspecialchars($msg['content'])) ?></div>
                    <?php endif; ?>
                    <div class="message-time"><?= \App\Core\DateHelper::fromUtc($msg['created_at'], 'H:i') ?></div>
                </div>
                <?php if ($isOwn || $user['role'] === 'admin'): ?>
                    <button class="msg-delete-btn" onclick="deleteMessage(<?= $msg['id'] ?>)" title="Supprimer">✕</button>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="chat-input-bar">
        <input type="text" id="chat-input" placeholder="Votre message…" maxlength="2000"
               onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();sendMessage();}">
        <span class="voice-record-timer" id="chat-voice-timer"></span>
        <button type="button" class="voice-record-btn" id="chat-voice-btn" title="Message vocal">🎤</button>
        <button onclick="sendMessage()" class="btn btn-primary">➤</button>
    </div>
</div>

<script>
const LAST_MSG_ID = <?= json_encode($lastId) ?>;
const BASE_URL = <?= json_encode(BASE_URL) ?>;
const CURRENT_USER_ID = <?= json_encode($user['id']) ?>;
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
