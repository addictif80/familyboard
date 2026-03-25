<?php
namespace App\Controllers;

use App\Core\Database;
use App\Core\Session;
use App\Models\AppSetting;
use App\Models\SupportTicket;
use App\Core\WebPush;

class SupportController extends BaseController
{
    public function index(array $params): void
    {
        $this->requireAuth();
        $user    = Session::user();
        $tickets = SupportTicket::getByFamily($user['family_id']);
        require BASE_PATH . '/templates/support/index.php';
    }

    public function show(array $params): void
    {
        $this->requireAuth();
        $user   = Session::user();
        $id     = (int)$params['id'];
        $ticket = SupportTicket::getById($id);
        if (!$ticket || $ticket['family_id'] !== $user['family_id']) {
            header('Location: ' . BASE_URL . '/support');
            exit;
        }
        $messages = SupportTicket::getMessages($id);
        require BASE_PATH . '/templates/support/show.php';
    }

    public function create(array $params): void
    {
        $this->requireAuth();
        $user    = Session::user();
        $subject = trim($_POST['subject'] ?? '');
        $message = trim($_POST['message'] ?? '');
        if (!$subject || !$message) {
            Session::flash('error', 'Sujet et message requis.');
            header('Location: ' . BASE_URL . '/support');
            exit;
        }
        $id = SupportTicket::create($user['family_id'], $user['id'], $subject, $message);
        header('Location: ' . BASE_URL . '/support/' . $id);
        exit;
    }

    public function reply(array $params): void
    {
        $this->requireAuth();
        $user    = Session::user();
        $id      = (int)$params['id'];
        $ticket  = SupportTicket::getById($id);
        if (!$ticket || $ticket['family_id'] !== $user['family_id']) {
            header('Location: ' . BASE_URL . '/support');
            exit;
        }
        $message = trim($_POST['message'] ?? '');
        if ($message) {
            SupportTicket::addMessage($id, $user['id'], false, $message);
        }
        header('Location: ' . BASE_URL . '/support/' . $id);
        exit;
    }

    public function subscribePush(array $params): void
    {
        $this->requireAuth();
        $this->json(function () {
            $user = Session::user();
            $data = $this->jsonInput();
            if (empty($data['endpoint']) || empty($data['p256dh']) || empty($data['auth'])) {
                return ['success' => false, 'error' => 'Données manquantes'];
            }
            // Upsert: one subscription per user+endpoint
            Database::execute(
                'INSERT INTO push_subscriptions (user_id, endpoint, p256dh, auth_key)
                 VALUES (?,?,?,?)
                 ON DUPLICATE KEY UPDATE p256dh=VALUES(p256dh), auth_key=VALUES(auth_key)',
                [$user['id'], $data['endpoint'], $data['p256dh'], $data['auth']]
            );
            return ['success' => true];
        });
    }

    public function unsubscribePush(array $params): void
    {
        $this->requireAuth();
        $this->json(function () {
            $user = Session::user();
            $data = $this->jsonInput();
            if (!empty($data['endpoint'])) {
                Database::execute('DELETE FROM push_subscriptions WHERE user_id=? AND endpoint=?', [$user['id'], $data['endpoint']]);
            }
            return ['success' => true];
        });
    }
}
