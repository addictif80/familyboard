<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administration — FamilyBoard</title>
    <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/app.css?v=<?= APP_VERSION ?>">
</head>
<body class="admin-body">
<div class="admin-layout">
    <!-- Sidebar -->
    <nav class="admin-sidebar">
        <div class="admin-sidebar-header">
            <span>🔐 Admin</span>
        </div>
        <ul class="admin-nav">
            <?php foreach (['dashboard'=>'📊 Tableau de bord','families'=>'🏠 Familles','users'=>'👥 Utilisateurs','ips'=>'🚫 IPs bloquées','push'=>'🔔 Notifications Push','tickets'=>'🎫 Tickets support'] as $t=>$label): ?>
            <li class="<?= $tab === $t ? 'active' : '' ?>">
                <a href="<?= BASE_URL ?>/admin?tab=<?= $t ?>"><?= $label ?></a>
            </li>
            <?php endforeach; ?>
            <li><a href="<?= BASE_URL ?>/admin/profile">👤 Mon profil</a></li>
        </ul>
        <div class="admin-sidebar-footer">
            <a href="<?= BASE_URL ?>/" style="font-size:.8rem;color:var(--text-muted)">← Site</a>
            <a href="<?= BASE_URL ?>/admin/logout" class="btn btn-danger btn-sm" style="margin-left:auto">Déconnexion</a>
        </div>
    </nav>

    <!-- Content -->
    <main class="admin-content">
        <?php if ($msg = ($_GET['msg'] ?? '')): ?>
            <?php
                preg_match('/^sent_(\d+)(?:_fail_(\d+))?$/', $msg, $pm);
                $isAlert = !empty($pm) && ((int)($pm[2] ?? 0) > 0 || (int)($pm[1] ?? 0) === 0);
            ?>
            <div class="alert alert-<?= $isAlert ? 'danger' : 'success' ?>" style="margin-bottom:1rem">
                <?php if (!empty($pm)): ?>
                    <?php $ok = (int)$pm[1]; $fail = (int)($pm[2] ?? 0); ?>
                    <?= $ok ?> notification(s) envoyée(s) avec succès<?= $fail ? ", <strong>{$fail} échec(s)</strong>" : '' ?>.
                    <?php if ($fail && ($pushErr = ($_SESSION['push_last_error'] ?? ''))): unset($_SESSION['push_last_error']); ?>
                        <br><code style="font-size:.78rem"><?= htmlspecialchars($pushErr) ?></code>
                    <?php endif; ?>
                <?php else: ?>
                    <?= match($msg) {
                        'blocked'        => 'Utilisateur bloqué.',
                        'unblocked'      => 'Utilisateur débloqué.',
                        'keys_generated' => 'Clés VAPID générées.',
                        'error'          => 'Erreur : titre et clés VAPID requis.',
                        default          => ''
                    } ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($tab === 'dashboard'): ?>
        <h2>Tableau de bord</h2>
        <div class="admin-stats-grid">
            <div class="admin-stat-card"><div class="stat-val"><?= $stats['families'] ?></div><div class="stat-label">Familles</div></div>
            <div class="admin-stat-card"><div class="stat-val"><?= $stats['users'] ?></div><div class="stat-label">Utilisateurs</div></div>
            <div class="admin-stat-card <?= $stats['blocked'] ? 'stat-warn' : '' ?>"><div class="stat-val"><?= $stats['blocked'] ?></div><div class="stat-label">Comptes bloqués</div></div>
            <div class="admin-stat-card <?= $stats['tickets'] ? 'stat-warn' : '' ?>"><div class="stat-val"><?= $stats['tickets'] ?></div><div class="stat-label">Tickets ouverts</div></div>
            <div class="admin-stat-card"><div class="stat-val"><?= $stats['push_subs'] ?></div><div class="stat-label">Abonnés Push</div></div>
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
                <td><?= date('d/m/Y', strtotime($f['created_at'])) ?></td>
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
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

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
                <td><?= date('d/m/Y H:i', strtotime($ip['created_at'])) ?></td>
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

        <?php elseif ($tab === 'push'): ?>
        <h2>Notifications Push</h2>
        <div class="card" style="max-width:600px">
            <div class="card-header"><h3>Clés VAPID</h3></div>
            <div style="padding:1rem">
                <?php if ($vapidPublic): ?>
                    <p style="margin-bottom:.5rem"><strong>Clé publique :</strong></p>
                    <code style="word-break:break-all;font-size:.75rem;background:var(--bg);padding:.4rem .6rem;border-radius:4px;display:block"><?= htmlspecialchars($vapidPublic) ?></code>
                    <p style="font-size:.8rem;color:var(--text-muted);margin-top:.5rem">⚠️ Regénérer invalide toutes les souscriptions existantes.</p>
                <?php else: ?>
                    <p style="color:var(--text-muted)">Aucune clé VAPID. Générez-en une pour activer les push.</p>
                <?php endif; ?>
                <form method="POST" action="<?= BASE_URL ?>/admin/push/keys" style="margin-top:.75rem">
                    <button class="btn btn-secondary btn-sm"><?= $vapidPublic ? '🔄 Regénérer' : '✨ Générer les clés VAPID' ?></button>
                </form>
            </div>
        </div>
        <?php if ($vapidPublic): ?>
        <div class="card" style="max-width:600px;margin-top:1rem">
            <div class="card-header"><h3>🔬 Diagnostic</h3></div>
            <div style="padding:1rem">
                <p style="font-size:.875rem;color:var(--text-muted);margin-bottom:.75rem">Envoie un push test au premier abonné et affiche la réponse brute du service push.</p>
                <button class="btn btn-secondary btn-sm" onclick="testPushDebug(this)">Lancer le test</button>
                <pre id="push-debug-result" style="display:none;margin-top:.75rem;font-size:.75rem;background:var(--bg);padding:.75rem;border-radius:6px;overflow-x:auto;white-space:pre-wrap;word-break:break-all"></pre>
            </div>
        </div>
        <div class="card" style="max-width:600px;margin-top:1rem">
            <div class="card-header"><h3>Envoyer une notification</h3></div>
            <div style="padding:1rem">
                <form method="POST" action="<?= BASE_URL ?>/admin/push/send">
                    <div class="form-group">
                        <label>Titre *</label>
                        <input type="text" name="title" required placeholder="Nouvelle annonce…">
                    </div>
                    <div class="form-group">
                        <label>Message</label>
                        <input type="text" name="body" placeholder="Contenu de la notification…">
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>URL cible</label>
                            <input type="text" name="url" value="/" placeholder="/">
                        </div>
                        <div class="form-group">
                            <label>Destinataires</label>
                            <select name="family_id">
                                <option value="0">— Tous les utilisateurs —</option>
                                <?php foreach ($families as $f): ?>
                                    <option value="<?= $f['id'] ?>"><?= htmlspecialchars($f['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <button class="btn btn-primary">📤 Envoyer</button>
                </form>
            </div>
        </div>
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
                <td><?= date('d/m/Y H:i', strtotime($t['updated_at'])) ?></td>
                <td><a href="<?= BASE_URL ?>/admin/tickets/<?= $t['id'] ?>" class="btn btn-secondary btn-sm">Voir</a></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>

        <?php endif; ?>
    </main>
</div>
<script>
async function testPushDebug(btn) {
    btn.disabled = true; btn.textContent = '⏳ Test en cours…';
    const pre = document.getElementById('push-debug-result');
    try {
        const r = await fetch('<?= BASE_URL ?>/admin/push/test');
        const data = await r.json();
        pre.textContent = JSON.stringify(data, null, 2);
        pre.style.display = 'block';
        pre.style.color = (data.http_status >= 200 && data.http_status < 300) ? 'var(--success,green)' : 'var(--danger,red)';
    } catch(e) {
        pre.textContent = 'Erreur réseau : ' + e.message;
        pre.style.display = 'block';
    }
    btn.disabled = false; btn.textContent = 'Relancer le test';
}
</script>
<script src="<?= ASSETS_URL ?>/js/admin.js?v=<?= APP_VERSION ?>"></script>
</body>
</html>
