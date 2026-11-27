<?php
namespace App\Controllers;

use App\Core\Session;
use App\Models\VaultwardenSettings;

/**
 * Relais serveur-à-serveur vers l'API cliente (non-admin) d'une instance Vaultwarden
 * auto-hébergée, pour permettre à la page FamilyBoard /settings de gérer les identifiants d'un
 * membre sans quitter l'application — sans jamais casser le chiffrement de bout en bout.
 *
 * Le navigateur fait toute la cryptographie (dérivation de la clé maître, (dé)chiffrement des
 * entrées) ; ce contrôleur ne fait que transmettre des octets déjà opaques pour FamilyBoard :
 * - prelogin/token : le mot de passe maître n'y transite jamais, seulement son empreinte dérivée
 *   côté client (identique à ce qu'envoie un vrai client Bitwarden — FamilyBoard n'apprend rien
 *   de plus que ce que le serveur Vaultwarden sait déjà).
 * - sync/ciphers : uniquement des blobs chiffrés (EncString), illisibles sans la clé maître que
 *   FamilyBoard ne connaît jamais.
 *
 * Restreint aux comptes non-coparent (même règle que Vaultwarden::inviteUser()) et uniquement si
 * l'instance est configurée par l'administrateur système.
 */
class VaultwardenProxyController extends BaseController
{
    private const TIMEOUT = 15;

    private function guard(): ?array
    {
        $this->requireAuth();
        $user = Session::user();
        if ($user['role'] === 'coparent') {
            http_response_code(403);
            echo json_encode(['error' => 'Non disponible pour ce type de compte.']);
            return null;
        }
        $settings = VaultwardenSettings::get();
        if (!$settings) {
            http_response_code(503);
            echo json_encode(['error' => 'Vaultwarden non configuré.']);
            return null;
        }
        return $settings;
    }

    public function prelogin(array $params): void
    {
        $settings = $this->guard();
        if (!$settings) return;
        $this->forward('POST', $settings['url'] . '/identity/accounts/prelogin', $this->rawBody(), 'application/json');
    }

    public function token(array $params): void
    {
        $settings = $this->guard();
        if (!$settings) return;
        // Le client envoie un corps application/x-www-form-urlencoded (grant_type=password...),
        // transmis tel quel — le "password" qu'il contient est déjà l'empreinte dérivée côté
        // client, jamais le mot de passe maître lui-même.
        $this->forward('POST', $settings['url'] . '/identity/connect/token', $this->rawBody(), 'application/x-www-form-urlencoded');
    }

    public function sync(array $params): void
    {
        $settings = $this->guard();
        if (!$settings) return;
        $this->forward('GET', $settings['url'] . '/api/sync?excludeDomains=true', null, null, true);
    }

    public function createCipher(array $params): void
    {
        $settings = $this->guard();
        if (!$settings) return;
        $this->forward('POST', $settings['url'] . '/api/ciphers', $this->rawBody(), 'application/json', true);
    }

    public function updateCipher(array $params): void
    {
        $settings = $this->guard();
        if (!$settings) return;
        $id = $this->sanitizeId($params['id'] ?? '');
        if (!$id) { http_response_code(400); return; }
        $this->forward('PUT', $settings['url'] . '/api/ciphers/' . $id, $this->rawBody(), 'application/json', true);
    }

    public function deleteCipher(array $params): void
    {
        $settings = $this->guard();
        if (!$settings) return;
        $id = $this->sanitizeId($params['id'] ?? '');
        if (!$id) { http_response_code(400); return; }
        $this->forward('DELETE', $settings['url'] . '/api/ciphers/' . $id, null, null, true);
    }

    /** N'autorise que des identifiants Vaultwarden plausibles (uuid) dans l'URL relayée — jamais
     *  un chemin arbitraire, pour éviter tout détournement du relais vers une autre ressource. */
    private function sanitizeId(string $id): ?string
    {
        return preg_match('/^[a-f0-9-]{10,60}$/i', $id) ? $id : null;
    }

    private function rawBody(): string
    {
        return file_get_contents('php://input') ?: '';
    }

    private function forward(string $method, string $url, ?string $body, ?string $contentType, bool $withAuth = false): void
    {
        $headers = [];
        if ($contentType) $headers[] = 'Content-Type: ' . $contentType;
        if ($withAuth) {
            $auth = $this->bearerHeader();
            if (!$auth) { http_response_code(401); echo json_encode(['error' => 'Jeton manquant.']); return; }
            $headers[] = 'Authorization: ' . $auth;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => self::TIMEOUT,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_USERAGENT      => 'FamilyBoard/1.0',
        ]);
        if ($body !== null && $method !== 'GET') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $respContentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            http_response_code(502);
            echo json_encode(['error' => 'Instance Vaultwarden injoignable : ' . $error]);
            return;
        }
        http_response_code($status ?: 502);
        if ($respContentType) header('Content-Type: ' . $respContentType);
        echo $response;
    }

    private function bearerHeader(): ?string
    {
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        foreach ($headers as $k => $v) {
            if (strcasecmp($k, 'Authorization') === 0) return $v;
        }
        return isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : null;
    }
}
