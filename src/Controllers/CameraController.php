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
     * Étape 1 (POST) : enregistre la caméra dans go2rtc et vérifie que c'est OK.
     * Retourne JSON {ok:true} ou {error:"message lisible"}.
     */
    public function go2rtcRegister(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $id   = (int)$params['id'];
            $cam  = Camera::getById($id);

            if (!$cam || $cam['family_id'] !== $user['family_id'] || !$cam['stream_url']) {
                return ['error' => 'Caméra introuvable.'];
            }

            $family     = Family::findById($user['family_id']);
            $go2rtcBase = rtrim($family['go2rtc_url'] ?? '', '/');

            if (!$go2rtcBase) {
                return ['error' => 'go2rtc non configuré — renseignez l\'URL dans Paramètres.'];
            }

            $rtspUrl = $cam['stream_url'];
            if (!empty($cam['username']) && !preg_match('#://[^@/]+@#', $rtspUrl)) {
                $cu      = urlencode($cam['username']);
                $cp      = $cam['password'] ? ':' . urlencode($cam['password']) : '';
                $rtspUrl = preg_replace('#^(rtsp://)#i', "rtsp://{$cu}{$cp}@", $rtspUrl);
            }

            $name = 'cam_' . $id;

            $ch = curl_init($go2rtcBase . '/api/streams?' . http_build_query(['name' => $name, 'src' => $rtspUrl]));
            curl_setopt_array($ch, [
                CURLOPT_CUSTOMREQUEST  => 'PUT',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 5,
                CURLOPT_CONNECTTIMEOUT => 3,
            ]);
            curl_exec($ch);
            $cerr = curl_error($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($cerr) {
                return ['error' => "go2rtc inaccessible ({$go2rtcBase}) : $cerr"];
            }
            if ($code >= 400) {
                return ['error' => "go2rtc a refusé l'enregistrement (HTTP $code)."];
            }

            // Attend que go2rtc tente la connexion RTSP, puis vérifie l'état réel
            usleep(2500000); // 2.5s

            $check = curl_init($go2rtcBase . '/api/streams');
            curl_setopt_array($check, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 3,
                CURLOPT_CONNECTTIMEOUT => 2,
            ]);
            $resp  = curl_exec($check);
            curl_close($check);

            if ($resp) {
                $all      = json_decode($resp, true) ?? [];
                $stream   = $all[$name] ?? null;
                $producers = $stream['producers'] ?? [];
                foreach ($producers as $p) {
                    if (($p['state'] ?? 'online') !== 'online') {
                        $detail = $p['error'] ?? 'connexion échouée';
                        return ['error' => "go2rtc → RTSP : $detail"];
                    }
                }
            }

            return ['ok' => true];
        });
    }

    /**
     * Étape 2 (GET) : proxifie le flux MJPEG de go2rtc vers le navigateur.
     * Le navigateur ne contacte que PHP — go2rtc peut rester sur 127.0.0.1.
     */
    public function go2rtcStream(array $params): void
    {
        $this->requireAuth();
        session_write_close();

        $user = Session::user();
        $id   = (int)$params['id'];
        $cam  = Camera::getById($id);

        if (!$cam || $cam['family_id'] !== $user['family_id']) {
            http_response_code(404); exit;
        }

        $family     = Family::findById($user['family_id']);
        $go2rtcBase = rtrim($family['go2rtc_url'] ?? '', '/');
        $name       = 'cam_' . $id;

        header('Cache-Control: no-cache, no-store');
        header('X-Accel-Buffering: no');
        while (ob_get_level() > 0) ob_end_flush();

        $ch = curl_init($go2rtcBase . '/api/stream.mjpeg?' . http_build_query(['src' => $name]));
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_HEADERFUNCTION => static function ($c, $header) {
                if (stripos($header, 'content-type:') === 0) {
                    header(trim($header));
                }
                return strlen($header);
            },
            CURLOPT_WRITEFUNCTION  => static function ($c, $data) {
                echo $data;
                flush();
                return strlen($data);
            },
            CURLOPT_TIMEOUT        => 300,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);
        curl_exec($ch);
        curl_close($ch);
        exit;
    }

}
