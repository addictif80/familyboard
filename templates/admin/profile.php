<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil administrateur — FamilyBoard</title>
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
            <li><a href="<?= BASE_URL ?>/admin?tab=<?= $t ?>"><?= $label ?></a></li>
            <?php endforeach; ?>
            <li class="active"><a href="<?= BASE_URL ?>/admin/profile">👤 Mon profil</a></li>
        </ul>
        <div class="admin-sidebar-footer">
            <a href="<?= BASE_URL ?>/" style="font-size:.8rem;color:var(--text-muted)">← Site</a>
            <a href="<?= BASE_URL ?>/admin/logout" class="btn btn-danger btn-sm" style="margin-left:auto">Déconnexion</a>
        </div>
    </nav>

    <!-- Content -->
    <main class="admin-content">
        <h2>Mon profil</h2>

        <?php $error = $_GET['error'] ?? ''; $msg = $_GET['msg'] ?? ''; ?>
        <?php if ($msg === 'saved'): ?>
            <div class="alert alert-success" style="margin-bottom:1rem">Profil mis à jour avec succès.</div>
        <?php elseif ($error === 'wrong_password'): ?>
            <div class="alert alert-danger" style="margin-bottom:1rem">Mot de passe actuel incorrect.</div>
        <?php elseif ($error === 'password_mismatch'): ?>
            <div class="alert alert-danger" style="margin-bottom:1rem">Les nouveaux mots de passe ne correspondent pas.</div>
        <?php elseif ($error === 'password_short'): ?>
            <div class="alert alert-danger" style="margin-bottom:1rem">Le nouveau mot de passe doit contenir au moins 8 caractères.</div>
        <?php endif; ?>

        <div class="card" style="max-width:480px">
            <div class="card-header">
                <h3>Informations de connexion</h3>
            </div>
            <div style="padding:1.25rem">
                <form method="POST" action="<?= BASE_URL ?>/admin/profile">

                    <div class="form-group">
                        <label>Nom d'utilisateur actuel</label>
                        <input type="text" value="<?= htmlspecialchars($adminUsername) ?>" disabled
                               style="background:var(--bg-secondary,#f5f5f5);color:var(--text-muted);cursor:not-allowed">
                    </div>

                    <div class="form-group">
                        <label>Nouveau nom d'utilisateur <span style="color:var(--text-muted);font-size:.8rem">(laisser vide pour ne pas changer)</span></label>
                        <input type="text" name="username" autocomplete="username"
                               placeholder="<?= htmlspecialchars($adminUsername) ?>">
                    </div>

                    <hr style="margin:1.25rem 0;border:none;border-top:1px solid var(--border)">

                    <div class="form-group">
                        <label>Mot de passe actuel *</label>
                        <input type="password" name="current_password" required autocomplete="current-password">
                    </div>

                    <div class="form-group">
                        <label>Nouveau mot de passe <span style="color:var(--text-muted);font-size:.8rem">(laisser vide pour ne pas changer)</span></label>
                        <input type="password" name="new_password" autocomplete="new-password" minlength="8"
                               placeholder="8 caractères minimum">
                    </div>

                    <div class="form-group">
                        <label>Confirmer le nouveau mot de passe</label>
                        <input type="password" name="confirm_password" autocomplete="new-password"
                               placeholder="Répétez le nouveau mot de passe">
                    </div>

                    <button type="submit" class="btn btn-primary" style="width:100%">💾 Enregistrer</button>
                </form>
            </div>
        </div>

        <div class="card" style="max-width:480px;margin-top:1rem">
            <div class="card-header"><h3>Informations</h3></div>
            <div style="padding:1rem;font-size:.875rem;color:var(--text-muted);line-height:1.6">
                <p>Les identifiants modifiés ici sont stockés en base de données et ont priorité sur les constantes définies dans <code>config.local.php</code>.</p>
                <p style="margin-top:.5rem">Pour revenir aux identifiants de configuration, supprimez les entrées <code>admin_username</code> et <code>admin_password_hash</code> de la table <code>app_settings</code>.</p>
            </div>
        </div>
    </main>
</div>
</body>
</html>
