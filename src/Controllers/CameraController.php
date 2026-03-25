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
     * Enregistre la caméra dans go2rtc via son API REST et retourne l'URL du player.
     * go2rtc gère la conversion RTSP → WebRTC côté serveur.
     */
    public function go2rtcStart(array $params): void
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

            // Injecte les identifiants RTSP si absents de l'URL
            $rtspUrl = $cam['stream_url'];
            if (!empty($cam['username']) && !preg_match('#://[^@/]+@#', $rtspUrl)) {
                $cu      = urlencode($cam['username']);
                $cp      = $cam['password'] ? ':' . urlencode($cam['password']) : '';
                $rtspUrl = preg_replace('#^(rtsp://)#i', "rtsp://{$cu}{$cp}@", $rtspUrl);
            }

            $name = 'cam_' . $id;

            // PUT /api/streams?name=cam_X&src=rtsp://... → enregistre le stream dans go2rtc
            $ch = curl_init($go2rtcBase . '/api/streams?' . http_build_query(['name' => $name, 'src' => $rtspUrl]));
            curl_setopt_array($ch, [
                CURLOPT_CUSTOMREQUEST  => 'PUT',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 5,
                CURLOPT_CONNECTTIMEOUT => 3,
            ]);
            curl_exec($ch);
            $cerr = curl_error($ch);
            curl_close($ch);

            if ($cerr) {
                return ['error' => "Impossible de joindre go2rtc : $cerr"];
            }

            return [
                'player_url' => $go2rtcBase . '/stream.html?src=' . urlencode($name),
            ];
        });
    }

}
