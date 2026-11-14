<?php
namespace App\Core;

/**
 * Enregistrement dynamique d'un client OAuth2 sur une instance Cozy Cloud (RFC 7591, propre à
 * chaque instance : pas de registre centralisé) — nécessaire pour obtenir un client_id avant de
 * générer un jeton via CozyProvisioner::mintToken() (cozy-stack instances token-oauth), qui
 * remplace le parcours navigateur /auth/authorize habituel : aucune interaction de la famille.
 * Cf. https://docs.cozy.io/en/cozy-stack/auth/.
 */
class CozyOAuthClient
{
    private const TIMEOUT = 20;

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
        return ['ok' => true, 'client_id' => $data['client_id']];
    }
}
