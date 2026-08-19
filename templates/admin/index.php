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
            <?php foreach (['dashboard'=>'📊 Tableau de bord','families'=>'🏠 Familles','users'=>'👥 Utilisateurs','deleted-accounts'=>'🗑️ Comptes supprimés','notifications'=>'📣 Notifications','impersonation'=>'🕵️ Impersonation','ips'=>'🚫 IPs bloquées','tickets'=>'🎫 Tickets support','smtp'=>'✉️ SMTP','email'=>'📧 Emails','highlights'=>'🏢 Mises en avant ABHD','links'=>'🔗 Liens certifiés','legal'=>'📜 Contenu légal'] as $t=>$label): ?>
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
        <?php if (\App\Models\AppSetting::get('admin_password_hash') === null && ADMIN_PASS === 'changeme'): ?>
            <div class="alert alert-error" style="margin-bottom:1rem">
                ⚠️ Le mot de passe administrateur est encore la valeur par défaut (<code>changeme</code>).
                <a href="<?= BASE_URL ?>/admin/profile">Changez-le immédiatement</a>.
            </div>
        <?php endif; ?>
        <?php if ($msg = ($_GET['msg'] ?? '')): ?>
            <div class="alert alert-<?= in_array($msg, ['highlight_invalid','link_invalid','link_unreachable','delete_failed','delete_admin_blocked']) ? 'error' : (in_array($msg, ['blocked','unblocked','user_deleted','smtp_saved','email_saved','notification_sent','meteofrance_saved','vaultwarden_saved','2fa_policy_saved','highlight_saved','highlight_deleted','link_saved','link_deleted']) ? 'success' : 'info') ?>" style="margin-bottom:1rem">
                <?= match($msg) {
                    'blocked'             => 'Utilisateur bloqué.',
                    'unblocked'           => 'Utilisateur débloqué.',
                    'user_deleted'        => 'Compte supprimé, e-mail de notification envoyé.',
                    'delete_failed'       => 'Suppression annulée : motif requis.',
                    'delete_admin_blocked'=> 'Impossible de supprimer un compte administrateur depuis ce panneau (transfert de rôle ou suppression de la famille à faire depuis le compte lui-même).',
                    'smtp_saved'          => 'Configuration SMTP enregistrée.',
                    'email_saved'         => 'Contenu de l\'email enregistré.',
                    'notification_sent'   => 'Notification envoyée.',
                    'meteofrance_saved'   => 'Clé API Météo-France enregistrée.',
                    'vaultwarden_saved'   => 'Configuration Vaultwarden enregistrée.',
                    '2fa_policy_saved'    => 'Politique de double authentification enregistrée.',
                    'highlight_saved'     => 'Mise en avant enregistrée.',
                    'highlight_deleted'   => 'Mise en avant supprimée.',
                    'highlight_invalid'   => 'Titre et lien (http/https valide) requis.',
                    'link_saved'          => 'Lien certifié enregistré.',
                    'link_deleted'        => 'Lien certifié supprimé.',
                    'link_invalid'        => 'Lien (http/https) requis.',
                    'link_unreachable'    => 'Ce lien n\'a pas pu être vérifié (adresse non autorisée ou site injoignable).',
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
                        <form method="POST" action="<?= BASE_URL ?>/admin/users/<?= $u['id'] ?>/unblock"><?= \App\Core\Csrf::field() ?>
                            <button class="btn btn-secondary btn-sm">Débloquer</button>
                        </form>
                    <?php else: ?>
                        <form method="POST" action="<?= BASE_URL ?>/admin/users/<?= $u['id'] ?>/block" style="display:flex;gap:.3rem"><?= \App\Core\Csrf::field() ?>
                            <input type="text" name="reason" placeholder="Raison (optionnel)" style="font-size:.78rem;padding:.2rem .4rem;border:1px solid var(--border);border-radius:4px;width:140px">
                            <button class="btn btn-danger btn-sm">Bloquer</button>
                        </form>
                        <form method="POST" action="<?= BASE_URL ?>/admin/users/<?= $u['id'] ?>/impersonate" onsubmit="return confirm('Se connecter en tant que <?= htmlspecialchars(addslashes($u['name'])) ?> ? Cette action est journalisée.')"><?= \App\Core\Csrf::field() ?>
                            <button class="btn btn-secondary btn-sm" title="Se connecter en tant que cet utilisateur">🕵️ Impersoner</button>
                        </form>
                    <?php endif; ?>
                    <?php if ($u['role'] !== 'admin'): ?>
                        <form method="POST" action="<?= BASE_URL ?>/admin/users/<?= $u['id'] ?>/delete" style="display:flex;gap:.3rem;align-items:center;flex-wrap:wrap"
                              onsubmit="return confirmUserDelete(this, '<?= htmlspecialchars(addslashes($u['name']), ENT_QUOTES) ?>')">
                            <?= \App\Core\Csrf::field() ?>
                            <input type="text" name="reason" placeholder="Motif (requis)" required style="font-size:.78rem;padding:.2rem .4rem;border:1px solid var(--border);border-radius:4px;width:140px">
                            <label style="display:flex;align-items:center;gap:.25rem;font-size:.72rem;color:var(--text-muted);white-space:nowrap" title="Supprime aussi le contenu normalement conservé (documents, événements, photos…), pas seulement le compte">
                                <input type="checkbox" name="purge_all" value="1"> Tout supprimer
                            </label>
                            <button class="btn btn-danger btn-sm">🗑️ Supprimer</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <?php elseif ($tab === 'deleted-accounts'): ?>
        <h2>Comptes supprimés</h2>
        <p style="color:var(--text-muted);font-size:.85rem;margin-bottom:1rem">
            Quand un compte (membre classique ou co-parent) est supprimé — par lui-même,
            l'administrateur de sa famille, ou un administrateur système — son contenu
            (documents, événements, journal parental, photos, liens proposés) est conservé,
            jamais supprimé automatiquement. La purge définitive ci-dessous est <strong>irréversible</strong>
            et supprime aussi les fichiers associés.
        </p>
        <?php if (empty($deletedUsers)): ?>
            <p class="empty-state">Aucun compte supprimé pour l'instant.</p>
        <?php else: ?>
        <table class="admin-table">
            <thead><tr><th>Nom</th><th>Email</th><th>Famille</th><th>Rôle</th><th>Supprimé par</th><th>Le</th><th>Statut</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($deletedUsers as $du): ?>
            <tr>
                <td><?= htmlspecialchars($du['name']) ?></td>
                <td><?= htmlspecialchars($du['email']) ?></td>
                <td><?= htmlspecialchars($du['family_name']) ?></td>
                <td><?= $du['role'] === 'admin' ? '👑' : ($du['role'] === 'coparent' ? '🔒' : '') ?> <?= htmlspecialchars($du['role']) ?></td>
                <td><?= ['self' => 'Lui-même', 'family_admin' => 'Admin famille', 'system_admin' => 'Admin système'][$du['deleted_by']] ?? htmlspecialchars($du['deleted_by']) ?></td>
                <td><?= htmlspecialchars(substr($du['deleted_at'], 0, 16)) ?></td>
                <td>
                    <?php if ($du['purged_at']): ?>
                        <span class="badge badge-danger" title="Purgé le <?= htmlspecialchars($du['purged_at']) ?>">🗑️ Purgé</span>
                    <?php else: ?>
                        <span class="badge badge-ok">📦 Données conservées</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if (!$du['purged_at']): ?>
                        <form method="POST" action="<?= BASE_URL ?>/admin/deleted-users/<?= $du['id'] ?>/purge"
                              onsubmit="return confirm('Purger définitivement les données de <?= htmlspecialchars(addslashes($du['name'])) ?> ? Cette action est irréversible : documents, événements, journal parental, photos et liens seront supprimés, fichiers compris.')">
                            <?= \App\Core\Csrf::field() ?>
                            <button class="btn btn-danger btn-sm">Purger les données</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>

        <?php elseif ($tab === 'notifications'): ?>
        <h2>Notification système</h2>
        <p style="color:var(--text-muted);font-size:.85rem;margin-bottom:1rem">
            Envoie une notification (push + dans l'application) à <strong>tous les utilisateurs</strong> ou à une sélection
            précise, toutes familles confondues. Le clic sur la notification (depuis la cloche du site ou le push du
            navigateur) ouvre une page dédiée affichant le titre, la date et le contenu détaillé.
        </p>
        <form method="POST" action="<?= BASE_URL ?>/admin/notifications/send" class="card" style="padding:1.25rem;max-width:640px" id="system-notify-form" onsubmit="return prepareSystemNotifyForm()"><?= \App\Core\Csrf::field() ?>
            <div class="form-group">
                <label>Destinataires</label>
                <label class="radio-option">
                    <input type="radio" name="recipients" value="all" checked onchange="toggleSystemNotifyRecipients()">
                    <span>Tous les utilisateurs</span>
                </label>
                <label class="radio-option">
                    <input type="radio" name="recipients" value="specific" onchange="toggleSystemNotifyRecipients()">
                    <span>Utilisateurs spécifiques</span>
                </label>
            </div>
            <div class="form-group" id="system-notify-recipient-picker" style="display:none">
                <label>Rechercher (nom, email ou famille)</label>
                <input type="text" id="system-notify-user-search" placeholder="Tapez pour filtrer…" oninput="filterSystemNotifyUsers(this.value)">
                <div id="system-notify-user-list" style="max-height:220px;overflow-y:auto;border:1px solid var(--border);border-radius:8px;padding:.5rem;margin-top:.5rem">
                    <?php foreach ($users as $u): ?>
                    <label class="radio-option system-notify-user-row" data-search="<?= htmlspecialchars(mb_strtolower($u['name'] . ' ' . $u['email'] . ' ' . $u['family_name'])) ?>">
                        <input type="checkbox" name="user_ids[]" value="<?= (int)$u['id'] ?>">
                        <span><?= htmlspecialchars($u['name']) ?> <small style="color:var(--text-muted)"><?= htmlspecialchars($u['email']) ?> · <?= htmlspecialchars($u['family_name']) ?></small></span>
                    </label>
                    <?php endforeach; ?>
                </div>
                <small id="system-notify-user-count" style="color:var(--text-muted)">0 destinataire(s) sélectionné(s)</small>
            </div>
            <div class="form-group">
                <label>Titre</label>
                <input type="text" name="title" maxlength="150" required>
            </div>
            <div class="form-group">
                <label>Texte court <span style="color:var(--text-muted);font-size:.8rem">(aperçu affiché dans la cloche de notifications et le push)</span></label>
                <input type="text" name="short_text" maxlength="300" required>
            </div>
            <div class="form-group">
                <label>Contenu détaillé</label>
                <div class="post-quill-wrap">
                    <div id="system-notify-quill-editor"></div>
                </div>
                <textarea name="content" id="system-notify-content" style="display:none"></textarea>
            </div>
            <button type="submit" class="btn btn-primary" id="system-notify-submit-btn">Envoyer à tous les utilisateurs</button>
        </form>
        <script>
        (function () {
            var editor;
            document.addEventListener('DOMContentLoaded', function () {
                if (typeof Quill === 'undefined') return;
                editor = new Quill('#system-notify-quill-editor', {
                    theme: 'snow',
                    placeholder: 'Contenu détaillé de la notification…',
                    modules: { toolbar: [['bold', 'italic', 'underline'], [{ list: 'ordered' }, { list: 'bullet' }], ['link'], ['clean']] },
                });
                document.querySelectorAll('#system-notify-user-list input[type=checkbox]').forEach(function (cb) {
                    cb.addEventListener('change', updateSystemNotifyUserCount);
                });
            });

            window.toggleSystemNotifyRecipients = function () {
                var specific = document.querySelector('input[name="recipients"]:checked').value === 'specific';
                document.getElementById('system-notify-recipient-picker').style.display = specific ? 'block' : 'none';
                document.getElementById('system-notify-submit-btn').textContent = specific
                    ? 'Envoyer aux utilisateurs sélectionnés' : 'Envoyer à tous les utilisateurs';
            };

            window.filterSystemNotifyUsers = function (query) {
                query = query.trim().toLowerCase();
                document.querySelectorAll('.system-notify-user-row').forEach(function (row) {
                    row.style.display = row.dataset.search.includes(query) ? 'flex' : 'none';
                });
            };

            window.updateSystemNotifyUserCount = function () {
                var n = document.querySelectorAll('#system-notify-user-list input[type=checkbox]:checked').length;
                document.getElementById('system-notify-user-count').textContent = n + ' destinataire(s) sélectionné(s)';
            };

            window.prepareSystemNotifyForm = function () {
                if (editor) document.getElementById('system-notify-content').value = editor.root.innerHTML;
                if (!editor || editor.getText().trim() === '') {
                    alert('Le contenu détaillé est requis.');
                    return false;
                }
                var specific = document.querySelector('input[name="recipients"]:checked').value === 'specific';
                if (specific) {
                    var n = document.querySelectorAll('#system-notify-user-list input[type=checkbox]:checked').length;
                    if (n === 0) {
                        alert('Sélectionnez au moins un destinataire.');
                        return false;
                    }
                    return confirm('Envoyer cette notification à ' + n + ' utilisateur(s) sélectionné(s) ?');
                }
                return confirm('Envoyer cette notification à tous les utilisateurs de toutes les familles ?');
            };
        })();
        </script>

        <h2 style="margin-top:2rem">Veille informationnelle — Météo-France Vigilance</h2>
        <p style="color:var(--text-muted);font-size:.85rem;margin-bottom:1rem">
            Le bandeau d'alertes (canicule, inondation, alerte climatique, catastrophe industrielle…) repose sur la
            catégorie « familyboard-alert » du blog <a href="https://leblogateur.fr" target="_blank" rel="noopener">leblogateur.fr</a>
            (un article dont le titre commence par <code>[categorie]</code>, ex. <code>[canicule]</code> ou
            <code>[inondation:Bordeaux]</code>, devient une alerte). En complément, renseignez ici une
            clé API Vigilance de Météo-France pour des alertes canicule/météo officielles, précises par
            département. Clé gratuite à créer sur
            <a href="https://portail-api.meteofrance.fr/" target="_blank" rel="noopener">portail-api.meteofrance.fr</a>
            (application « Vigilance », authentification « Clé API »).
        </p>
        <form method="POST" action="<?= BASE_URL ?>/admin/meteofrance-key" class="card" style="padding:1.25rem;max-width:640px"><?= \App\Core\Csrf::field() ?>
            <div class="form-group">
                <label>Clé API Vigilance Météo-France</label>
                <?php if ($meteoFranceApiKey): ?>
                    <input type="text" value="•••••••••••••• <?= htmlspecialchars(substr($meteoFranceApiKey, -4)) ?>" disabled>
                    <input type="hidden" name="keep_existing" value="1">
                    <details style="margin-top:.4rem">
                        <summary style="cursor:pointer;font-size:.8rem;color:var(--text-muted)">Changer ou désactiver la clé</summary>
                        <input type="text" name="api_key" placeholder="Nouvelle clé (laisser vide pour désactiver)" autocomplete="off" style="margin-top:.5rem">
                    </details>
                <?php else: ?>
                    <input type="text" name="api_key" placeholder="Laisser vide pour désactiver ce complément" autocomplete="off">
                <?php endif; ?>
            </div>
            <div style="display:flex;gap:.5rem;align-items:center">
                <button type="submit" class="btn btn-primary btn-sm">Enregistrer</button>
                <?php if ($meteoFranceApiKey): ?>
                <button type="button" class="btn btn-secondary btn-sm" onclick="testMeteoFranceKey()">🔌 Tester la clé</button>
                <?php endif; ?>
            </div>
            <div id="meteofrance-test-result" style="margin-top:.75rem"></div>
        </form>

        <h2 style="margin-top:2rem">Coffre-fort de mots de passe (Vaultwarden)</h2>
        <p style="color:var(--text-muted);font-size:.85rem;margin-bottom:1rem">
            Propose aux membres de famille (jamais aux co-parents) de créer un coffre-fort de mots de passe/2FA
            sur votre instance Vaultwarden auto-hébergée. FamilyBoard se contente de déclencher l'invitation par
            e-mail depuis le panneau admin de Vaultwarden — il ne connaît jamais le mot de passe maître du
            coffre, choisi par chaque membre directement sur Vaultwarden. Nécessite une instance configurée en
            <code>SIGNUPS_ALLOWED=false</code> / <code>INVITATIONS_ALLOWED=true</code>.
        </p>
        <form method="POST" action="<?= BASE_URL ?>/admin/vaultwarden" class="card" style="padding:1.25rem;max-width:640px"><?= \App\Core\Csrf::field() ?>
            <div class="form-group">
                <label>URL de l'instance Vaultwarden</label>
                <input type="url" name="url" value="<?= htmlspecialchars($vaultwardenSettings['url'] ?? '') ?>" placeholder="https://pwd.votredomaine.fr">
            </div>
            <div class="form-group">
                <label>Jeton d'administration (ADMIN_TOKEN)</label>
                <?php if (!empty($vaultwardenSettings['token'])): ?>
                    <input type="text" value="•••••••••••••• <?= htmlspecialchars(substr($vaultwardenSettings['token'], -4)) ?>" disabled>
                    <input type="hidden" name="keep_existing" value="1">
                    <details style="margin-top:.4rem">
                        <summary style="cursor:pointer;font-size:.8rem;color:var(--text-muted)">Changer ou désactiver le jeton</summary>
                        <input type="text" name="admin_token" placeholder="Nouveau jeton (laisser vide pour désactiver)" autocomplete="off" style="margin-top:.5rem">
                    </details>
                <?php else: ?>
                    <input type="text" name="admin_token" placeholder="Laisser vide pour désactiver cette fonctionnalité" autocomplete="off">
                <?php endif; ?>
            </div>
            <div style="display:flex;gap:.5rem;align-items:center">
                <button type="submit" class="btn btn-primary btn-sm">Enregistrer</button>
                <?php if (!empty($vaultwardenSettings['token'])): ?>
                <button type="button" class="btn btn-secondary btn-sm" onclick="testVaultwarden()">🔌 Tester la connexion</button>
                <?php endif; ?>
            </div>
            <div id="vaultwarden-test-result" style="margin-top:.75rem"></div>
        </form>

        <h2 style="margin-top:2rem">Sécurité — Double authentification</h2>
        <p style="color:var(--text-muted);font-size:.85rem;margin-bottom:1rem">
            Si activé, tout utilisateur sans double authentification voit un message l'invitant à
            l'activer (application ou email, à son choix) pendant le délai de grâce ci-dessous ;
            passé ce délai, l'accès à l'application est bloqué jusqu'à activation.
        </p>
        <form method="POST" action="<?= BASE_URL ?>/admin/2fa-policy" class="card" style="padding:1.25rem;max-width:640px"><?= \App\Core\Csrf::field() ?>
            <div class="form-group">
                <label><input type="checkbox" name="require_2fa_all" value="1" <?= $require2faAll ? 'checked' : '' ?>> Exiger la double authentification pour tous les utilisateurs</label>
            </div>
            <div class="form-group">
                <label>Délai de grâce (jours) avant blocage</label>
                <input type="number" name="require_2fa_grace_days" min="0" max="90" value="<?= (int)$require2faGraceDays ?>" style="max-width:120px">
            </div>
            <button type="submit" class="btn btn-primary btn-sm">Enregistrer</button>
        </form>

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
        <form method="POST" action="<?= BASE_URL ?>/admin/ips" class="admin-inline-form"><?= \App\Core\Csrf::field() ?>
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
                    <form method="POST" action="<?= BASE_URL ?>/admin/ips/<?= $ip['id'] ?>/delete"><?= \App\Core\Csrf::field() ?>
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
                <td><strong><?= htmlspecialchars($t['subject']) ?></strong><?php if (($t['source'] ?? 'user') === 'auto'): ?> <span class="badge badge-warn" title="Créé automatiquement suite à une erreur technique">🤖 Auto</span><?php endif; ?></td>
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
        <form method="POST" action="<?= BASE_URL ?>/admin/smtp" class="card" style="padding:1.25rem;max-width:640px"><?= \App\Core\Csrf::field() ?>
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
                    <input type="password" name="smtp_pass" placeholder="<?= !empty($smtp['password']) ? '•••••••• (laisser vide pour conserver)' : '' ?>" autocomplete="new-password">
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

        <?php elseif ($tab === 'email'): ?>
        <h2>Contenu des emails (global)</h2>
        <p style="color:var(--text-muted);font-size:.85rem;margin-bottom:1rem">
            Le style graphique des emails (couleurs, polices, mise en page) est fixe et identique
            au panneau — seuls le sujet et le message peuvent être personnalisés ici, pour l'ensemble des familles.
        </p>
        <?php foreach ($emailContents as $type => $tpl): ?>
        <div class="card" style="padding:1.25rem;max-width:640px;margin-bottom:1rem">
            <h3 style="margin-top:0;display:flex;align-items:center;gap:.5rem">
                <?= htmlspecialchars($tpl['label']) ?>
                <?php if ($tpl['is_custom']): ?><span class="badge-custom">Personnalisé</span><?php endif; ?>
            </h3>
            <div style="margin-bottom:.75rem;font-size:.78rem;color:var(--text-muted)">
                Variables : <?= implode(', ', array_map(fn($v) => '<code>{{' . $v . '}}</code>', \App\Models\EmailContent::variables($type))) ?>
            </div>
            <form method="POST" action="<?= BASE_URL ?>/admin/email-content"><?= \App\Core\Csrf::field() ?>
                <input type="hidden" name="type" value="<?= htmlspecialchars($type) ?>">
                <div class="form-group">
                    <label>Sujet</label>
                    <input type="text" name="subject" value="<?= htmlspecialchars($tpl['subject']) ?>">
                </div>
                <div class="form-group">
                    <label>Message</label>
                    <textarea name="message" rows="5"><?= htmlspecialchars($tpl['message']) ?></textarea>
                </div>
                <div style="display:flex;gap:.5rem;align-items:center">
                    <button type="submit" class="btn btn-primary btn-sm">Enregistrer</button>
                    <a href="<?= BASE_URL ?>/admin/email-content/<?= $type ?>/preview" target="_blank" class="btn btn-secondary btn-sm">👁️ Aperçu</a>
                </div>
            </form>
            <?php if ($tpl['is_custom']): ?>
            <form method="POST" action="<?= BASE_URL ?>/admin/email-content/<?= $type ?>/reset" style="margin-top:.5rem"><?= \App\Core\Csrf::field() ?>
                <button type="submit" class="btn btn-secondary btn-sm">Réinitialiser la valeur par défaut</button>
            </form>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>

        <?php elseif ($tab === 'highlights'): ?>
        <h2>🏢 Mises en avant ABHD</h2>
        <p style="color:var(--text-muted);font-size:.85rem;margin-bottom:1rem">
            Met en avant d'autres services proposés par ABHD (éditeur de FamilyBoard) — jamais
            appelé "publicité" dans l'interface, et strictement réservé à cet usage interne (pas
            de régie tierce). Choisissez où chaque mise en avant apparaît : tableau de bord, pied
            de chaque e-mail, page d'accueil de chaque module, et/ou vue co-parent. Si plusieurs
            sont actives pour un même emplacement, une seule s'affiche à chaque chargement, tirée
            au hasard.
        </p>

        <div class="card" style="padding:1.25rem;max-width:640px;margin-bottom:1.5rem">
            <h3 style="margin-top:0">Ajouter</h3>
            <form method="POST" action="<?= BASE_URL ?>/admin/highlights" enctype="multipart/form-data"><?= \App\Core\Csrf::field() ?>
                <div class="form-group">
                    <label>Titre</label>
                    <input type="text" name="title" required>
                </div>
                <div class="form-group">
                    <label>Description courte (optionnel)</label>
                    <input type="text" name="description" maxlength="300">
                </div>
                <div class="form-group">
                    <label>Lien (http/https)</label>
                    <input type="url" name="url" required placeholder="https://...">
                </div>
                <div class="form-group">
                    <label>Image (optionnel)</label>
                    <input type="file" name="image" accept="image/*">
                </div>
                <div class="form-group">
                    <label>Emplacements</label>
                    <label class="radio-option"><input type="checkbox" name="show_dashboard" checked> Tableau de bord</label>
                    <label class="radio-option"><input type="checkbox" name="show_module_pages" checked> Page d'accueil de chaque module</label>
                    <label class="radio-option"><input type="checkbox" name="show_email" checked> Pied de chaque e-mail</label>
                </div>
                <div class="form-group">
                    <label class="radio-option"><input type="checkbox" name="show_modal"> Fenêtre modale à la première page vue après connexion</label>
                </div>
                <div class="form-group">
                    <label class="radio-option"><input type="checkbox" name="is_active" checked> Active</label>
                </div>
                <button type="submit" class="btn btn-primary btn-sm">Ajouter</button>
            </form>
        </div>

        <?php foreach ($highlights as $h): ?>
        <div class="card" style="padding:1.25rem;max-width:640px;margin-bottom:1rem">
            <h3 style="margin-top:0;display:flex;align-items:center;gap:.5rem">
                <?= htmlspecialchars($h['title']) ?>
                <?php if (!$h['is_active']): ?><span class="badge-custom">Inactive</span><?php endif; ?>
                <span style="margin-left:auto;font-size:.78rem;color:var(--text-muted)">👆 <?= (int)$h['click_count'] ?> clic(s)</span>
            </h3>
            <?php if ($h['image_path']): ?>
                <img src="<?= htmlspecialchars(BASE_URL . $h['image_path']) ?>" alt="" style="max-height:60px;margin-bottom:.5rem">
            <?php endif; ?>
            <form method="POST" action="<?= BASE_URL ?>/admin/highlights/<?= (int)$h['id'] ?>" enctype="multipart/form-data"><?= \App\Core\Csrf::field() ?>
                <div class="form-group">
                    <label>Titre</label>
                    <input type="text" name="title" value="<?= htmlspecialchars($h['title']) ?>" required>
                </div>
                <div class="form-group">
                    <label>Description courte</label>
                    <input type="text" name="description" maxlength="300" value="<?= htmlspecialchars($h['description'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Lien</label>
                    <input type="url" name="url" value="<?= htmlspecialchars($h['url']) ?>" required>
                </div>
                <div class="form-group">
                    <label>Remplacer l'image (optionnel)</label>
                    <input type="file" name="image" accept="image/*">
                </div>
                <div class="form-group">
                    <label>Emplacements</label>
                    <label class="radio-option"><input type="checkbox" name="show_dashboard" <?= $h['show_dashboard'] ? 'checked' : '' ?>> Tableau de bord</label>
                    <label class="radio-option"><input type="checkbox" name="show_module_pages" <?= $h['show_module_pages'] ? 'checked' : '' ?>> Page d'accueil de chaque module</label>
                    <label class="radio-option"><input type="checkbox" name="show_email" <?= $h['show_email'] ? 'checked' : '' ?>> Pied de chaque e-mail</label>
                </div>
                <div class="form-group">
                    <label class="radio-option"><input type="checkbox" name="show_modal" <?= $h['show_modal'] ? 'checked' : '' ?>> Fenêtre modale à la première page vue après connexion</label>
                </div>
                <div class="form-group">
                    <label class="radio-option"><input type="checkbox" name="is_active" <?= $h['is_active'] ? 'checked' : '' ?>> Active</label>
                </div>
                <div style="display:flex;gap:.5rem">
                    <button type="submit" class="btn btn-primary btn-sm">Enregistrer</button>
                </div>
            </form>
            <form method="POST" action="<?= BASE_URL ?>/admin/highlights/<?= (int)$h['id'] ?>/delete" style="margin-top:.5rem" onsubmit="return confirmSubmit(this,'Supprimer cette mise en avant ?')"><?= \App\Core\Csrf::field() ?>
                <button type="submit" class="btn btn-danger btn-sm">Supprimer</button>
            </form>
        </div>
        <?php endforeach; ?>
        <?php if (empty($highlights)): ?>
            <p style="color:var(--text-muted)">Aucune mise en avant pour l'instant.</p>
        <?php endif; ?>

        <?php elseif ($tab === 'links'): ?>
        <h2>🔗 Liens certifiés</h2>
        <p style="color:var(--text-muted);font-size:.85rem;margin-bottom:1rem">
            Ces liens sont ajoutés au portail de liens de <strong>toutes les familles</strong>
            simultanément, avec un badge « ✓ Certifié » — aucune famille ne peut les modifier
            ou les supprimer, seulement les consulter. Publiés immédiatement, sans circuit de
            validation (contrairement aux liens proposés par les membres d'une famille).
        </p>

        <div class="card" style="padding:1.25rem;max-width:640px;margin-bottom:1.5rem">
            <h3 style="margin-top:0">Ajouter</h3>
            <form method="POST" action="<?= BASE_URL ?>/admin/links"><?= \App\Core\Csrf::field() ?>
                <div class="form-group">
                    <label>Adresse du site</label>
                    <input type="url" name="url" required placeholder="https://...">
                </div>
                <div class="form-group">
                    <label>Titre</label>
                    <input type="text" name="title">
                    <small style="color:var(--text-muted)">Laissez vide pour utiliser le titre détecté automatiquement.</small>
                </div>
                <div class="form-group">
                    <label>Description (optionnel)</label>
                    <input type="text" name="description" maxlength="300">
                </div>
                <div class="form-group">
                    <label class="radio-option"><input type="checkbox" name="visible_to_coparent" checked> Visible par un accès co-parent</label>
                </div>
                <button type="submit" class="btn btn-primary btn-sm">Ajouter</button>
            </form>
        </div>

        <?php foreach ($certifiedLinks as $l): ?>
        <div class="card" style="padding:1.25rem;max-width:640px;margin-bottom:1rem">
            <h3 style="margin-top:0;display:flex;align-items:center;gap:.5rem">
                <?= htmlspecialchars($l['title']) ?>
                <span style="margin-left:auto;font-size:.78rem;color:var(--text-muted)">👆 <?= (int)$l['click_count'] ?> clic(s)</span>
            </h3>
            <?php if ($l['image_path']): ?>
                <img src="<?= htmlspecialchars(BASE_URL . $l['image_path']) ?>" alt="" style="max-height:60px;margin-bottom:.5rem">
            <?php endif; ?>
            <form method="POST" action="<?= BASE_URL ?>/admin/links/<?= (int)$l['id'] ?>"><?= \App\Core\Csrf::field() ?>
                <div class="form-group">
                    <label>Titre</label>
                    <input type="text" name="title" value="<?= htmlspecialchars($l['title']) ?>" required>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <input type="text" name="description" maxlength="300" value="<?= htmlspecialchars($l['description'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Lien</label>
                    <input type="url" name="url" value="<?= htmlspecialchars($l['url']) ?>" required>
                </div>
                <div class="form-group">
                    <label class="radio-option"><input type="checkbox" name="visible_to_coparent" <?= $l['visible_to_coparent'] ? 'checked' : '' ?>> Visible par un accès co-parent</label>
                </div>
                <button type="submit" class="btn btn-primary btn-sm">Enregistrer</button>
            </form>
            <div style="display:flex;gap:.5rem;margin-top:.5rem">
                <?php if (empty($l['image_path'])): ?>
                <form method="POST" action="<?= BASE_URL ?>/admin/links/<?= (int)$l['id'] ?>/refresh-preview"><?= \App\Core\Csrf::field() ?>
                    <button type="submit" class="btn btn-secondary btn-sm" title="Réessayer de récupérer l'image du site">🔄 Réessayer l'image</button>
                </form>
                <?php endif; ?>
                <form method="POST" action="<?= BASE_URL ?>/admin/links/<?= (int)$l['id'] ?>/delete" onsubmit="return confirmSubmit(this,'Supprimer ce lien certifié pour toutes les familles ?')"><?= \App\Core\Csrf::field() ?>
                    <button type="submit" class="btn btn-danger btn-sm">Supprimer</button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($certifiedLinks)): ?>
            <p style="color:var(--text-muted)">Aucun lien certifié pour l'instant.</p>
        <?php endif; ?>

        <?php elseif ($tab === 'legal'): ?>
        <h2>Contenu légal</h2>
        <p style="color:var(--text-muted);font-size:.85rem;margin-bottom:1rem">
            Politique de confidentialité et conditions générales d'utilisation, affichées sur
            <code><?= BASE_URL ?>/confidentialite</code> et <code><?= BASE_URL ?>/cgu</code> —
            accessibles sans compte, et liées depuis le formulaire d'inscription. Un texte par
            défaut est fourni ; personnalisez-le pour refléter votre situation réelle (identité
            du responsable de traitement, éventuels sous-traitants supplémentaires…). Texte brut
            uniquement (pas de HTML) : une ligne vide sépare deux paragraphes, une ligne
            commençant par « 1. », « 2. »… devient un titre de section.
        </p>
        <div class="card" style="padding:1.25rem;max-width:760px;margin-bottom:1rem">
            <h3 style="margin-top:0;display:flex;align-items:center;gap:.5rem">
                Politique de confidentialité
                <?php if ($legalPrivacyIsCustom): ?><span class="badge-custom">Personnalisé</span><?php endif; ?>
            </h3>
            <form method="POST" action="<?= BASE_URL ?>/admin/legal"><?= \App\Core\Csrf::field() ?>
                <input type="hidden" name="type" value="privacy">
                <div class="form-group">
                    <textarea name="content" rows="16" style="font-family:monospace;font-size:.82rem"><?= htmlspecialchars($legalPrivacy) ?></textarea>
                </div>
                <div style="display:flex;gap:.5rem;align-items:center">
                    <button type="submit" class="btn btn-primary btn-sm">Enregistrer</button>
                    <a href="<?= BASE_URL ?>/confidentialite" target="_blank" class="btn btn-secondary btn-sm">👁️ Aperçu</a>
                </div>
            </form>
            <?php if ($legalPrivacyIsCustom): ?>
            <form method="POST" action="<?= BASE_URL ?>/admin/legal/privacy/reset" style="margin-top:.5rem"><?= \App\Core\Csrf::field() ?>
                <button type="submit" class="btn btn-secondary btn-sm">Réinitialiser la valeur par défaut</button>
            </form>
            <?php endif; ?>
        </div>
        <div class="card" style="padding:1.25rem;max-width:760px;margin-bottom:1rem">
            <h3 style="margin-top:0;display:flex;align-items:center;gap:.5rem">
                Conditions générales d'utilisation
                <?php if ($legalTermsIsCustom): ?><span class="badge-custom">Personnalisé</span><?php endif; ?>
            </h3>
            <form method="POST" action="<?= BASE_URL ?>/admin/legal"><?= \App\Core\Csrf::field() ?>
                <input type="hidden" name="type" value="terms">
                <div class="form-group">
                    <textarea name="content" rows="16" style="font-family:monospace;font-size:.82rem"><?= htmlspecialchars($legalTerms) ?></textarea>
                </div>
                <div style="display:flex;gap:.5rem;align-items:center">
                    <button type="submit" class="btn btn-primary btn-sm">Enregistrer</button>
                    <a href="<?= BASE_URL ?>/cgu" target="_blank" class="btn btn-secondary btn-sm">👁️ Aperçu</a>
                </div>
            </form>
            <?php if ($legalTermsIsCustom): ?>
            <form method="POST" action="<?= BASE_URL ?>/admin/legal/terms/reset" style="margin-top:.5rem"><?= \App\Core\Csrf::field() ?>
                <button type="submit" class="btn btn-secondary btn-sm">Réinitialiser la valeur par défaut</button>
            </form>
            <?php endif; ?>
        </div>

        <?php endif; ?>
    </main>
</div>
<script>const BASE_URL = <?= json_encode(BASE_URL) ?>;</script>
<script src="<?= ASSETS_URL ?>/js/quill.min.js?v=<?= APP_VERSION ?>"></script>
<script src="<?= ASSETS_URL ?>/js/admin.js?v=<?= APP_VERSION ?>"></script>
</body>
</html>
