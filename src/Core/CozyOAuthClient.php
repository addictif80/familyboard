<?php
namespace App\Core;

/**
 * Client OAuth2 minimal pour une instance Cozy Cloud — enregistrement dynamique de client (RFC
 * 7591, propre à chaque instance : pas de registre centralisé), autorisation et échange/
 * rafraîchissement de jeton. Portée demandée : "io.cozy.files" (lecture + suppression, nécessaire
 * pour rapatrier un document puis le supprimer côté Cozy une fois importé — voir
 * CozyDocumentSync). Cf. https://docs.cozy.io/en/cozy-stack/auth/.
 */
class CozyOAuthClient
{
    private const TIMEOUT = 20;
    private const SCOPE = 'io.cozy.files';

    public static function registerClient(string $domain, string $redirectUri): array
    {
        $ch = curl_init("https://$domain/auth/register");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => self::TIMEOUT,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode([
                'redirect_uris' => [$redirectUri],
                'client_name'   => 'FamilyBoard',
                'software_id'   => 'familyboard',
            ]),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        ]);
        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode((string)$response, true);
        if ($status !== 201 || !is_array($data) || empty($data['client_id'])) {
            return ['ok' => false, 'error' => "Échec de l'enregistrement OAuth auprès de Cozy (HTTP $status)."];
        }
        return ['ok' => true, 'client_id' => $data['client_id'], 'client_secret' => $data['client_secret'] ?? ''];
    }

    public static function authorizeUrl(string $domain, string $clientId, string $redirectUri, string $state): string
    {
        return "https://$domain/auth/authorize?" . http_build_query([
            'client_id'     => $clientId,
            'redirect_uri'  => $redirectUri,
            'response_type' => 'code',
            'scope'         => self::SCOPE,
            'state'         => $state,
        ]);
    }

    public static function exchangeCode(string $domain, string $clientId, string $clientSecret, string $code, string $redirectUri): array
    {
        return self::tokenRequest($domain, [
            'grant_type'    => 'authorization_code',
            'code'          => $code,
            'client_id'     => $clientId,
            'client_secret' => $clientSecret,
            'redirect_uri'  => $redirectUri,
        ]);
    }

    public static function refreshToken(string $domain, string $clientId, string $clientSecret, string $refreshToken): array
    {
        return self::tokenRequest($domain, [
            'grant_type'    => 'refresh_token',
            'refresh_token' => $refreshToken,
            'client_id'     => $clientId,
            'client_secret' => $clientSecret,
        ]);
    }

    private static function tokenRequest(string $domain, array $params): array
    {
        $ch = curl_init("https://$domain/auth/access_token");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => self::TIMEOUT,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($params),
        ]);
        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode((string)$response, true);
        if ($status !== 200 || !is_array($data) || empty($data['access_token'])) {
            return ['ok' => false, 'error' => "Échec de l'échange de jeton OAuth Cozy (HTTP $status)."];
        }
        return [
            'ok'            => true,
            'access_token'  => $data['access_token'],
            'refresh_token' => $data['refresh_token'] ?? null,
            'expires_in'    => isset($data['expires_in']) ? (int)$data['expires_in'] : null,
        ];
    }
}
