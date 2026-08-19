<?php
namespace App\Controllers;

use App\Core\Database;
use App\Core\Mail;
use App\Core\Session;
use App\Models\AbhdHighlight;
use App\Models\AccountDeletion;
use App\Models\PortalLink;
use App\Models\AppSetting;
use App\Models\EmailContent;
use App\Models\LegalContent;
use App\Core\OfficialAlertFeed;
use App\Models\ImpersonationLog;
use App\Models\Notification;
use App\Models\SmtpSettings;
use App\Models\SupportTicket;
use App\Models\User;
use App\Core\EmailLayout;
use App\Core\Vaultwarden;
use App\Models\VaultwardenSettings;
use App\Models\DataExport;

class AdminController extends BaseController
{
    // ── Auth ────────────────────────────────────────────────────

    private function requireSuperAdmin(): void
    {
        if (!($_SESSION['admin_logged_in'] ?? false)) {
            header('Location: ' . BASE_URL . '/admin/login');
            exit;
        }
        // Les appels fetch() JS envoient déjà X-Requested-With (vérifié par isAjax()), ce qui
        // bloque les soumissions de &lt;form&gt; cross-origin classiques ; le jeton CSRF protège
        // en plus les formulaires HTML POST natifs (impersonation, blocage, IPs…).
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$this->isAjax() && !\App\Core\Csrf::verify()) {
            http_response_code(403);
            echo 'Jeton de sécurité invalide ou expiré. Rechargez la page et réessayez.';
            exit;
        }
    }

    public function showLogin(array $params): void
    {
        if ($_SESSION['admin_logged_in'] ?? false) {
            header('Location: ' . BASE_URL . '/admin');
            exit;
        }
        require BASE_PATH . '/templates/admin/login.php';
    }

    public function login(array $params): void
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        if (\App\Models\LoginAttempt::isLocked('admin', $ip)) {
            $_SESSION['admin_error'] = 'Trop de tentatives. Réessayez dans ' . \App\Models\LoginAttempt::minutesUntilUnlock() . ' minutes.';
            header('Location: ' . BASE_URL . '/admin/login');
            exit;
        }

        $user = trim($_POST['username'] ?? '');
        $pass = $_POST['password'] ?? '';

        // Each credential checked independently against db value or config fallback
        $storedUser = AppSetting::get('admin_username');
        $storedHash = AppSetting::get('admin_password_hash');

        $expectedUser = $storedUser ?? ADMIN_USER;
        $validUser    = hash_equals($expectedUser, $user);
        $validPass    = $storedHash !== null
            ? password_verify($pass, $storedHash)
            : hash_equals(ADMIN_PASS, $pass);
        $ok = $validUser && $validPass;

        if ($ok) {
            \App\Models\LoginAttempt::clear('admin', $ip);
            session_regenerate_id(true); // anti-fixation, cf. App\Core\Session::login()
            $_SESSION['admin_logged_in'] = true;
            header('Location: ' . BASE_URL . '/admin');
        } else {
            \App\Models\LoginAttempt::record('admin', $ip);
            $_SESSION['admin_error'] = 'Identifiants incorrects.';
            header('Location: ' . BASE_URL . '/admin/login');
        }
        exit;
    }

    // ── Profile ──────────────────────────────────────────────────

    public function showProfile(array $params): void
    {
        $this->requireSuperAdmin();
        $adminUsername = AppSetting::get('admin_username') ?? ADMIN_USER;
        $adminEmail    = AppSetting::get('admin_email') ?? '';
        require BASE_PATH . '/templates/admin/profile.php';
    }

    public function updateProfile(array $params): void
    {
        $this->requireSuperAdmin();

        $currentPass = $_POST['current_password'] ?? '';
        $newUser     = trim($_POST['username'] ?? '');
        $newEmail    = trim($_POST['email'] ?? '');
        $newPass     = $_POST['new_password'] ?? '';
        $confirmPass = $_POST['confirm_password'] ?? '';

        // Verify current password
        $storedUser = AppSetting::get('admin_username');
        $storedHash = AppSetting::get('admin_password_hash');
        $currentUser = $storedUser ?? ADMIN_USER;

        $validCurrent = $storedHash !== null
            ? password_verify($currentPass, $storedHash)
            : hash_equals(ADMIN_PASS, $currentPass);

        if (!$validCurrent) {
            $this->redirect('/admin/profile?error=wrong_password');
            return;
        }

        if ($newUser !== '') {
            AppSetting::set('admin_username', $newUser);
        }

        if ($newEmail !== '' && !filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            $this->redirect('/admin/profile?error=invalid_email');
            return;
        }
        AppSetting::set('admin_email', $newEmail);

        if ($newPass !== '') {
            if ($newPass !== $confirmPass) {
                $this->redirect('/admin/profile?error=password_mismatch');
                return;
            }
            if (strlen($newPass) < 8) {
                $this->redirect('/admin/profile?error=password_short');
                return;
            }
            AppSetting::set('admin_password_hash', password_hash($newPass, PASSWORD_BCRYPT));
        }

        $this->redirect('/admin/profile?msg=saved');
    }

    public function logout(array $params): void
    {
        unset($_SESSION['admin_logged_in']);
        header('Location: ' . BASE_URL . '/admin/login');
        exit;
    }

    // ── Dashboard ────────────────────────────────────────────────

    public function index(array $params): void
    {
        $this->requireSuperAdmin();
        $tab = $_GET['tab'] ?? 'dashboard';

        $stats = [
            'families'    => (int)(Database::fetch('SELECT COUNT(*) c FROM families')['c'] ?? 0),
            'users'       => (int)(Database::fetch('SELECT COUNT(*) c FROM users')['c'] ?? 0),
            'blocked'     => (int)(Database::fetch("SELECT COUNT(*) c FROM users WHERE blocked_at IS NOT NULL")['c'] ?? 0),
            'tickets'     => SupportTicket::countOpen(),
        ];
        $families     = Database::fetchAll('SELECT f.*, COUNT(u.id) as member_count FROM families f LEFT JOIN users u ON u.family_id=f.id GROUP BY f.id ORDER BY f.created_at DESC');
        // Colonnes explicites (jamais SELECT u.*) : évite qu'un futur ajout de colonne sensible
        // (mot de passe, secret TOTP...) ne se retrouve exposé sans y penser dans ce tableau.
        $users        = Database::fetchAll(
            'SELECT u.id, u.family_id, u.name, u.email, u.role, u.blocked_at, u.blocked_reason, u.created_at, f.name as family_name
             FROM users u JOIN families f ON f.id=u.family_id ORDER BY u.blocked_at IS NULL DESC, u.created_at DESC'
        );
        $blockedIps   = Database::fetchAll('SELECT * FROM blocked_ips ORDER BY created_at DESC');
        $tickets      = SupportTicket::getAll();
        $smtp         = SmtpSettings::get();
        $emailContents = EmailContent::getAll();
        $highlights = AbhdHighlight::getAll();
        $certifiedLinks = PortalLink::getCertified();
        $legalPrivacy = LegalContent::get('privacy');
        $legalTerms   = LegalContent::get('terms');
        $legalPrivacyIsCustom = LegalContent::isCustom('privacy');
        $legalTermsIsCustom   = LegalContent::isCustom('terms');
        $deletedUsers = AccountDeletion::getDeletedUsers();
        $meteoFranceApiKey = AppSetting::get('meteofrance_api_key') ?? '';
        $vaultwardenSettings = VaultwardenSettings::get();
        $require2faAll     = (bool)(int)(AppSetting::get('require_2fa_all') ?? '0');
        $require2faGraceDays = (int)(AppSetting::get('require_2fa_grace_days') ?? '7');

        require BASE_PATH . '/templates/admin/index.php';
    }

    // ── Politique de sécurité globale : 2FA obligatoire ────────────

    public function updateTwoFactorPolicy(array $params): void
    {
        $this->requireSuperAdmin();
        AppSetting::set('require_2fa_all', !empty($_POST['require_2fa_all']) ? '1' : '0');
        $days = max(0, (int)($_POST['require_2fa_grace_days'] ?? 7));
        AppSetting::set('require_2fa_grace_days', (string)$days);
        $this->redirect('/admin?tab=notifications&msg=2fa_policy_saved');
    }

    // ── Contenu des emails (global, système) ──────────────────────

    public function saveEmailContent(array $params): void
    {
        $this->requireSuperAdmin();
        $type    = $_POST['type'] ?? '';
        $subject = trim($_POST['subject'] ?? '');
        $message = trim($_POST['message'] ?? '');
        if ($type && $subject && $message) {
            EmailContent::save($type, $subject, $message);
        }
        $this->redirect('/admin?tab=email&msg=email_saved');
    }

    public function resetEmailContent(array $params): void
    {
        $this->requireSuperAdmin();
        $type = $params['type'] ?? '';
        if ($type) EmailContent::reset($type);
        $this->redirect('/admin?tab=email&msg=email_saved');
    }

    /**
     * Preview the fixed email design with sample data, so the admin can see
     * the graphical style without being able to edit it here.
     */
    public function previewEmailContent(array $params): void
    {
        $this->requireSuperAdmin();
        $type = $params['type'] ?? '';
        $sampleVars = [
            'sender_name' => 'Camille', 'family_name' => 'Dupont', 'user_name' => 'exemple@mail.com',
            'child_list' => 'Léo, Nina', 'event_title' => 'Anniversaire de Léo',
            'event_date' => 'demain à 15h00', 'event_description' => 'Ne pas oublier le gâteau !',
            'task_name' => 'Ranger le garage', 'list_name' => 'Courses de la semaine',
            'task_created' => 'lundi 12 mai', 'type_label' => 'prélèvement', 'title' => 'Abonnement internet',
            'amount' => '39,99 €', 'due_date' => '5 août 2026', 'event_count' => '3',
            'author_name' => 'Camille', 'content' => 'On part en week-end samedi, qui est partant ?',
        ];
        $rendered = EmailContent::render($type ?: 'invitation', $sampleVars);
        echo EmailLayout::render($rendered['subject'], $rendered['message_html'], [
            'label' => 'Exemple de bouton',
            'url'   => '#',
        ]);
    }

    // ── SMTP (global, système) ────────────────────────────────────

    public function updateSmtp(array $params): void
    {
        $this->requireSuperAdmin();
        // Le mot de passe n'est jamais réaffiché dans le formulaire (il ne doit pas apparaître en
        // clair dans le code source de la page) — champ vide = conserver le mot de passe actuel.
        $password = $_POST['smtp_pass'] ?? '';
        if ($password === '') {
            $password = SmtpSettings::get()['password'] ?? '';
        }
        SmtpSettings::save([
            'host'       => $_POST['smtp_host'] ?? '',
            'port'       => (int)($_POST['smtp_port'] ?? 587),
            'username'   => $_POST['smtp_user'] ?? '',
            'password'   => $password,
            'from_email' => $_POST['smtp_from_email'] ?? '',
            'from_name'  => $_POST['smtp_from_name'] ?? '',
            'encryption' => $_POST['smtp_encryption'] ?? 'tls',
        ]);
        $this->redirect('/admin?tab=smtp&msg=smtp_saved');
    }

    public function testSmtp(array $params): void
    {
        $this->requireSuperAdmin();
        $this->json(function () {
            $settings = SmtpSettings::get();
            if (!$settings) {
                return ['ok' => false, 'error' => 'Aucune configuration SMTP enregistrée.', 'steps' => []];
            }
            return Mail::testConnection($settings);
        });
    }

    public function sendTestEmail(array $params): void
    {
        $this->requireSuperAdmin();
        $this->json(function () {
            $settings = SmtpSettings::get();
            if (!$settings) {
                return ['ok' => false, 'error' => 'Aucune configuration SMTP enregistrée.', 'steps' => []];
            }
            $to = trim($this->jsonInput()['email'] ?? '');
            if (!$to || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
                return ['ok' => false, 'error' => 'Adresse email de test invalide.', 'steps' => []];
            }
            return Mail::sendTest($settings, $to, $to);
        });
    }

    // ── User management ──────────────────────────────────────────

    public function blockUser(array $params): void
    {
        $this->requireSuperAdmin();
        $id     = (int)$params['id'];
        $reason = trim($_POST['reason'] ?? '');
        Database::execute('UPDATE users SET blocked_at=NOW(), blocked_reason=? WHERE id=?', [$reason ?: null, $id]);
        // Destroy any active sessions for this user is not straightforward without session table,
        // but we check blocked_at on every requireAuth call.
        $this->redirect('/admin?tab=users&msg=blocked');
    }

    public function unblockUser(array $params): void
    {
        $this->requireSuperAdmin();
        $id = (int)$params['id'];
        Database::execute('UPDATE users SET blocked_at=NULL, blocked_reason=NULL WHERE id=?', [$id]);
        $this->redirect('/admin?tab=users&msg=unblocked');
    }

    /**
     * Suppression d'un compte membre ou co-parent par l'administrateur système, avec motif
     * obligatoire envoyé par e-mail au titulaire du compte avant suppression, accompagné d'une
     * copie de ses données (mêmes données que "Télécharger mes données" en Paramètres) — pour
     * qu'il en garde une trace même si "Tout supprimer" est coché (purge immédiate des données
     * normalement conservées, ex. former_user_id, plutôt que le comportement par défaut de
     * AccountDeletion::deleteUser(), qui les préserve sans suppression en cascade).
     * Volontairement limité aux rôles membre/co-parent : supprimer un compte administrateur de
     * famille nécessite de transférer son rôle ou de supprimer toute la famille (choix qui
     * appartient à cette famille, pas à un administrateur système agissant seul).
     */
    public function deleteUserAccount(array $params): void
    {
        $this->requireSuperAdmin();
        $id = (int)$params['id'];
        $reason = trim($_POST['reason'] ?? '');
        $purgeAll = !empty($_POST['purge_all']);
        $target = User::findById($id);

        if (!$target || $reason === '') {
            $this->redirect('/admin?tab=users&msg=delete_failed');
            return;
        }
        if ($target['role'] === 'admin') {
            $this->redirect('/admin?tab=users&msg=delete_admin_blocked');
            return;
        }

        $attachments = [];
        try {
            $zipPath = DataExport::build((int)$target['id'], (int)$target['family_id'], false);
            $attachments[] = [
                'filename' => 'mes-donnees-' . date('Y-m-d') . '.zip',
                'content'  => file_get_contents($zipPath),
                'mime'     => 'application/zip',
            ];
            @unlink($zipPath);
        } catch (\Throwable $e) {
            error_log('Account deletion data export error: ' . $e->getMessage());
        }

        try {
            $rendered = EmailContent::render('account_deleted', [
                'user_name' => $target['name'],
                'reason'    => $reason,
            ]);
            $html = EmailLayout::render($rendered['subject'], $rendered['message_html']);
            Mail::send((int)$target['family_id'], $target['email'], $target['name'], $rendered['subject'], $html, 'account_deleted', null, $attachments);
        } catch (\Throwable $e) {
            error_log('Account deletion email error: ' . $e->getMessage());
        }

        if ($target['role'] === 'coparent') {
            AccountDeletion::deleteCoparent($id, 'system_admin');
        } else {
            AccountDeletion::deleteUser($id, (int)$target['family_id'], 'system_admin');
        }

        if ($purgeAll) {
            $deletedRow = Database::fetch('SELECT id FROM deleted_users WHERE original_user_id=? ORDER BY id DESC LIMIT 1', [$id]);
            if ($deletedRow) AccountDeletion::purgeDeletedUserData((int)$deletedRow['id']);
        }

        $this->redirect('/admin?tab=users&msg=user_deleted');
    }

    /**
     * Purge définitive des données conservées d'un compte supprimé (membre classique ou
     * co-parent, quel que soit qui a initié la suppression) — action irréversible.
     */
    public function purgeDeletedUser(array $params): void
    {
        $this->requireSuperAdmin();
        AccountDeletion::purgeDeletedUserData((int)$params['id']);
        $this->redirect('/admin?tab=deleted-accounts&msg=purged');
    }

    /**
     * Se connecte temporairement en tant qu'un membre de famille, pour du support.
     * Ne passe jamais par RememberMe (aucun cookie persistant émis pour le compte
     * ciblé) et reste journalisé dans impersonation_log. La session admin système
     * ($_SESSION['admin_logged_in']) est indépendante de la session utilisateur
     * et n'est jamais touchée ici : l'admin reste connecté au panneau /admin
     * pendant toute la durée de l'impersonation.
     */
    public function impersonate(array $params): void
    {
        $this->requireSuperAdmin();
        $id     = (int)$params['id'];
        $target = User::findById($id);

        if (!$target) { $this->redirect('/admin?tab=users&error=not_found'); return; }
        if (!empty($target['blocked_at'])) { $this->redirect('/admin?tab=users&error=blocked_user'); return; }

        // Si l'admin naviguait déjà avec son propre compte membre, on le garde de
        // côté pour pouvoir y revenir plutôt que de simplement déconnecter au retour.
        if (empty($_SESSION['impersonation'])) {
            $_SESSION['impersonation'] = ['original_user_id' => Session::userId()];
        }

        $adminUsername = AppSetting::get('admin_username') ?? ADMIN_USER;
        $logId = ImpersonationLog::create($id, (int)$target['family_id'], $adminUsername, $_SERVER['REMOTE_ADDR'] ?? null);
        $_SESSION['impersonation']['log_id'] = $logId;

        Session::login($target);
        header('Location: ' . BASE_URL . '/');
        exit;
    }

    public function stopImpersonating(array $params): void
    {
        $this->requireSuperAdmin();
        $imp = $_SESSION['impersonation'] ?? null;
        unset($_SESSION['impersonation']);

        if ($imp) {
            if (!empty($imp['log_id'])) ImpersonationLog::end((int)$imp['log_id']);
            if (!empty($imp['original_user_id'])) {
                $original = User::findById((int)$imp['original_user_id']);
                if ($original) {
                    Session::login($original);
                    $this->redirect('/');
                    return;
                }
            }
        }

        Session::delete('user_id');
        Session::delete('family_id');
        Session::delete('user');
        $this->redirect('/admin?tab=users');
    }

    // ── IP management ────────────────────────────────────────────

    public function addIp(array $params): void
    {
        $this->requireSuperAdmin();
        $ip     = trim($_POST['ip'] ?? '');
        $reason = trim($_POST['reason'] ?? '');
        if ($ip) {
            try {
                Database::insert('INSERT INTO blocked_ips (ip, reason) VALUES (?,?) ON DUPLICATE KEY UPDATE reason=VALUES(reason)', [$ip, $reason ?: null]);
            } catch (\Throwable) {}
        }
        $this->redirect('/admin?tab=ips');
    }

    public function deleteIp(array $params): void
    {
        $this->requireSuperAdmin();
        $id = (int)$params['id'];
        Database::execute('DELETE FROM blocked_ips WHERE id=?', [$id]);
        $this->redirect('/admin?tab=ips');
    }

    // ── Support tickets ──────────────────────────────────────────

    public function viewTicket(array $params): void
    {
        $this->requireSuperAdmin();
        $id      = (int)$params['id'];
        $ticket  = SupportTicket::getById($id);
        if (!$ticket) { $this->redirect('/admin?tab=tickets'); return; }
        $messages = SupportTicket::getMessages($id);
        require BASE_PATH . '/templates/admin/ticket.php';
    }

    public function replyTicket(array $params): void
    {
        $this->requireSuperAdmin();
        $id      = (int)$params['id'];
        $message = trim($_POST['message'] ?? '');
        if ($message) {
            SupportTicket::addMessage($id, null, true, $message);
            SupportTicket::setStatus($id, 'in_progress');
            // Send push to ticket owner if subscribed
            $ticket = SupportTicket::getById($id);
        }
        $this->redirect('/admin/tickets/' . $id);
    }

    public function closeTicket(array $params): void
    {
        $this->requireSuperAdmin();
        $id = (int)$params['id'];
        SupportTicket::setStatus($id, 'closed');
        $this->redirect('/admin/tickets/' . $id);
    }

    public function reopenTicket(array $params): void
    {
        $this->requireSuperAdmin();
        $id = (int)$params['id'];
        SupportTicket::setStatus($id, 'open');
        $this->redirect('/admin/tickets/' . $id);
    }

    // ── Notifications système (à tous les utilisateurs, ou ciblée) ─

    public function sendSystemNotification(array $params): void
    {
        $this->requireSuperAdmin();
        $title      = trim($_POST['title'] ?? '');
        $shortText  = trim($_POST['short_text'] ?? '');
        $contentHtml = $this->sanitizeHtml(trim($_POST['content'] ?? ''));
        $contentIsEmpty = trim(strip_tags($contentHtml)) === '';
        $recipients = ($_POST['recipients'] ?? 'all') === 'specific' ? 'specific' : 'all';

        if ($title && $shortText && !$contentIsEmpty && mb_strlen($title) <= 150 && mb_strlen($shortText) <= 300) {
            if ($recipients === 'specific') {
                $userIds = array_map('intval', (array)($_POST['user_ids'] ?? []));
                Notification::sendToUsers($userIds, $title, $shortText, $contentHtml);
            } else {
                Notification::broadcastToAll($title, $shortText, $contentHtml);
            }
        }
        $this->redirect('/admin?tab=notifications&msg=notification_sent');
    }

    // ── Veille informationnelle : clé API Météo-France Vigilance (optionnelle) ────

    public function updateMeteoFranceKey(array $params): void
    {
        $this->requireSuperAdmin();
        $newKey = trim($_POST['api_key'] ?? '');
        // Le champ vide + keep_existing=1 signifie "le panneau de modification n'a pas été
        // ouvert", pas "désactiver la clé" — sinon recharger la page suffirait à l'effacer.
        if ($newKey === '' && !empty($_POST['keep_existing'])) {
            $this->redirect('/admin?tab=notifications&msg=meteofrance_saved');
            return;
        }
        AppSetting::set('meteofrance_api_key', $newKey);
        $this->redirect('/admin?tab=notifications&msg=meteofrance_saved');
    }

    public function testMeteoFranceKey(array $params): void
    {
        $this->requireSuperAdmin();
        $this->json(function () {
            $apiKey = AppSetting::get('meteofrance_api_key');
            if (!$apiKey) {
                return ['ok' => false, 'error' => 'Aucune clé API enregistrée.'];
            }
            $data = OfficialAlertFeed::fetchVigilanceData($apiKey);
            if ($data === null) {
                return ['ok' => false, 'error' => "Échec de l'appel à l'API Vigilance — clé invalide, expirée, ou service injoignable."];
            }
            return ['ok' => true];
        });
    }

    // ── Vaultwarden (coffre-fort de mots de passe, réservé aux membres non co-parent) ──

    public function updateVaultwardenSettings(array $params): void
    {
        $this->requireSuperAdmin();
        $url = trim($_POST['url'] ?? '');
        $newToken = trim($_POST['admin_token'] ?? '');
        // Comme pour la clé Météo-France : champ jeton vide + keep_existing=1 veut dire "panneau
        // non ouvert", pas "effacer le jeton" — sinon recharger la page suffirait à le vider.
        if ($newToken === '' && !empty($_POST['keep_existing'])) {
            $existing = VaultwardenSettings::get();
            $newToken = $existing['token'] ?? '';
        }
        if ($url && $newToken) {
            VaultwardenSettings::save($url, $newToken);
        } else {
            AppSetting::set('vaultwarden_url', '');
            AppSetting::set('vaultwarden_admin_token', '');
        }
        $this->redirect('/admin?tab=notifications&msg=vaultwarden_saved');
    }

    public function testVaultwardenConnection(array $params): void
    {
        $this->requireSuperAdmin();
        $this->json(fn() => Vaultwarden::testConnection());
    }

    // ── Mises en avant ABHD (jamais "publicité" dans le code/l'UI) ─────────────

    public function createHighlight(array $params): void
    {
        $this->requireSuperAdmin();
        $title = trim($_POST['title'] ?? '');
        $url = trim($_POST['url'] ?? '');
        if (!$title || !filter_var($url, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $url)) {
            $this->redirect('/admin?tab=highlights&msg=highlight_invalid');
            return;
        }
        $image = $this->uploadImage('image');
        AbhdHighlight::create([
            'title'             => $title,
            'description'       => trim($_POST['description'] ?? ''),
            'url'               => $url,
            'image_path'        => $image,
            'show_dashboard'    => !empty($_POST['show_dashboard']),
            'show_module_pages' => !empty($_POST['show_module_pages']),
            'show_email'        => !empty($_POST['show_email']),
            'show_coparent'     => !empty($_POST['show_coparent']),
            'show_modal'        => !empty($_POST['show_modal']),
            'is_active'         => !empty($_POST['is_active']),
        ]);
        $this->redirect('/admin?tab=highlights&msg=highlight_saved');
    }

    public function updateHighlight(array $params): void
    {
        $this->requireSuperAdmin();
        $highlight = AbhdHighlight::getById((int)($params['id'] ?? 0));
        if (!$highlight) { $this->redirect('/admin?tab=highlights'); return; }

        $title = trim($_POST['title'] ?? '');
        $url = trim($_POST['url'] ?? '');
        if (!$title || !filter_var($url, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $url)) {
            $this->redirect('/admin?tab=highlights&msg=highlight_invalid');
            return;
        }
        $image = $this->uploadImage('image');
        AbhdHighlight::update($highlight['id'], [
            'title'             => $title,
            'description'       => trim($_POST['description'] ?? ''),
            'url'               => $url,
            'image_path'        => $image,
            'show_dashboard'    => !empty($_POST['show_dashboard']),
            'show_module_pages' => !empty($_POST['show_module_pages']),
            'show_email'        => !empty($_POST['show_email']),
            'show_coparent'     => !empty($_POST['show_coparent']),
            'show_modal'        => !empty($_POST['show_modal']),
            'is_active'         => !empty($_POST['is_active']),
        ]);
        if ($image && $highlight['image_path']) @unlink(BASE_PATH . $highlight['image_path']);
        $this->redirect('/admin?tab=highlights&msg=highlight_saved');
    }

    public function deleteHighlight(array $params): void
    {
        $this->requireSuperAdmin();
        $highlight = AbhdHighlight::getById((int)($params['id'] ?? 0));
        if ($highlight) {
            if ($highlight['image_path']) @unlink(BASE_PATH . $highlight['image_path']);
            AbhdHighlight::delete($highlight['id']);
        }
        $this->redirect('/admin?tab=highlights&msg=highlight_deleted');
    }

    // ── Liens certifiés (portail de liens de TOUTES les familles) ──────────────

    public function createCertifiedLink(array $params): void
    {
        $this->requireSuperAdmin();
        $title = trim($_POST['title'] ?? '');
        $url = trim($_POST['url'] ?? '');
        if (!$url) {
            $this->redirect('/admin?tab=links&msg=link_invalid');
            return;
        }

        $preview = \App\Core\LinkPreview::fetch($url);
        if (!$preview['ok']) {
            $this->redirect('/admin?tab=links&msg=link_unreachable');
            return;
        }
        if (!$title) $title = $preview['title'];

        PortalLink::createCertified([
            'title'               => $title,
            'url'                 => $url,
            'description'         => trim($_POST['description'] ?? ''),
            'image_path'          => $preview['image_path'],
            'visible_to_coparent' => !empty($_POST['visible_to_coparent']),
        ]);
        $this->redirect('/admin?tab=links&msg=link_saved');
    }

    public function updateCertifiedLink(array $params): void
    {
        $this->requireSuperAdmin();
        $link = PortalLink::getById((int)($params['id'] ?? 0));
        if (!$link || empty($link['certified'])) { $this->redirect('/admin?tab=links'); return; }

        $title = trim($_POST['title'] ?? '');
        $url = trim($_POST['url'] ?? '');
        if (!$title || !$url) {
            $this->redirect('/admin?tab=links&msg=link_invalid');
            return;
        }
        PortalLink::updateCertified($link['id'], [
            'title'               => $title,
            'url'                 => $url,
            'description'         => trim($_POST['description'] ?? ''),
            'visible_to_coparent' => !empty($_POST['visible_to_coparent']),
        ]);
        $this->redirect('/admin?tab=links&msg=link_saved');
    }

    public function deleteCertifiedLink(array $params): void
    {
        $this->requireSuperAdmin();
        $link = PortalLink::getById((int)($params['id'] ?? 0));
        if ($link && !empty($link['certified'])) {
            if ($link['image_path']) @unlink(BASE_PATH . $link['image_path']);
            PortalLink::delete($link['id']);
        }
        $this->redirect('/admin?tab=links&msg=link_deleted');
    }

    /** Relance la vérification/aperçu (titre + image) d'un lien certifié déjà ajouté — utile
     *  pour les liens créés avant l'amélioration de la détection d'image. */
    public function refreshCertifiedLinkPreview(array $params): void
    {
        $this->requireSuperAdmin();
        $link = PortalLink::getById((int)($params['id'] ?? 0));
        if (!$link || empty($link['certified'])) { $this->redirect('/admin?tab=links'); return; }

        $preview = \App\Core\LinkPreview::fetch($link['url']);
        if (!$preview['ok'] || !$preview['image_path']) {
            $this->redirect('/admin?tab=links&msg=link_unreachable');
            return;
        }
        if ($link['image_path']) @unlink(BASE_PATH . $link['image_path']);
        PortalLink::updatePreviewImage($link['id'], $preview['image_path']);
        $this->redirect('/admin?tab=links&msg=link_saved');
    }

    // ── Politique de confidentialité / CGU (éditable, texte brut) ──────────────

    public function updateLegalContent(array $params): void
    {
        $this->requireSuperAdmin();
        $type = $_POST['type'] ?? '';
        if (!isset(LegalContent::TYPES[$type])) {
            $this->redirect('/admin?tab=legal');
            return;
        }
        LegalContent::set($type, trim($_POST['content'] ?? ''));
        $this->redirect('/admin?tab=legal&msg=legal_saved');
    }

    public function resetLegalContent(array $params): void
    {
        $this->requireSuperAdmin();
        $type = $params['type'] ?? '';
        if (isset(LegalContent::TYPES[$type])) {
            LegalContent::set($type, '');
        }
        $this->redirect('/admin?tab=legal&msg=legal_reset');
    }

    // ── Helpers ──────────────────────────────────────────────────

    private function redirect(string $path): void
    {
        header('Location: ' . BASE_URL . $path);
        exit;
    }
}
