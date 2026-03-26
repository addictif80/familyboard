<?php
namespace App\Controllers;

use App\Core\Database;
use App\Core\Session;
use App\Models\SupportTicket;

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
}
