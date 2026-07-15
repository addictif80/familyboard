<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administration — FamilyBoard</title>
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
    <!-- Sidebar -->
    <nav class="admin-sidebar">
        <div class="admin-sidebar-header">
            <span>🔐 Admin</span>
        </div>
        <ul class="admin-nav">
            <?php foreach (['dashboard'=>'📊 Tableau de bord','families'=>'🏠 Familles','users'=>'👥 Utilisateurs','impersonation'=>'🕵️ Impersonation','ips'=>'🚫 IPs bloquées','tickets'=>'🎫 Tickets support','smtp'=>'✉️ SMTP'] as $t=>$label): ?>
            <li class="<?= $tab === $t ? 'active' : '' ?>">
                <a href="<?= BASE_URL ?>/admin?tab=<?= $t ?>"><?= $label ?></a>
            </li>
            <?php endforeach; ?>
            <li><a href="<?= BASE_URL ?>/admin/profile">👤 Mon profil</a></li>
        </ul>
        <div class="admin-sidebar-footer">
            <a href="<?= BASE_URL ?>/" style="font-size:.8rem;color:var(--text-muted)">← Site</a>
            <button class="theme-toggle-btn" onclick="toggleTheme()" title="Changer de thème" style="margin-left:.5rem"><span class="theme-icon-sun">☀️</span><span class="theme-icon-moon">🌙</span></button>
            <a href="<?= BASE_URL ?>/admin/logout" class="btn btn-danger btn-sm" style="margin-left:auto">Déconnexion</a>
        </div>
    </nav>

    <!-- Content -->
    <main class="admin-content">
        <?php if ($msg = ($_GET['msg'] ?? '')): ?>
            <div class="alert alert-<?= in_array($msg, ['blocked','unblocked','smtp_saved']) ? 'success' : 'info' ?>" style="margin-bottom:1rem">
                <?= match($msg) {
                    'blocked'     => 'Utilisateur bloqué.',
                    'unblocked'   => 'Utilisateur débloqué.',
                    'smtp_saved'  => 'Configuration SMTP enregistrée.',
                    default       => ''
                } ?>
            </div>
        <?php endif; ?>
        <?php if ($err = ($_GET['error'] ?? '')): ?>
            <div class="alert alert-error" style="margin-bottom:1rem">
                <?= match($err) {
                    'blocked_user' => "Impossible de se connecter en tant qu'un compte bloqué — débloquez-le d'abord.",
                    'not_found'    => 'Utilisateur introuvable.',
                    default        => "Une erreur s'est produite."
                } ?>
            </div>
        <?php endif; ?>

        <?php if ($tab === 'dashboard'): ?>
        <h2>Tableau de bord</h2>
        <div class="admin-stats-grid">
            <div class="admin-stat-card"><div class="stat-val"><?= $stats['families'] ?></div><div class="stat-label">Familles</div></div>
            <div class="admin-stat-card"><div class="stat-val"><?= $stats['users'] ?></div><div class="stat-label">Utilisateurs</div></div>
            <div class="admin-stat-card <?= $stats['blocked'] ? 'stat-warn' : '' ?>"><div class="stat-val"><?= $stats['blocked'] ?></div><div class="stat-label">Comptes bloqués</div></div>
            <div class="admin-stat-card <?= $stats['tickets'] ? 'stat-warn' : '' ?>"><div class="stat-val"><?= $stats['tickets'] ?></div><div class="stat-label">Tickets ouverts</div></div>
        </div>

        <?php elseif ($tab === 'families'): ?>
        <h2>Familles (<?= count($families) ?>)</h2>
        <table class="admin-table">
            <thead><tr><th>#</th><th>Nom</th><th>Membres</th><th>Créé le</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($families as $f): ?>
            <tr>
                <td><?= $f['id'] ?></td>
                <td><strong><?= htmlspecialchars($f['name']) ?></strong></td>
                <td><?= $f['member_count'] ?></td>
                <td><?= \App\Core\DateHelper::fromUtc($f['created_at'], 'd/m/Y') ?></td>
                <td><a href="<?= BASE_URL ?>/admin?tab=users&family=<?= $f['id'] ?>" class="btn btn-secondary btn-sm">Voir membres</a></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <?php elseif ($tab === 'users'): ?>
        <?php $filterFamily = (int)($_GET['family'] ?? 0); ?>
        <h2>Utilisateurs<?= $filterFamily ? ' · Famille #' . $filterFamily : '' ?></h2>
        <table class="admin-table">
            <thead><tr><th>#</th><th>Nom</th><th>Email</th><th>Famille</th><th>Rôle</th><th>Statut</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($users as $u): ?>
            <?php if ($filterFamily && $u['family_id'] !== $filterFamily) continue; ?>
            <tr class="<?= $u['blocked_at'] ? 'row-blocked' : '' ?>">
                <td><?= $u['id'] ?></td>
                <td><?= htmlspecialchars($u['name']) ?></td>
                <td><?= htmlspecialchars($u['email']) ?></td>
                <td><?= htmlspecialchars($u['family_name']) ?></td>
                <td><?= $u['role'] === 'admin' ? '👑' : '' ?> <?= $u['role'] ?></td>
                <td>
                    <?php if ($u['blocked_at']): ?>
                        <span class="badge badge-danger" title="<?= htmlspecialchars($u['blocked_reason'] ?? '') ?>">🚫 Bloqué</span>
                    <?php else: ?>
                        <span class="badge badge-ok">✅ Actif</span>
                    <?php endif; ?>
                </td>
                <td style="display:flex;gap:.4rem;flex-wrap:wrap">
                    <?php if ($u['blocked_at']): ?>
                        <form method="POST" action="<?= BASE_URL ?>/admin/users/<?= $u['id'] ?>/unblock">
                            <button class="btn btn-secondary btn-sm">Débloquer</button>
                        </form>
                    <?php else: ?>
                        <form method="POST" action="<?= BASE_URL ?>/admin/users/<?= $u['id'] ?>/block" style="display:flex;gap:.3rem">
                            <input type="text" name="reason" placeholder="Raison (optionnel)" style="font-size:.78rem;padding:.2rem .4rem;border:1px solid var(--border);border-radius:4px;width:140px">
                            <button class="btn btn-danger btn-sm">Bloquer</button>
                        </form>
                        <form method="POST" action="<?= BASE_URL ?>/admin/users/<?= $u['id'] ?>/impersonate" onsubmit="return confirm('Se connecter en tant que <?= htmlspecialchars(addslashes($u['name'])) ?> ? Cette action est journalisée.')">
                            <button class="btn btn-secondary btn-sm" title="Se connecter en tant que cet utilisateur">🕵️ Impersoner</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <?php elseif ($tab === 'impersonation'): ?>
        <h2>Journal d'impersonation</h2>
        <p style="color:var(--text-muted);font-size:.85rem;margin-bottom:1rem">
            Historique des connexions d'un admin système en tant qu'un membre de famille, pour du support.
        </p>
        <?php $impersonationLog = \App\Models\ImpersonationLog::getRecent(100); ?>
        <?php if (empty($impersonationLog)): ?>
            <p class="empty-state">Aucune impersonation enregistrée.</p>
        <?php else: ?>
        <table class="admin-table">
            <thead><tr><th>Admin</th><th>Utilisateur ciblé</th><th>IP</th><th>Début</th><th>Fin</th></tr></thead>
            <tbody>
            <?php foreach ($impersonationLog as $l): ?>
            <tr>
                <td><?= htmlspecialchars($l['admin_username']) ?></td>
                <td><?= htmlspecialchars($l['target_name']) ?> <span style="color:var(--text-muted)">(<?= htmlspecialchars($l['target_email']) ?>)</span></td>
                <td><code><?= htmlspecialchars($l['ip'] ?? '—') ?></code></td>
                <td><?= \App\Core\DateHelper::fromUtc($l['started_at'], 'd/m/Y H:i') ?></td>
                <td><?= $l['ended_at'] ? \App\Core\DateHelper::fromUtc($l['ended_at'], 'd/m/Y H:i') : '<span class="badge badge-warn">En cours</span>' ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>

        <?php elseif ($tab === 'ips'): ?>
        <h2>IPs bloquées</h2>
        <form method="POST" action="<?= BASE_URL ?>/admin/ips" class="admin-inline-form">
            <input type="text" name="ip" placeholder="Adresse IP (ex: 1.2.3.4)" required style="width:180px">
            <input type="text" name="reason" placeholder="Raison (optionnel)" style="width:220px">
            <button class="btn btn-danger btn-sm">Bloquer cette IP</button>
        </form>
        <?php if (empty($blockedIps)): ?>
            <p class="empty-state">Aucune IP bloquée.</p>
        <?php else: ?>
        <table class="admin-table" style="margin-top:1rem">
            <thead><tr><th>IP</th><th>Raison</th><th>Ajoutée le</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($blockedIps as $ip): ?>
            <tr>
                <td><code><?= htmlspecialchars($ip['ip']) ?></code></td>
                <td><?= htmlspecialchars($ip['reason'] ?? '—') ?></td>
                <td><?= \App\Core\DateHelper::fromUtc($ip['created_at'], 'd/m/Y H:i') ?></td>
                <td>
                    <form method="POST" action="<?= BASE_URL ?>/admin/ips/<?= $ip['id'] ?>/delete">
                        <button class="btn btn-secondary btn-sm">Débloquer</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>

        <?php elseif ($tab === 'tickets'): ?>
        <h2>Tickets de support (<?= count(array_filter($tickets, fn($t) => $t['status'] !== 'closed')) ?> ouverts)</h2>
        <?php if (empty($tickets)): ?>
            <p class="empty-state">Aucun ticket.</p>
        <?php else: ?>
        <table class="admin-table">
            <thead><tr><th>#</th><th>Sujet</th><th>Utilisateur</th><th>Famille</th><th>Statut</th><th>Mis à jour</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($tickets as $t): ?>
            <tr class="<?= $t['status'] === 'closed' ? 'row-muted' : '' ?>">
                <td><?= $t['id'] ?></td>
                <td><strong><?= htmlspecialchars($t['subject']) ?></strong></td>
                <td><?= htmlspecialchars($t['user_name']) ?></td>
                <td><?= htmlspecialchars($t['family_name']) ?></td>
                <td><span class="badge badge-<?= $t['status'] === 'closed' ? 'ok' : ($t['status'] === 'in_progress' ? 'warn' : 'new') ?>"><?= match($t['status']) { 'open'=>'🆕 Ouvert','in_progress'=>'💬 En cours','closed'=>'✅ Fermé',default=>$t['status'] } ?></span></td>
                <td><?= \App\Core\DateHelper::fromUtc($t['updated_at'], 'd/m/Y H:i') ?></td>
                <td><a href="<?= BASE_URL ?>/admin/tickets/<?= $t['id'] ?>" class="btn btn-secondary btn-sm">Voir</a></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>

        <?php elseif ($tab === 'smtp'): ?>
        <h2>Configuration SMTP (globale)</h2>
        <p style="color:var(--text-muted);font-size:.85rem;margin-bottom:1rem">
            Un seul serveur SMTP est utilisé pour l'envoi d'emails de toutes les familles inscrites.
        </p>
        <form method="POST" action="<?= BASE_URL ?>/admin/smtp" class="card" style="padding:1.25rem;max-width:640px">
            <div class="form-row">
                <div class="form-group flex-2">
                    <label>Serveur SMTP</label>
                    <input type="text" name="smtp_host" value="<?= htmlspecialchars($smtp['host'] ?? '') ?>" placeholder="smtp.gmail.com">
                </div>
                <div class="form-group">
                    <label>Port</label>
                    <input type="number" name="smtp_port" value="<?= htmlspecialchars($smtp['port'] ?? '587') ?>">
                </div>
                <div class="form-group">
                    <label>Chiffrement</label>
                    <select name="smtp_encryption">
                        <option value="tls" <?= ($smtp['encryption'] ?? '') === 'tls' ? 'selected' : '' ?>>TLS</option>
                        <option value="ssl" <?= ($smtp['encryption'] ?? '') === 'ssl' ? 'selected' : '' ?>>SSL</option>
                        <option value="none" <?= ($smtp['encryption'] ?? '') === 'none' ? 'selected' : '' ?>>Aucun</option>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Identifiant</label>
                    <input type="text" name="smtp_user" value="<?= htmlspecialchars($smtp['username'] ?? '') ?>" placeholder="user@gmail.com">
                </div>
                <div class="form-group">
                    <label>Mot de passe</label>
                    <input type="password" name="smtp_pass" value="<?= htmlspecialchars($smtp['password'] ?? '') ?>">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Email expéditeur</label>
                    <input type="email" name="smtp_from_email" value="<?= htmlspecialchars($smtp['from_email'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Nom expéditeur</label>
                    <input type="text" name="smtp_from_name" value="<?= htmlspecialchars($smtp['from_name'] ?? 'FamilyBoard') ?>">
                </div>
            </div>
            <button type="submit" class="btn btn-primary">Enregistrer la configuration SMTP</button>
        </form>

        <?php if ($smtp): ?>
        <div class="card" style="padding:1.25rem;max-width:640px;margin-top:1rem">
            <h3 style="margin-top:0">Tester la configuration</h3>
            <div style="display:flex;gap:.75rem;align-items:center;flex-wrap:wrap;margin-bottom:.75rem">
                <button type="button" class="btn btn-secondary" onclick="testSmtp()">🔌 Tester la connexion</button>
                <input type="email" id="smtp-test-email" placeholder="destinataire@exemple.com" style="flex:1;min-width:220px">
                <button type="button" class="btn btn-secondary" onclick="sendTestSmtpEmail()">📨 Envoyer un email test</button>
            </div>
            <div id="smtp-test-result"></div>
        </div>
        <?php endif; ?>

        <?php endif; ?>
    </main>
</div>
<script>const BASE_URL = <?= json_encode(BASE_URL) ?>;</script>
<script src="<?= ASSETS_URL ?>/js/admin.js?v=<?= APP_VERSION ?>"></script>
</body>
</html>
