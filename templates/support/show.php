<?php
$pageTitle = 'Ticket #' . $ticket['id'];
ob_start();
?>
<div class="support-page">
    <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:.5rem;flex-wrap:wrap">
        <a href="<?= BASE_URL ?>/support" class="btn btn-secondary btn-sm">← Tickets</a>
        <h2 style="margin:0;flex:1"><?= htmlspecialchars($ticket['subject']) ?></h2>
        <span class="badge badge-<?= $ticket['status'] === 'closed' ? 'ok' : ($ticket['status'] === 'in_progress' ? 'warn' : 'new') ?>">
            <?= match($ticket['status']) { 'open'=>'🆕 Ouvert','in_progress'=>'💬 En cours','closed'=>'✅ Fermé',default=>$ticket['status'] } ?>
        </span>
    </div>
    <p style="color:var(--text-muted);font-size:.82rem;margin-bottom:1.5rem">
        Ouvert le <?= \App\Core\DateHelper::fromUtc($ticket['created_at'], 'd/m/Y \\à H:i') ?>
    </p>

    <div class="support-thread">
        <?php foreach ($messages as $msg): ?>
        <div class="support-msg <?= $msg['is_admin'] ? 'msg-admin' : 'msg-user' ?>">
            <div class="msg-meta">
                <?php if ($msg['is_admin']): ?>
                    <span class="msg-author admin-author">🔐 Administrateur</span>
                <?php else: ?>
                    <span class="msg-author" style="color:<?= htmlspecialchars($msg['user_color'] ?? '#4A90D9') ?>"><?= htmlspecialchars($msg['user_name'] ?? 'Vous') ?></span>
                <?php endif; ?>
                <span class="msg-time"><?= \App\Core\DateHelper::fromUtc($msg['created_at'], 'd/m/Y H:i') ?></span>
            </div>
            <div class="msg-body"><?= nl2br(htmlspecialchars($msg['message'])) ?></div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php if ($ticket['status'] !== 'closed'): ?>
    <div class="card" style="margin-top:1.5rem;max-width:700px">
        <div class="card-header"><h3>Répondre</h3></div>
        <form method="POST" action="<?= BASE_URL ?>/support/<?= $ticket['id'] ?>/reply" style="padding:1rem"><?= \App\Core\Csrf::field() ?>
            <div class="form-group">
                <textarea name="message" rows="4" placeholder="Votre message…" required style="width:100%"></textarea>
            </div>
            <button class="btn btn-primary">Envoyer</button>
        </form>
    </div>
    <?php else: ?>
    <p style="text-align:center;color:var(--text-muted);margin-top:1.5rem">Ce ticket est fermé. <a href="<?= BASE_URL ?>/support">Ouvrir un nouveau ticket</a>.</p>
    <?php endif; ?>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
