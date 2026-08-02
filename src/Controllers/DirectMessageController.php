<?php
namespace App\Controllers;

use App\Core\Session;
use App\Models\DirectMessage;
use App\Models\Follow;
use App\Models\Notification;
use App\Models\User;

/** Messagerie privée : réservée à deux membres qui se suivent mutuellement, jamais un accès
 *  co-parent, jamais entre deux familles différentes (Follow ne peut de toute façon exister
 *  qu'entre membres d'une même famille — voir FollowController::validTarget). */
class DirectMessageController extends BaseController
{
    public function inbox(array $params): void
    {
        $this->requireAuth();
        $this->requireModule('wall');
        $user = Session::user();
        $threads = DirectMessage::getThreadsForUser((int)$user['id']);
        require BASE_PATH . '/templates/messages/index.php';
    }

    public function thread(array $params): void
    {
        $this->requireAuth();
        $this->requireModule('wall');
        $user = Session::user();
        $otherId = (int)($params['userId'] ?? 0);
        $other = User::findById($otherId);
        if (!$other || $other['family_id'] !== $user['family_id'] || !Follow::isMutual((int)$user['id'], $otherId)) {
            Session::flash('error', 'Cette conversation nécessite un abonnement mutuel.');
            header('Location: ' . BASE_URL . '/messages');
            exit;
        }
        $threadId = DirectMessage::getOrCreateThread((int)$user['id'], $otherId);
        $messages = DirectMessage::getMessages($threadId);
        DirectMessage::markThreadRead($threadId, (int)$user['id']);
        require BASE_PATH . '/templates/messages/thread.php';
    }

    public function send(array $params): void
    {
        $this->requireAuth();
        $this->requireModule('wall');
        $this->json(function () use ($params) {
            $user = Session::user();
            $otherId = (int)($params['userId'] ?? 0);
            $other = User::findById($otherId);
            if (!$other || $other['family_id'] !== $user['family_id'] || !Follow::isMutual((int)$user['id'], $otherId)) {
                return ['success' => false, 'error' => 'Cette conversation nécessite un abonnement mutuel.'];
            }
            $content = trim($this->jsonInput()['content'] ?? '');
            if (!$content) return ['success' => false];
            if (mb_strlen($content) > 2000) return ['success' => false, 'error' => 'Message trop long'];

            $threadId = DirectMessage::getOrCreateThread((int)$user['id'], $otherId);
            $id = DirectMessage::send($threadId, (int)$user['id'], $content);

            Notification::create($otherId, 'wall', 'Nouveau message de ' . $user['name'],
                mb_strimwidth($content, 0, 80, '…'), BASE_URL . '/messages/' . $user['id']);

            return ['success' => true, 'message' => [
                'id' => $id,
                'content' => $content,
                'sender_id' => (int)$user['id'],
                'sender_name' => $user['name'],
                'sender_color' => $user['color'],
                'sender_avatar' => $user['avatar'],
                'created_at' => date('Y-m-d H:i:s'),
            ]];
        });
    }

    public function poll(array $params): void
    {
        $this->requireAuth();
        $this->requireModule('wall');
        $this->json(function () use ($params) {
            $user = Session::user();
            $otherId = (int)($params['userId'] ?? 0);
            $other = User::findById($otherId);
            if (!$other || $other['family_id'] !== $user['family_id'] || !Follow::isMutual((int)$user['id'], $otherId)) {
                return ['messages' => []];
            }
            $threadId = DirectMessage::getOrCreateThread((int)$user['id'], $otherId);
            $afterId = (int)($_GET['after'] ?? 0);
            $messages = DirectMessage::getNew($threadId, $afterId);
            if ($messages) DirectMessage::markThreadRead($threadId, (int)$user['id']);
            return ['messages' => $messages];
        });
    }
}
