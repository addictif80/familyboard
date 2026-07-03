<?php
namespace App\Controllers;

use App\Core\Session;
use App\Models\Message;
use App\Models\Notification;

class ChatController extends BaseController
{
    public function index(array $params): void
    {
        $this->requireAuth();
        $this->requireModule('chat');
        $user = Session::user();
        $messages = array_reverse(Message::getByFamily($user['family_id'], 50));
        $lastId = Message::getLastId($user['family_id']);
        require BASE_PATH . '/templates/chat/index.php';
    }

    public function send(array $params): void
    {
        $this->requireAuth();
        $this->json(function () {
            $user = Session::user();
            $data = $this->jsonInput();
            $content = trim($data['content'] ?? '');
            if (!$content) return ['success' => false];
            if (mb_strlen($content) > 2000) return ['success' => false, 'error' => 'Message trop long'];

            $id = Message::create($user['family_id'], $user['id'], $content);

            Notification::notifyFamily($user['family_id'], $user['id'], 'chat',
                $user['name'], mb_strimwidth($content, 0, 80, '…'), BASE_URL . '/chat');

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
            $user = Session::user();
            $afterId = (int)($_GET['after'] ?? 0);
            $messages = Message::getNew($user['family_id'], $afterId);
            return ['messages' => $messages];
        });
    }

    public function delete(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $id = (int)$params['id'];
            // Only allow deleting own messages or admin
            $row = \App\Core\Database::fetch('SELECT * FROM messages WHERE id=?', [$id]);
            $msg = $row;
            if (!$msg || $msg['family_id'] !== $user['family_id']) return ['success' => false];
            if ($msg['user_id'] !== $user['id'] && $user['role'] !== 'admin') return ['success' => false];
            Message::delete($id);
            return ['success' => true];
        });
    }
}
