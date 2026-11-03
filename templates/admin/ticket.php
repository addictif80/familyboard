<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket #<?= $ticket['id'] ?> — Admin</title>
    <script>
    (function () {
        try {
            var stored = localStorage.getItem('fb-theme');
            var theme = stored || (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            document.documentElement.setAttribute('data-theme', theme);
        } catch (e) {}
    })();
    </script>
    <link rel="icon" href="<?= BASE_URL ?>/public/icons/icon.svg" type="image/svg+xml">
    <link rel="icon" href="<?= BASE_URL ?>/public/icons/icon-192.png" type="image/png">
    <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/app.css?v=<?= APP_VERSION ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&display=swap">
</head>
<body class="admin-body">
<div class="admin-layout">
    <nav class="admin-sidebar">
        <div class="admin-sidebar-header"><span>🔐 Admin</span></div>
        <ul class="admin-nav">
            <li><a href="<?= BASE_URL ?>/admin?tab=tickets" style="font-weight:600">← Tickets</a></li>
        </ul>
        <div class="admin-sidebar-footer">
            <button class="theme-toggle-btn" onclick="toggleTheme()" title="Changer de thème"><span class="theme-icon-sun">☀️</span><span class="theme-icon-moon">🌙</span></button>
            <a href="<?= BASE_URL ?>/admin/logout" class="btn btn-danger btn-sm" style="margin-left:auto">Déconnexion</a>
        </div>
    </nav>
    <main class="admin-content">
        <div style="display:flex;align-items:center;gap:1rem;margin-bottom:1rem;flex-wrap:wrap">
            <h2 style="margin:0">Ticket #<?= $ticket['id'] ?> — <?= htmlspecialchars($ticket['subject']) ?></h2>
            <span class="badge badge-<?= $ticket['status'] === 'closed' ? 'ok' : 'warn' ?>">
                <?= match($ticket['status']) { 'open'=>'🆕 Ouvert','in_progress'=>'💬 En cours','closed'=>'✅ Fermé',default=>$ticket['status'] } ?>
            </span>
            <?php if (($ticket['source'] ?? 'user') === 'auto'): ?>
                <span class="badge badge-warn" title="Créé automatiquement suite à une erreur technique">🤖 Signalement automatique</span>
            <?php endif; ?>
            <?php if ($ticket['status'] !== 'closed'): ?>
            <form method="POST" action="<?= BASE_URL ?>/admin/tickets/<?= $ticket['id'] ?>/close" style="margin-left:auto"><?= \App\Core\Csrf::field() ?>
                <button class="btn btn-secondary btn-sm">✅ Fermer le ticket</button>
            </form>
            <?php else: ?>
            <form method="POST" action="<?= BASE_URL ?>/admin/tickets/<?= $ticket['id'] ?>/reopen" style="margin-left:auto"><?= \App\Core\Csrf::field() ?>
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

        <?php if (($ticket['source'] ?? 'user') === 'auto'): ?>
        <div class="card" style="max-width:900px;margin-bottom:1.5rem">
            <div class="card-header"><h3>🤖 Correction assistée par IA</h3></div>
            <div style="padding:1rem" id="ai-fix-panel">
                <?php if (empty($ticket['error_file'])): ?>
                    <p class="empty-state">Aucun fichier identifié pour cette erreur — correction automatique impossible.</p>

                <?php elseif ($ticket['ai_fix_status'] === 'none'): ?>
                    <p style="color:var(--text-muted);font-size:.85rem">
                        Fichier en cause : <code><?= htmlspecialchars($ticket['error_file']) ?></code><?= $ticket['error_line'] ? ' (ligne ' . (int)$ticket['error_line'] . ')' : '' ?>
                    </p>
                    <button class="btn btn-primary btn-sm" onclick="aiAnalyzeTicket(<?= $ticket['id'] ?>)" id="ai-analyze-btn">🤖 Analyser avec l'IA</button>

                <?php elseif ($ticket['ai_fix_status'] === 'error'): ?>
                    <p style="color:var(--danger)">❌ <?= htmlspecialchars($ticket['ai_fix_error'] ?? 'Erreur inconnue.') ?></p>
                    <button class="btn btn-secondary btn-sm" onclick="aiAnalyzeTicket(<?= $ticket['id'] ?>)">Réessayer</button>

                <?php elseif ($ticket['ai_fix_status'] === 'proposed'): ?>
                    <div style="background:color-mix(in srgb, var(--accent) 10%, transparent);border-left:3px solid var(--accent);border-radius:6px;padding:.75rem 1rem;margin-bottom:1rem">
                        <strong>Diagnostic de l'IA :</strong>
                        <p style="margin:.3rem 0 0"><?= nl2br(htmlspecialchars($ticket['ai_fix_diagnosis'])) ?></p>
                    </div>
                    <p style="font-size:.85rem;color:var(--text-muted)">Fichier : <code><?= htmlspecialchars($ticket['error_file']) ?></code></p>
                    <?= \App\Core\TextDiff::renderHtml($ticket['ai_fix_original_content'], $ticket['ai_fix_proposed_content']) ?>
                    <div style="display:flex;gap:.5rem;margin-top:1rem">
                        <button class="btn btn-primary btn-sm" onclick="aiPublishFix(<?= $ticket['id'] ?>)">✅ Valider et publier sur GitHub</button>
                        <button class="btn btn-secondary btn-sm" onclick="aiRejectFix(<?= $ticket['id'] ?>)">❌ Rejeter</button>
                    </div>
                    <p style="font-size:.78rem;color:var(--text-muted);margin-top:.5rem">
                        La validation ouvre une Pull Request sur GitHub — aucun fichier n'est modifié directement sur ce serveur. Fusionner la Pull Request reste une action séparée, sur GitHub.
                    </p>

                <?php elseif ($ticket['ai_fix_status'] === 'pr_open'): ?>
                    <p style="color:var(--success)">✅ Correctif publié : <a href="<?= htmlspecialchars($ticket['ai_fix_pr_url']) ?>" target="_blank" rel="noopener"><?= htmlspecialchars($ticket['ai_fix_pr_url']) ?></a></p>
                    <p style="font-size:.85rem;color:var(--text-muted)">Fusionner la Pull Request se fait sur GitHub.</p>
                <?php endif; ?>

                <div id="ai-fix-error" style="color:var(--danger);margin-top:.5rem"></div>
            </div>
        </div>
        <?php endif; ?>

        <div class="card" style="max-width:700px">
            <div class="card-header"><h3>Répondre</h3></div>
            <form method="POST" action="<?= BASE_URL ?>/admin/tickets/<?= $ticket['id'] ?>/reply" style="padding:1rem"><?= \App\Core\Csrf::field() ?>
                <div class="form-group">
                    <textarea name="message" rows="4" placeholder="Votre réponse…" required style="width:100%"></textarea>
                </div>
                <button class="btn btn-primary">Envoyer la réponse</button>
            </form>
        </div>
    </main>
</div>
<script>
const AI_FIX_BASE_URL = <?= json_encode(BASE_URL) ?>;

async function aiFixCall(path) {
    const res = await fetch(`${AI_FIX_BASE_URL}${path}`, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' } });
    return res.json();
}

async function aiAnalyzeTicket(id) {
    const btn = document.getElementById('ai-analyze-btn');
    const errBox = document.getElementById('ai-fix-error');
    errBox.textContent = '';
    if (btn) { btn.disabled = true; btn.textContent = '⏳ Analyse en cours…'; }
    const r = await aiFixCall(`/admin/tickets/${id}/ai-analyze`);
    if (r.success) { window.location.reload(); return; }
    errBox.textContent = r.error || 'Erreur lors de l\'analyse.';
    if (btn) { btn.disabled = false; btn.textContent = "🤖 Analyser avec l'IA"; }
}

async function aiPublishFix(id) {
    const errBox = document.getElementById('ai-fix-error');
    errBox.textContent = '';
    const r = await aiFixCall(`/admin/tickets/${id}/ai-publish`);
    if (r.success) { window.location.reload(); return; }
    errBox.textContent = r.error || 'Erreur lors de la publication.';
}

async function aiRejectFix(id) {
    const r = await aiFixCall(`/admin/tickets/${id}/ai-reject`);
    if (r.success) window.location.reload();
}
</script>
</body>
</html>
