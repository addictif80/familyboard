<?php
namespace App\Controllers;

use App\Core\Session;
use App\Models\CommLogMessage;
use App\Models\Notification;

class CommLogController extends BaseController
{
    public function index(array $params): void
    {
        $this->requireAuth();
        $this->requireModule('comm_log');
        $user = Session::user();

        CommLogMessage::markAllRead($user['family_id'], $user['id']);

        $messages = CommLogMessage::getByFamily($user['family_id']);
        $reads    = CommLogMessage::getReadsByFamily($user['family_id']);
        $lastId   = CommLogMessage::getLastId($user['family_id']);
        require BASE_PATH . '/templates/comm_log/index.php';
    }

    public function send(array $params): void
    {
        $this->requireAuth();
        $this->json(function () {
            $user    = Session::user();
            $content = trim($this->jsonInput()['content'] ?? '');
            if (!$content) return ['success' => false];
            if (mb_strlen($content) > 4000) return ['success' => false, 'error' => 'Message trop long'];

            $id = CommLogMessage::create($user['family_id'], $user['id'], $content);

            Notification::notifyFamily($user['family_id'], $user['id'], 'comm_log',
                'Journal parental', $user['name'] . ' a ajouté un message.', BASE_URL . '/comm-log');

            return [
                'success' => true,
                'message' => [
                    'id' => $id,
                    'content' => $content,
                    'user_name' => $user['name'],
                    'user_color' => $user['color'],
                    'user_avatar' => $user['avatar'],
                    'created_at' => date('Y-m-d H:i:s'),
                    'user_id' => $user['id'],
                ],
            ];
        });
    }

    public function poll(array $params): void
    {
        $this->requireAuth();
        $this->json(function () {
            $user     = Session::user();
            $afterId  = (int)($_GET['after'] ?? 0);
            $messages = CommLogMessage::getNew($user['family_id'], $afterId);
            if ($messages) {
                CommLogMessage::markAllRead($user['family_id'], $user['id']);
            }
            return ['messages' => $messages];
        });
    }
}
