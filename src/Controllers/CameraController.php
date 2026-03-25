<?php
namespace App\Controllers;

use App\Core\Session;
use App\Models\Camera;
use App\Models\Family;

class CameraController extends BaseController
{
    public function index(array $params): void
    {
        $this->requireAuth();
        $user    = Session::user();
        $cameras = Camera::getByFamily($user['family_id']);
        require BASE_PATH . '/templates/cameras/index.php';
    }

    public function create(array $params): void
    {
        $this->requireAuth();
        $this->json(function () {
            $user = Session::user();
            $id = Camera::create($user['family_id'], $user['id'], $this->jsonInput());
            return ['success' => true, 'id' => $id];
        });
    }

    public function update(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $id   = (int)$params['id'];
            $cam  = Camera::getById($id);
            if (!$cam || $cam['family_id'] !== $user['family_id']) return ['success' => false];
            Camera::update($id, $this->jsonInput());
            return ['success' => true];
        });
    }

    public function delete(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $id   = (int)$params['id'];
            $cam  = Camera::getById($id);
            if (!$cam || $cam['family_id'] !== $user['family_id']) return ['success' => false];
            Camera::delete($id);
            return ['success' => true];
        });
    }

    /**
     * Proxy RTSP → MJPEG via FFmpeg.
     * Streams multipart/x-mixed-replace to the browser so any <img> tag can
     * display a live RTSP camera feed without browser-side RTSP support.
     */
    public function stream(array $params): void
    {
        $this->requireAuth();
        session_write_close(); // libère le verrou de session

        $user = Session::user();
        $id   = (int)$params['id'];
        $cam  = Camera::getById($id);

        if (!$cam || $cam['family_id'] !== $user['family_id'] || !$cam['stream_url']) {
            http_response_code(404);
            exit;
        }

        // Injecte les identifiants dans l'URL RTSP s'ils ne sont pas déjà présents
        $url = $cam['stream_url'];
        if (!empty($cam['username']) && !preg_match('#://[^@/]+@#', $url)) {
            $cu  = urlencode($cam['username']);
            $cp  = $cam['password'] ? ':' . urlencode($cam['password']) : '';
            $url = preg_replace('#^(rtsp://)#i', "rtsp://{$cu}{$cp}@", $url);
        }

        // Localise FFmpeg
        $ffmpeg = trim((string)(shell_exec('which ffmpeg 2>/dev/null') ?: ''));
        if (!$ffmpeg || !is_executable($ffmpeg)) $ffmpeg = '/usr/bin/ffmpeg';
        if (!is_executable($ffmpeg)) {
            http_response_code(503);
            header('Content-Type: text/plain');
            echo 'FFmpeg introuvable sur ce serveur.';
            exit;
        }

        $cmd  = $ffmpeg
              . ' -loglevel error'
              . ' -rtsp_transport tcp'
              . ' -i ' . escapeshellarg($url)
              . ' -an -f mjpeg -q:v 5 -vf fps=5'
              . ' pipe:1';

        $desc = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['file', '/dev/null', 'w'],
        ];

        $proc = proc_open($cmd, $desc, $pipes);
        if (!is_resource($proc)) {
            http_response_code(500);
            exit;
        }

        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);

        $boundary = 'fbmjpeg';
        header('Content-Type: multipart/x-mixed-replace; boundary=' . $boundary);
        header('Cache-Control: no-cache, no-store');
        header('X-Accel-Buffering: no');
        header('Connection: close');
        while (ob_get_level() > 0) ob_end_flush();

        $buf = '';
        while (!connection_aborted()) {
            $st    = proc_get_status($proc);
            $chunk = fread($pipes[1], 16384);

            if ($chunk === false || $chunk === '') {
                if (!$st['running']) break;
                usleep(5000);
                continue;
            }
            $buf .= $chunk;

            // Extrait les frames JPEG complètes (\xFF\xD8 … \xFF\xD9)
            while (($s = strpos($buf, "\xFF\xD8")) !== false) {
                $e = strpos($buf, "\xFF\xD9", $s + 2);
                if ($e === false) { $buf = substr($buf, $s); break; }
                $frame = substr($buf, $s, $e - $s + 2);
                $buf   = substr($buf, $e + 2);
                echo "--{$boundary}\r\n"
                   . "Content-Type: image/jpeg\r\n"
                   . "Content-Length: " . strlen($frame) . "\r\n"
                   . "\r\n"
                   . $frame
                   . "\r\n";
                flush();
            }
        }

        fclose($pipes[1]);
        proc_terminate($proc, 15);
        proc_close($proc);
        exit;
    }

}
