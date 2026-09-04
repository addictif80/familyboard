<?php
namespace App\Controllers;

use App\Core\DigiposteClient;
use App\Core\Session;
use App\Models\DigiposteConnection;

/**
 * Connexion OAuth2 personnelle d'un utilisateur à son coffre-fort Digiposte (voir
 * App\Core\DigiposteClient pour l'avertissement sur les chemins d'API non encore vérifiés).
 * Chaque membre connecte SON PROPRE compte depuis ses réglages — jamais un accès "famille".
 */
class DigiposteController extends BaseController
{
    public function connect(array $params): void
    {
        $this->requireAuth();
        if (!DigiposteClient::isConfigured()) {
            Session::flash('error', "L'import Digiposte n'est pas encore configuré par l'administrateur.");
            header('Location: ' . BASE_URL . '/settings');
            exit;
        }
        $state = bin2hex(random_bytes(24));
        Session::set('digiposte_oauth_state', $state);
        header('Location: ' . DigiposteClient::authorizeUrl($state, $this->redirectUri()));
        exit;
    }

    public function callback(array $params): void
    {
        $this->requireAuth();
        $user = Session::user();

        $expectedState = Session::get('digiposte_oauth_state');
        Session::set('digiposte_oauth_state', null);
        $state = $_GET['state'] ?? '';
        $code = $_GET['code'] ?? '';

        if (!$code || !$expectedState || !hash_equals((string)$expectedState, (string)$state)) {
            Session::flash('error', 'Connexion Digiposte annulée ou expirée — réessayez.');
            header('Location: ' . BASE_URL . '/settings');
            exit;
        }

        $tokens = DigiposteClient::exchangeCode($code, $this->redirectUri());
        if (!$tokens) {
            Session::flash('error', 'La connexion à Digiposte a échoué. Réessayez dans quelques instants.');
            header('Location: ' . BASE_URL . '/settings');
            exit;
        }

        DigiposteConnection::save((int)$user['id'], $tokens['access_token'], $tokens['refresh_token'], $tokens['expires_in']);
        Session::flash('success', 'Coffre Digiposte connecté.');
        header('Location: ' . BASE_URL . '/settings#tab-compte');
        exit;
    }

    public function disconnect(array $params): void
    {
        $this->requireAuth();
        $user = Session::user();
        DigiposteConnection::disconnect((int)$user['id']);
        Session::flash('success', 'Coffre Digiposte déconnecté.');
        header('Location: ' . BASE_URL . '/settings#tab-compte');
        exit;
    }

    public function syncNow(array $params): void
    {
        $this->requireAuth();
        $this->json(function () {
            $user = Session::user();
            $result = DigiposteConnection::syncUser($user);
            if ($result['error'] === 'not_connected') return ['success' => false, 'error' => 'Coffre non connecté.'];
            if ($result['error']) return ['success' => false, 'error' => 'Import impossible pour le moment — réessayez plus tard.'];
            return ['success' => true, 'imported' => $result['imported']];
        });
    }

    private function redirectUri(): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        return $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . BASE_URL . '/digiposte/callback';
    }
}
