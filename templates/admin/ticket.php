<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket #<?= $ticket['id'] ?> — Admin</title>
    <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/app.css?v=<?= APP_VERSION ?>">
</head>
<body class="admin-body">
<div class="admin-layout">
    <nav class="admin-sidebar">
        <div class="admin-sidebar-header"><span>🔐 Admin</span></div>
        <ul class="admin-nav">
            <li><a href="<?= BASE_URL ?>/admin?tab=tickets" style="font-weight:600">← Tickets</a></li>
        </ul>
        <div class="admin-sidebar-footer">
            <a href="<?= BASE_URL ?>/admin/logout" class="btn btn-danger btn-sm" style="margin-left:auto">Déconnexion</a>
        </div>
    </nav>
    <main class="admin-content">
        <div style="display:flex;align-items:center;gap:1rem;margin-bottom:1rem;flex-wrap:wrap">
            <h2 style="margin:0">Ticket #<?= $ticket['id'] ?> — <?= htmlspecialchars($ticket['subject']) ?></h2>
            <span class="badge badge-<?= $ticket['status'] === 'closed' ? 'ok' : 'warn' ?>">
                <?= match($ticket['status']) { 'open'=>'🆕 Ouvert','in_progress'=>'💬 En cours','closed'=>'✅ Fermé',default=>$ticket['status'] } ?>
            </span>
            <?php if ($ticket['status'] !== 'closed'): ?>
            <form method="POST" action="<?= BASE_URL ?>/admin/tickets/<?= $ticket['id'] ?>/close" style="margin-left:auto">
                <button class="btn btn-secondary btn-sm">✅ Fermer le ticket</button>
            </form>
            <?php else: ?>
            <form method="POST" action="<?= BASE_URL ?>/admin/tickets/<?= $ticket['id'] ?>/reopen" style="margin-left:auto">
                <button class="btn btn-secondary btn-sm">🔄 Rouvrir</button>
            </form>
            <?php endif; ?>
        </div>
        <p style="color:var(--text-muted);font-size:.85rem">
            Famille : <strong><?= htmlspecialchars($ticket['family_name']) ?></strong> ·
            Utilisateur : <strong><?= htmlspecialchars($ticket['user_name']) ?></strong> ·
            Créé le <?= \App\Core\DateHelper::fromUtc($ticket['created_at'], 'd/m/Y \\à H:i') ?>
        </p>

        <div class="support-thread" style="margin:1.5rem 0">
            <?php foreach ($messages as $msg): ?>
            <div class="support-msg <?= $msg['is_admin'] ? 'msg-admin' : 'msg-user' ?>">
                <div class="msg-meta">
                    <?php if ($msg['is_admin']): ?>
                        <span class="msg-author">🔐 Administrateur</span>
                    <?php else: ?>
                        <span class="msg-author" style="color:<?= htmlspecialchars($msg['user_color'] ?? '#4A90D9') ?>"><?= htmlspecialchars($msg['user_name'] ?? 'Utilisateur') ?></span>
                    <?php endif; ?>
                    <span class="msg-time"><?= \App\Core\DateHelper::fromUtc($msg['created_at'], 'd/m/Y H:i') ?></span>
                </div>
                <div class="msg-body"><?= nl2br(htmlspecialchars($msg['message'])) ?></div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="card" style="max-width:700px">
            <div class="card-header"><h3>Répondre</h3></div>
            <form method="POST" action="<?= BASE_URL ?>/admin/tickets/<?= $ticket['id'] ?>/reply" style="padding:1rem">
                <div class="form-group">
                    <textarea name="message" rows="4" placeholder="Votre réponse…" required style="width:100%"></textarea>
                </div>
                <button class="btn btn-primary">Envoyer la réponse</button>
            </form>
        </div>
    </main>
</div>
</body>
</html>
