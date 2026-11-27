<?php
$pageTitle = 'Historique — Assistant IA';
ob_start();
?>
<div class="tasks-header">
    <h2>🤖 Historique de l'assistant</h2>
</div>
<p style="color:var(--text-muted);font-size:.85rem;margin-top:-.5rem">
    Pour discuter avec l'assistant, utilisez la bulle 🤖 dans le menu (ou 🎤 dans la barre du bas
    sur mobile) — cette page est une vue en lecture seule de l'historique complet.
</p>

<div class="card settings-section">
    <h3 style="margin-top:0">Conversation</h3>
    <div id="ai-history-messages" style="display:flex;flex-direction:column;gap:.5rem;max-height:500px;overflow-y:auto">
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
            <p class="empty-state">Aucun message pour le moment.</p>
        <?php endif; ?>
    </div>
</div>

<div class="card settings-section" style="margin-top:1rem">
    <h3 style="margin-top:0">📋 Actions effectuées</h3>
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
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
