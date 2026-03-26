<?php
namespace App\Controllers;

use App\Core\Database;
use App\Core\Session;
use App\Core\WebPush;
use App\Models\AppSetting;
use App\Models\SupportTicket;
use App\Models\User;

class AdminController extends BaseController
{
    // ── Auth ────────────────────────────────────────────────────

    private function requireSuperAdmin(): void
    {
        if (!($_SESSION['admin_logged_in'] ?? false)) {
            header('Location: ' . BASE_URL . '/admin/login');
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
            $_SESSION['admin_logged_in'] = true;
            header('Location: ' . BASE_URL . '/admin');
        } else {
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
        require BASE_PATH . '/templates/admin/profile.php';
    }

    public function updateProfile(array $params): void
    {
        $this->requireSuperAdmin();

        $currentPass = $_POST['current_password'] ?? '';
        $newUser     = trim($_POST['username'] ?? '');
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
            'push_subs'   => (int)(Database::fetch('SELECT COUNT(*) c FROM push_subscriptions')['c'] ?? 0),
        ];
        $families     = Database::fetchAll('SELECT f.*, COUNT(u.id) as member_count FROM families f LEFT JOIN users u ON u.family_id=f.id GROUP BY f.id ORDER BY f.created_at DESC');
        $users        = Database::fetchAll('SELECT u.*, f.name as family_name FROM users u JOIN families f ON f.id=u.family_id ORDER BY u.blocked_at IS NULL DESC, u.created_at DESC');
        $blockedIps   = Database::fetchAll('SELECT * FROM blocked_ips ORDER BY created_at DESC');
        $tickets      = SupportTicket::getAll();
        $vapidPublic  = AppSetting::get('vapid_public');

        require BASE_PATH . '/templates/admin/index.php';
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

    // ── Push notifications ───────────────────────────────────────

    public function testPush(array $params): void
    {
        $this->requireSuperAdmin();
        header('Content-Type: application/json; charset=utf-8');

        $vapid = AppSetting::getVapidKeys();
        if (!$vapid) { echo json_encode(['error' => 'Clés VAPID absentes']); exit; }

        $sub = Database::fetch('SELECT * FROM push_subscriptions LIMIT 1');
        if (!$sub) { echo json_encode(['error' => 'Aucun abonné en base']); exit; }

        $payload = json_encode(['title' => 'Test FamilyBoard', 'body' => 'Ceci est un test.', 'url' => '/']);

        try {
            $result = WebPush::sendDebug($sub['endpoint'], $sub['p256dh'], $sub['auth_key'], $vapid['public'], $vapid['private'], $payload);
            echo json_encode($result);
        } catch (\Throwable $e) {
            echo json_encode(['exception' => $e->getMessage()]);
        }
        exit;
    }

    public function generateVapidKeys(array $params): void
    {
        $this->requireSuperAdmin();
        $keys = WebPush::generateVapidKeys();
        AppSetting::set('vapid_public',  $keys['public']);
        AppSetting::set('vapid_private', $keys['private']);
        $this->redirect('/admin?tab=push&msg=keys_generated');
    }

    public function sendPush(array $params): void
    {
        $this->requireSuperAdmin();
        $title    = trim($_POST['title'] ?? '');
        $body     = trim($_POST['body']  ?? '');
        $url      = trim($_POST['url']   ?? '/');
        $familyId = (int)($_POST['family_id'] ?? 0);

        $vapid = AppSetting::getVapidKeys();
        if (!$vapid || !$title) {
            $this->redirect('/admin?tab=push&msg=error');
            return;
        }

        $payload = json_encode(['title' => $title, 'body' => $body, 'url' => $url]);

        $sql = 'SELECT ps.* FROM push_subscriptions ps';
        $binds = [];
        if ($familyId > 0) {
            $sql .= ' JOIN users u ON u.id=ps.user_id WHERE u.family_id=?';
            $binds[] = $familyId;
        }
        $subs = Database::fetchAll($sql, $binds);

        $ok = 0; $fail = 0; $lastErr = '';
        foreach ($subs as $sub) {
            try {
                if (WebPush::send($sub['endpoint'], $sub['p256dh'], $sub['auth_key'], $vapid['public'], $vapid['private'], $payload)) {
                    $ok++;
                } else {
                    $fail++;
                }
            } catch (\Throwable $e) {
                $fail++;
                $lastErr = $e->getMessage();
            }
        }

        $msg = 'sent_' . $ok;
        if ($fail > 0) $msg .= '_fail_' . $fail;
        if ($lastErr)  $_SESSION['push_last_error'] = substr($lastErr, 0, 200);
        $this->redirect('/admin?tab=push&msg=' . $msg);
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
            if ($ticket) {
                $this->notifyUser($ticket['user_id'], 'Nouvelle réponse de support', "Ticket : " . $ticket['subject'], BASE_URL . '/support/' . $id);
            }
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

    // ── Helpers ──────────────────────────────────────────────────

    private function redirect(string $path): void
    {
        header('Location: ' . BASE_URL . $path);
        exit;
    }

    private function notifyUser(int $userId, string $title, string $body, string $url): void
    {
        $vapid = AppSetting::getVapidKeys();
        if (!$vapid) return;
        $payload = json_encode(['title' => $title, 'body' => $body, 'url' => $url]);
        $subs = Database::fetchAll('SELECT * FROM push_subscriptions WHERE user_id=?', [$userId]);
        foreach ($subs as $sub) {
            try {
                WebPush::send($sub['endpoint'], $sub['p256dh'], $sub['auth_key'], $vapid['public'], $vapid['private'], $payload);
            } catch (\Throwable) {}
        }
    }
}
