<?php
namespace App\Controllers;

use App\Core\Database;
use App\Core\Session;
use App\Models\SupportTicket;

class SupportController extends BaseController
{
    public function index(array $params): void
    {
        $this->requireAuth(true);
        $user    = Session::user();
        // Un co-parent appartient, en base, à la famille qui l'a invité : il ne doit voir que
        // ses propres tickets, jamais ceux des autres membres de cette famille.
        $tickets = $user['role'] === 'coparent'
            ? SupportTicket::getByFamilyAndUser($user['family_id'], $user['id'])
            : SupportTicket::getByFamily($user['family_id']);
        require BASE_PATH . '/templates/support/index.php';
    }

    public function show(array $params): void
    {
        $this->requireAuth(true);
        $user   = Session::user();
        $id     = (int)$params['id'];
        $ticket = SupportTicket::getById($id);
        if (!$this->ownsTicket($ticket, $user)) {
            header('Location: ' . BASE_URL . '/support');
            exit;
        }
        $messages = SupportTicket::getMessages($id);
        require BASE_PATH . '/templates/support/show.php';
    }

    public function create(array $params): void
    {
        $this->requireAuth(true);
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
        $this->requireAuth(true);
        $user    = Session::user();
        $id      = (int)$params['id'];
        $ticket  = SupportTicket::getById($id);
        if (!$this->ownsTicket($ticket, $user)) {
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

    private function ownsTicket(?array $ticket, array $user): bool
    {
        if (!$ticket || (int)$ticket['family_id'] !== (int)$user['family_id']) return false;
        // Un co-parent (family_id = celui de la famille qui l'a invité) ne doit accéder qu'aux
        // tickets qu'il a lui-même ouverts, jamais ceux des autres membres de cette famille.
        if ($user['role'] === 'coparent' && (int)$ticket['user_id'] !== (int)$user['id']) return false;
        return true;
    }
}
