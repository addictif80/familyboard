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
    <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/quill.snow.css?v=<?= APP_VERSION ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Baloo+2:wght@500;600;700;800&family=JetBrains+Mono:wght@500;700&display=swap">
</head>
<body class="admin-body">
<div class="admin-layout">
    <!-- Sidebar -->
    <nav class="admin-sidebar">
        <div class="admin-sidebar-header">
            <span>🔐 Admin</span>
        </div>
        <ul class="admin-nav">
            <?php
            // Une clé "tab:sub" (voir boucle de rendu ci-dessous) pointe directement sur un
            // sous-onglet précis d'une page à sous-onglets gérés en JS (voir
            // templates/admin/tabs/storage.php, switchAdminSubTab()) plutôt que de rouvrir la
            // page sur son premier sous-onglet par défaut à chaque clic.
            $adminNavGroups = [
                'Vue d\'ensemble' => ['dashboard' => '📊 Tableau de bord', 'health' => '🩺 Santé plateforme'],
                'Comptes' => ['families' => '🏠 Familles', 'users' => '👥 Utilisateurs', 'ldap-directory' => '🗂️ Annuaire LDAP', 'deleted-accounts' => '🗑️ Comptes supprimés'],
                'Facturation' => ['subscriptions' => '💳 Abonnements', 'referrals' => '🤝 Parrainage'],
                'Stockage & fichiers' => [
                    'storage' => '⚙️ Réglages',
                    'storage:storage-produits' => '📦 Paliers de stockage',
                    'storage:storage-consommation' => '📊 Consommation',
                    'storage:storage-rattrapage' => '🔄 Rattrapage fichiers',
                    'storage:storage-nextcloud-migration' => '☁️ Migration Nextcloud',
                    'storage:storage-infra' => '🖥️ LDAP / Synology',
                ],
                'Support & sécurité' => ['tickets' => '🎫 Tickets support', 'impersonation' => '🕵️ Impersonation', 'ips' => '🚫 IPs bloquées'],
                'Communication' => ['notifications' => '📣 Notifications & intégrations', 'smtp' => '✉️ SMTP', 'email' => '📧 Emails'],
                'Contenu' => ['announcements' => '📣 Annonces', 'testimonials' => '💬 Témoignages', 'namedays' => '🎉 Fêtes des prénoms', 'highlights' => '🏢 Mises en avant ABHD', 'links' => '🔗 Liens certifiés', 'legal' => '📜 Contenu légal', 'roadmap' => '🗺️ Roadmap'],
            ];
            $currentSub = $_GET['sub'] ?? '';
            ?>
            <?php foreach ($adminNavGroups as $groupLabel => $items): ?>
            <li class="admin-nav-group">
                <div class="admin-nav-group-label"><?= $groupLabel ?></div>
                <ul style="list-style:none;margin:0;padding:0">
                    <?php foreach ($items as $t => $label):
                        [$realTab, $sub] = str_contains($t, ':') ? explode(':', $t, 2) : [$t, null];
                        $isActive = $tab === $realTab && ($sub === null ? $currentSub === '' : $currentSub === $sub);
                        $href = BASE_URL . '/admin?tab=' . $realTab . ($sub ? '&sub=' . $sub : '');
                    ?>
                    <li class="<?= $isActive ? 'active' : '' ?>">
                        <a href="<?= $href ?>"><?= $label ?></a>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </li>
            <?php endforeach; ?>
            <li class="admin-nav-group">
                <div class="admin-nav-group-label">Compte</div>
                <ul style="list-style:none;margin:0;padding:0">
                    <li><a href="<?= BASE_URL ?>/admin/profile">👤 Mon profil</a></li>
                </ul>
            </li>
        </ul>
        <div class="admin-sidebar-footer">
            <a href="<?= BASE_URL ?>/" style="font-size:.8rem;color:var(--sidebar-text)">← Site</a>
            <button class="theme-toggle-btn" onclick="toggleTheme()" title="Changer de thème" style="margin-left:.5rem"><span class="theme-icon-sun">☀️</span><span class="theme-icon-moon">🌙</span></button>
            <a href="<?= BASE_URL ?>/admin/logout" class="btn btn-danger btn-sm" style="margin-left:auto">Déconnexion</a>
        </div>
    </nav>

    <!-- Content -->
    <main class="admin-content">
        <?php if (\App\Models\AppSetting::get('admin_password_hash') === null && ADMIN_PASS === 'changeme'): ?>
            <div class="alert alert-error" style="margin-bottom:1rem">
                ⚠️ Le mot de passe administrateur est encore la valeur par défaut (<code>changeme</code>).
                <a href="<?= BASE_URL ?>/admin/profile">Changez-le immédiatement</a>.
            </div>
        <?php endif; ?>
        <?php if ($msg = ($_GET['msg'] ?? '')): ?>
            <div class="alert alert-<?= in_array($msg, ['highlight_invalid','link_invalid','link_unreachable','delete_failed','delete_admin_blocked','report_resend_failed','nameday_invalid','roadmap_invalid','plan_saved_stripe_error','urssaf_send_failed','urssaf_no_admin_email','urssaf_stripe_not_configured']) ? 'error' : (in_array($msg, ['blocked','unblocked','user_deleted','report_resent','smtp_saved','email_saved','notification_sent','meteofrance_saved','vaultwarden_saved','mailcow_saved','2fa_policy_saved','highlight_saved','highlight_deleted','link_saved','link_deleted','nameday_added','roadmap_saved','roadmap_deleted','sub_settings_saved','stripe_saved','plan_saved','plan_deactivated','urssaf_settings_saved','urssaf_sent']) ? 'success' : 'info') ?>" style="margin-bottom:1rem">
                <?= match($msg) {
                    'blocked'             => 'Utilisateur bloqué.',
                    'unblocked'           => 'Utilisateur débloqué.',
                    'user_deleted'        => 'Compte supprimé, e-mail de notification envoyé.',
                    'delete_failed'       => 'Suppression annulée : motif requis.',
                    'delete_admin_blocked'=> 'Impossible de supprimer un compte administrateur depuis ce panneau (transfert de rôle ou suppression de la famille à faire depuis le compte lui-même).',
                    'report_resent'        => 'Rapport de suppression renvoyé.',
                    'report_resend_failed' => 'Échec de l\'envoi du rapport.',
                    'smtp_saved'          => 'Configuration SMTP enregistrée.',
                    'email_saved'         => 'Contenu de l\'email enregistré.',
                    'notification_sent'   => 'Notification envoyée.',
                    'meteofrance_saved'   => 'Clé API Météo-France enregistrée.',
                    'vaultwarden_saved'   => 'Configuration Vaultwarden enregistrée.',
                    'ollama_saved'        => 'Configuration Ollama enregistrée.',
                    '2fa_policy_saved'    => 'Politique de double authentification enregistrée.',
                    'highlight_saved'     => 'Mise en avant enregistrée.',
                    'highlight_deleted'   => 'Mise en avant supprimée.',
                    'highlight_invalid'   => 'Titre et lien (http/https valide) requis.',
                    'link_saved'          => 'Lien certifié enregistré.',
                    'link_deleted'        => 'Lien certifié supprimé.',
                    'link_invalid'        => 'Lien (http/https) requis.',
                    'link_unreachable'    => 'Ce lien n\'a pas pu être vérifié (adresse non autorisée ou site injoignable).',
                    'nameday_added'       => 'Fête ajoutée au calendrier des prénoms.',
                    'nameday_invalid'     => 'Prénom et date requis.',
                    'mailcow_saved'       => 'Configuration Mailcow enregistrée — alias e-mail synchronisés.',
                    'roadmap_saved'       => 'Idée enregistrée.',
                    'roadmap_deleted'     => 'Idée supprimée.',
                    'roadmap_invalid'     => 'Titre et statut requis.',
                    'sub_settings_saved'  => 'Réglages de facturation enregistrés.',
                    'stripe_saved'        => 'Clés Stripe enregistrées.',
                    'plan_saved'          => 'Palier enregistré. Produit/prix Stripe synchronisés automatiquement.',
                    'plan_saved_stripe_error' => 'Palier enregistré, mais la synchronisation avec Stripe a échoué (vérifiez la clé secrète dans l\'onglet « Clés Stripe »).',
                    'urssaf_settings_saved' => 'Réglages du rapport URSSAF enregistrés.',
                    'urssaf_sent'          => 'Rapport URSSAF envoyé par e-mail.',
                    'urssaf_send_failed'   => 'Échec de l\'envoi du rapport URSSAF (vérifiez la configuration SMTP).',
                    'urssaf_no_admin_email' => 'Aucune adresse e-mail admin configurée (onglet Profil admin).',
                    'urssaf_stripe_not_configured' => 'Stripe n\'est pas configuré (onglet « Clés Stripe »).',
                    'plan_deactivated'    => 'Palier désactivé.',
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

        <?php
        $tabFile = BASE_PATH . '/templates/admin/tabs/' . $tab . '.php';
        if (!is_file($tabFile)) { $tabFile = BASE_PATH . '/templates/admin/tabs/dashboard.php'; }
        require $tabFile;
        ?>
    </main>
</div>
<script>const BASE_URL = <?= json_encode(BASE_URL) ?>;</script>
<script src="<?= ASSETS_URL ?>/js/quill.min.js?v=<?= APP_VERSION ?>"></script>
<script src="<?= ASSETS_URL ?>/js/admin.js?v=<?= APP_VERSION ?>"></script>
</body>
</html>
