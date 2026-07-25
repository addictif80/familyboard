<?php
namespace App\Controllers;

use App\Core\OcrHelper;
use App\Core\Session;
use App\Models\CommLogMessage;
use App\Models\Notification;

class CommLogController extends BaseController
{
    private const MAX_VOICE_SIZE = 15 * 1024 * 1024;

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
            $user = Session::user();
            $audioFile = $_FILES['audio'] ?? null;

            if ($audioFile) {
                $content = trim($_POST['content'] ?? '');
                if (mb_strlen($content) > 4000) return ['success' => false, 'error' => 'Message trop long'];
                try {
                    [$audioPath, $audioOriginal, $audioMime] =
                        OcrHelper::saveUploadedFile($audioFile, 'voice', $user['family_id'], OcrHelper::VOICE_MIMES, self::MAX_VOICE_SIZE);
                } catch (\RuntimeException $e) {
                    return ['success' => false, 'error' => $e->getMessage()];
                }
                $duration = isset($_POST['duration']) ? (int)$_POST['duration'] : null;
                $id = CommLogMessage::create($user['family_id'], $user['id'], $content, null, $audioPath, $audioOriginal, $audioMime, $duration);
            } else {
                $content = trim($this->jsonInput()['content'] ?? '');
                if (!$content) return ['success' => false];
                if (mb_strlen($content) > 4000) return ['success' => false, 'error' => 'Message trop long'];
                $id = CommLogMessage::create($user['family_id'], $user['id'], $content);
                $audioPath = $audioMime = null;
                $duration = null;
            }

            Notification::notifyFamily($user['family_id'], $user['id'], 'comm_log',
                'Journal parental', $user['name'] . ' a ajouté ' . ($audioPath ? 'un message vocal' : 'un message') . '.', BASE_URL . '/comm-log');

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
        $msg  = CommLogMessage::findById((int)$params['id']);

        if (!$msg || $msg['family_id'] !== $user['family_id'] || !$msg['audio_path']) {
            http_response_code(404); echo 'Introuvable.'; return;
        }

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
