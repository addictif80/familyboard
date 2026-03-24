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
        $family  = Family::findById($user['family_id']);
        $strixUrl = $family['strix_url'] ?? '';
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
     * Proxy Strix SSE stream discovery to avoid CORS issues.
     * Forwards GET params: target, username, password, model, channel.
     */
    public function strixDiscover(array $params): void
    {
        $this->requireAuth();
        $user    = Session::user();
        $family  = Family::findById($user['family_id']);
        $strixUrl = rtrim($family['strix_url'] ?? '', '/');

        if (!$strixUrl) {
            http_response_code(503);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'URL Strix non configurée dans les paramètres.']);
            exit;
        }

        // Libère le verrou de session pour ne pas bloquer les autres requêtes
        session_write_close();

        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('X-Accel-Buffering: no');

        // Désactive tous les niveaux de tampon PHP
        while (ob_get_level() > 0) ob_end_flush();
        @ini_set('implicit_flush', true);

        // Signal initial pour que le navigateur sache que la connexion est ouverte
        echo ": connected\n\n";
        flush();

        $q = http_build_query([
            'target'      => $_GET['target']   ?? '',
            'model'       => $_GET['model']    ?? 'auto',
            'username'    => $_GET['username'] ?? 'admin',
            'password'    => $_GET['password'] ?? '',
            'channel'     => (int)($_GET['channel'] ?? 1),
            'max_streams' => 10,
            'timeout'     => 120,
        ]);

        if (!function_exists('curl_init')) {
            echo "event: complete\n";
            echo "data: " . json_encode(['streams_found' => 0, 'error' => 'cURL non disponible sur ce serveur.']) . "\n\n";
            flush();
            exit;
        }

        $ch = curl_init($strixUrl . '/api/v1/streams/discover?' . $q);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_WRITEFUNCTION  => static function ($c, $data) {
                echo $data;
                flush();
                return strlen($data);
            },
            CURLOPT_TIMEOUT        => 150,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_FOLLOWLOCATION => true,
        ]);
        $ok = curl_exec($ch);

        if ($ok === false) {
            $errMsg = curl_strerror(curl_errno($ch));
            echo "event: complete\n";
            echo "data: " . json_encode(['streams_found' => 0, 'error' => "Impossible de joindre Strix : $errMsg"]) . "\n\n";
            flush();
        }

        curl_close($ch);
        exit;
    }

    /**
     * Proxy Strix camera model search (autocomplete).
     */
    public function strixSearch(array $params): void
    {
        $this->requireAuth();
        $this->json(function () {
            $user    = Session::user();
            $family  = Family::findById($user['family_id']);
            $strixUrl = rtrim($family['strix_url'] ?? '', '/');
            if (!$strixUrl) return [];

            $data = $this->jsonInput();
            $ch   = curl_init($strixUrl . '/api/v1/cameras/search');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => json_encode(['query' => $data['query'] ?? '', 'limit' => 10]),
                CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
                CURLOPT_TIMEOUT        => 5,
                CURLOPT_CONNECTTIMEOUT => 3,
            ]);
            $resp = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            return ($code === 200 && $resp) ? json_decode($resp, true) : [];
        });
    }
}
