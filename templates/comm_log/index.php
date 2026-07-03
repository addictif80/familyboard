<?php
$pageTitle = 'Journal parental';
$extraJs = ['comm_log.js'];
ob_start();
?>
<div class="chat-container">
    <p style="color:var(--text-muted);font-size:.8rem;padding:.5rem 1rem 0">
        📝 Ces messages sont horodatés et ne peuvent jamais être modifiés ni supprimés — utile comme trace
        de communication entre parents.
    </p>
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
                    <div class="message-text"><?= nl2br(htmlspecialchars($msg['content'])) ?></div>
                    <div class="message-time">
                        <?= \App\Core\DateHelper::fromUtc($msg['created_at'], 'd/m/Y H:i') ?>
                        <?php if (!empty($reads[$msg['id']])): ?>
                            · lu par <?= htmlspecialchars(implode(', ', array_column($reads[$msg['id']], 'user_name'))) ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="chat-input-bar">
        <input type="text" id="chat-input" placeholder="Votre message… (définitif, non modifiable)" maxlength="4000"
               onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();sendCommLogMessage();}">
        <button onclick="sendCommLogMessage()" class="btn btn-primary">➤</button>
    </div>
</div>

<script>
const LAST_MSG_ID = <?= json_encode($lastId) ?>;
const CURRENT_USER_ID = <?= json_encode($user['id']) ?>;
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
