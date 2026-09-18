<?php
$pageTitle = 'Assistant IA';
$extraJs = ['ai_assistant.js'];
ob_start();
?>
<div class="chat-container">
    <p style="color:var(--text-muted);font-size:.8rem;padding:.5rem 1rem 0">
        🤖 Assistant auto-hébergé (aucune donnée n'est envoyée à un service tiers). Il peut ajouter
        des tâches, des articles à la liste de courses et des événements au calendrier — jamais
        rien d'autre, et jamais sans que vous le lui demandiez explicitement. Conversation visible
        de toute la famille.
    </p>
    <div class="chat-messages" id="chat-messages">
        <?php foreach ($messages as $msg): ?>
            <?php $isAssistant = $msg['role'] === 'assistant'; ?>
            <div class="message-row <?= $isAssistant ? '' : 'own' ?>">
                <?php if ($isAssistant): ?>
                    <div class="user-avatar-sm" style="background:#4A90D9" title="Assistant">🤖</div>
                <?php endif; ?>
                <div class="message-bubble <?= $isAssistant ? 'bubble-other' : 'bubble-own' ?>">
                    <?php if (!$isAssistant): ?>
                        <div class="message-author"><?= htmlspecialchars($msg['user_name'] ?? '?') ?></div>
                    <?php endif; ?>
                    <div class="message-text"><?= nl2br(htmlspecialchars($msg['content'])) ?></div>
                    <div class="message-time"><?= \App\Core\DateHelper::fromUtc($msg['created_at'], 'd/m H:i') ?></div>
                </div>
            </div>
        <?php endforeach; ?>
        <?php if (empty($messages)): ?>
            <p class="empty-state">Posez une question, ou demandez d'ajouter une tâche, un article de courses ou un événement.</p>
        <?php endif; ?>
    </div>

    <div class="chat-input-bar">
        <input type="text" id="chat-input" placeholder="Écrire à l'assistant…" onkeydown="if(event.key==='Enter')sendAssistantMessage()">
        <button class="btn btn-primary" id="chat-send-btn" onclick="sendAssistantMessage()">Envoyer</button>
    </div>
</div>

<div class="card settings-section" style="margin-top:1rem">
    <h3>📋 Actions récentes de l'assistant</h3>
    <table class="admin-table">
        <thead><tr><th>Action</th><th>Demandée par</th><th>Date</th></tr></thead>
        <tbody>
        <?php foreach ($actions as $a): ?>
            <tr>
                <td><?= htmlspecialchars($a['summary']) ?></td>
                <td><?= htmlspecialchars($a['user_name']) ?></td>
                <td><?= \App\Core\DateHelper::fromUtc($a['created_at'], 'd/m/Y H:i') ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($actions)): ?>
            <tr><td colspan="3" class="empty-state">Aucune action effectuée pour le moment.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
const BASE_URL = <?= json_encode(BASE_URL) ?>;
const CURRENT_USER_NAME = <?= json_encode($user['name']) ?>;
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
