<?php
namespace App\Core;

use App\Models\AppSetting;

/**
 * Client OAuth2 pour l'API partenaire Digiposte (coffre-fort numérique La Poste/Docaposte —
 * https://developer.laposte.fr, produit "Digiposte"). Chaque appel utilise le jeton d'accès
 * PERSONNEL d'un utilisateur FamilyBoard (jamais un jeton "famille" : un compte Digiposte est
 * strictement individuel), stocké chiffré dans digiposte_connections (voir App\Core\Crypto).
 *
 * ⚠️ IMPORTANT — à relire avant mise en production : les chemins d'API et la forme des réponses
 * JSON (listDocuments()/downloadDocument()) sont des HYPOTHÈSES raisonnables (schéma REST/OAuth2
 * classique), pas vérifiées contre la documentation technique réelle de l'API Digiposte v3 — non
 * accessible publiquement au moment de l'écriture de ce code. Une fois les vrais identifiants et
 * la fiche technique obtenus sur developer.laposte.fr :
 *   1. Ajustez les chemins depuis le panneau admin (Notifications & intégrations → Digiposte) —
 *      aucun changement de code nécessaire pour ça, voir AppSetting.
 *   2. Vérifiez la forme réelle de la réponse de list() dans normalizeDocumentItem() ci-dessous —
 *      c'est le point le plus probable à corriger (noms de champs JSON différents).
 */
class DigiposteClient
{
    private const TIMEOUT = 15;

    public static function isConfigured(): bool
    {
        return (bool)(int)(AppSetting::get('digiposte_enabled') ?? '0')
            && (bool)AppSetting::get('digiposte_client_id')
            && (bool)AppSetting::get('digiposte_client_secret');
    }

    public static function authorizeUrl(string $state, string $redirectUri): string
    {
        $params = [
            'client_id'     => AppSetting::get('digiposte_client_id'),
            'redirect_uri'  => $redirectUri,
            'response_type' => 'code',
            'scope'         => AppSetting::get('digiposte_scope') ?: 'read',
            'state'         => $state,
        ];
        return (AppSetting::get('digiposte_authorize_url') ?: '') . '?' . http_build_query($params);
    }

    /** @return array{access_token:string,refresh_token:?string,expires_in:?int}|null */
    public static function exchangeCode(string $code, string $redirectUri): ?array
    {
        return self::tokenRequest([
            'grant_type'    => 'authorization_code',
            'code'          => $code,
            'redirect_uri'  => $redirectUri,
        ]);
    }

    public static function refreshAccessToken(string $refreshToken): ?array
    {
        return self::tokenRequest([
            'grant_type'    => 'refresh_token',
            'refresh_token' => $refreshToken,
        ]);
    }

    private static function tokenRequest(array $params): ?array
    {
        $params['client_id'] = AppSetting::get('digiposte_client_id');
        $params['client_secret'] = AppSetting::get('digiposte_client_secret');

        $url = rtrim((string)AppSetting::get('digiposte_base_url'), '/') . (AppSetting::get('digiposte_token_path') ?: '/oauth/token');
        $response = self::request('POST', $url, null, $params, true);
        if (!$response || !isset($response['access_token'])) return null;

        return [
            'access_token'  => $response['access_token'],
            'refresh_token' => $response['refresh_token'] ?? null,
            'expires_in'    => isset($response['expires_in']) ? (int)$response['expires_in'] : null,
        ];
    }

    /** Liste (best-effort — voir avertissement en tête de fichier) des documents disponibles. */
    public static function listDocuments(string $accessToken): ?array
    {
        $url = rtrim((string)AppSetting::get('digiposte_base_url'), '/') . (AppSetting::get('digiposte_documents_list_path') ?: '/documents');
        $response = self::request('GET', $url, $accessToken);
        if ($response === null) return null;

        $items = $response['documents'] ?? $response['items'] ?? $response['data'] ?? (array_is_list($response) ? $response : []);
        return array_values(array_filter(array_map([self::class, 'normalizeDocumentItem'], $items)));
    }

    private static function normalizeDocumentItem(array $raw): ?array
    {
        $id = $raw['id'] ?? $raw['documentId'] ?? $raw['uuid'] ?? null;
        $name = $raw['name'] ?? $raw['title'] ?? $raw['filename'] ?? null;
        if (!$id || !$name) return null;
        return [
            'id'   => (string)$id,
            'name' => (string)$name,
            'mime' => $raw['mimeType'] ?? $raw['contentType'] ?? $raw['mime'] ?? 'application/pdf',
        ];
    }

    /** @return array{bytes:string,mime:string}|null */
    public static function downloadDocument(string $accessToken, string $documentId): ?array
    {
        $template = AppSetting::get('digiposte_document_download_path') ?: '/documents/{id}/content';
        $path = str_replace('{id}', rawurlencode($documentId), $template);
        $url = rtrim((string)AppSetting::get('digiposte_base_url'), '/') . $path;

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT        => self::TIMEOUT,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $accessToken],
            CURLOPT_USERAGENT      => 'FamilyBoard/1.0',
        ]);
        $body = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);

        if ($body === false || $status !== 200) return null;
        return ['bytes' => $body, 'mime' => $contentType ?: 'application/pdf'];
    }

    private static function request(string $method, string $url, ?string $accessToken, array $postFields = [], bool $formEncoded = false): ?array
    {
        $ch = curl_init($url);
        $headers = ['Accept: application/json'];
        if ($accessToken) $headers[] = 'Authorization: Bearer ' . $accessToken;

        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT        => self::TIMEOUT,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT      => 'FamilyBoard/1.0',
            CURLOPT_HTTPHEADER     => $headers,
        ];
        if ($method === 'POST') {
            $opts[CURLOPT_POST] = true;
            $opts[CURLOPT_POSTFIELDS] = $formEncoded ? http_build_query($postFields) : json_encode($postFields);
            if ($formEncoded) $headers[] = 'Content-Type: application/x-www-form-urlencoded';
            $opts[CURLOPT_HTTPHEADER] = $headers;
        }
        curl_setopt_array($ch, $opts);
        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $status < 200 || $status >= 300) return null;
        try {
            return json_decode($response, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return null;
        }
    }
}
