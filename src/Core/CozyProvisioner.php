<?php
namespace App\Core;

use App\Models\CozySettings;

/**
 * Crée/supprime une instance Cozy Cloud par famille, via un petit relais HTTP déployé sur le
 * serveur Cozy Stack (voir deploy/cozy-wrapper/index.php) — l'API admin HTTP native de Cozy Stack
 * n'expose PAS la création/suppression d'instance (seule la commande `cozy-stack instances
 * add|destroy` le permet, exécutée localement sur ce serveur), d'où ce relais minimal : il reçoit
 * un appel authentifié par jeton partagé et exécute la commande pour nous, pour que FamilyBoard
 * n'ait jamais besoin d'un accès SSH à ce serveur.
 */
class CozyProvisioner
{
    private const TIMEOUT = 30;

    private static function request(string $path, array $body): array
    {
        $settings = CozySettings::get();
        if (!$settings) return ['ok' => false, 'error' => 'Intégration Cozy non configurée.'];

        $ch = curl_init($settings['wrapper_url'] . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => self::TIMEOUT,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($body),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Authorization: Bearer ' . $settings['wrapper_token']],
            CURLOPT_USERAGENT      => 'FamilyBoard/1.0',
        ]);
        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false) return ['ok' => false, 'error' => 'Relais Cozy injoignable : ' . $curlError];
        $data = json_decode($response, true);
        if ($status < 200 || $status >= 300) {
            return ['ok' => false, 'error' => (is_array($data) ? ($data['error'] ?? null) : null) ?? "Erreur relais Cozy (HTTP $status)."];
        }
        return ['ok' => true] + (is_array($data) ? $data : []);
    }

    public static function testConnection(): array
    {
        $settings = CozySettings::get();
        if (!$settings) return ['ok' => false, 'error' => 'URL et jeton du relais requis.'];

        $ch = curl_init($settings['wrapper_url'] . '/health');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $settings['wrapper_token']],
        ]);
        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $status !== 200) {
            return ['ok' => false, 'error' => "Relais Cozy injoignable ou jeton invalide (HTTP $status)."];
        }
        return ['ok' => true, 'error' => ''];
    }

    /**
     * @return array{ok:bool, registerToken?:string, error?:string} registerToken sert à
     *  construire le lien d'onboarding (https://$domain/?registerToken=...) envoyé à la famille
     *  pour qu'elle choisisse son mot de passe Cozy — voir CozyController::connect(). Le format
     *  exact de la sortie de `cozy-stack instances add` n'a pas pu être vérifié contre une
     *  instance réelle avant déploiement ; à valider en premier lors de la mise en service (voir
     *  deploy/cozy-wrapper/README.md).
     */
    public static function createInstance(string $domain, string $email): array
    {
        return self::request('/create', ['domain' => $domain, 'email' => $email]);
    }

    public static function destroyInstance(string $domain): array
    {
        return self::request('/destroy', ['domain' => $domain]);
    }
}
