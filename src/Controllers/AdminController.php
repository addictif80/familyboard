<?php
namespace App\Controllers;

use App\Core\Database;
use App\Core\Mail;
use App\Core\Session;
use App\Models\AppSetting;
use App\Models\EmailContent;
use App\Core\OfficialAlertFeed;
use App\Models\ImpersonationLog;
use App\Models\Notification;
use App\Models\SmtpSettings;
use App\Models\SupportTicket;
use App\Models\User;
use App\Core\EmailLayout;

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
        $meteoFranceApiKey = AppSetting::get('meteofrance_api_key') ?? '';
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

    // ── Notifications système (broadcast à tous les utilisateurs) ─

    public function sendSystemNotification(array $params): void
    {
        $this->requireSuperAdmin();
        $title      = trim($_POST['title'] ?? '');
        $shortText  = trim($_POST['short_text'] ?? '');
        $contentHtml = $this->sanitizeHtml(trim($_POST['content'] ?? ''));
        $contentIsEmpty = trim(strip_tags($contentHtml)) === '';

        if ($title && $shortText && !$contentIsEmpty && mb_strlen($title) <= 150 && mb_strlen($shortText) <= 300) {
            Notification::broadcastToAll($title, $shortText, $contentHtml);
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

    // ── Helpers ──────────────────────────────────────────────────

    private function redirect(string $path): void
    {
        header('Location: ' . BASE_URL . $path);
        exit;
    }
}
