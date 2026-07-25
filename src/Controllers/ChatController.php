<?php
namespace App\Controllers;

use App\Core\OcrHelper;
use App\Core\Session;
use App\Models\Message;
use App\Models\Notification;

class ChatController extends BaseController
{
    private const MAX_VOICE_SIZE = 15 * 1024 * 1024;

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
            $audioFile = $_FILES['audio'] ?? null;

            if ($audioFile) {
                $content = trim($_POST['content'] ?? '');
                if (mb_strlen($content) > 2000) return ['success' => false, 'error' => 'Message trop long'];
                try {
                    [$audioPath, $audioOriginal, $audioMime] =
                        OcrHelper::saveUploadedFile($audioFile, 'voice', $user['family_id'], OcrHelper::VOICE_MIMES, self::MAX_VOICE_SIZE);
                } catch (\RuntimeException $e) {
                    return ['success' => false, 'error' => $e->getMessage()];
                }
                $duration = isset($_POST['duration']) ? (int)$_POST['duration'] : null;
                $id = Message::create($user['family_id'], $user['id'], $content, $audioPath, $audioOriginal, $audioMime, $duration);
                $notifText = '🎤 Message vocal' . ($content ? ' — ' . mb_strimwidth($content, 0, 60, '…') : '');
            } else {
                $data = $this->jsonInput();
                $content = trim($data['content'] ?? '');
                if (!$content) return ['success' => false];
                if (mb_strlen($content) > 2000) return ['success' => false, 'error' => 'Message trop long'];
                $id = Message::create($user['family_id'], $user['id'], $content);
                $audioPath = $audioMime = null;
                $duration = null;
                $notifText = mb_strimwidth($content, 0, 80, '…');
            }

            Notification::notifyFamily($user['family_id'], $user['id'], 'chat',
                $user['name'], $notifText, BASE_URL . '/chat');

            return [
                'success' => true,
                'message' => [
                    'id' => $id,
                    'content' => $content,
                    'audio_path' => $audioPath,
                    'audio_mime' => $audioMime,
                    'audio_duration' => $duration,
                    'user_name' => $user['name'],
                    'user_color' => $user['color'],
                    'user_avatar' => $user['avatar'],
                    'created_at' => date('Y-m-d H:i:s'),
                    'user_id' => $user['id'],
                ],
            ];
        });
    }

    public function serveAudio(array $params): void
    {
        $this->requireAuth();
        $user = Session::user();
        $msg  = Message::findById((int)$params['id'], $user['family_id']);

        if (!$msg || !$msg['audio_path']) { http_response_code(404); echo 'Introuvable.'; return; }

        $path = BASE_PATH . $msg['audio_path'];
        if (!file_exists($path)) { http_response_code(404); echo 'Introuvable.'; return; }

        header('Content-Type: ' . ($msg['audio_mime'] ?: 'audio/webm'));
        header('Content-Length: ' . filesize($path));
        header($this->contentDispositionHeader($msg['audio_original'] ?: basename($path)));
        header('Cache-Control: private, max-age=3600');
        header('Accept-Ranges: bytes');
        readfile($path);
        exit;
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
