<?php
namespace App\Core;

use App\Models\StorageSettings;

/**
 * Client minimal pour l'API Web DSM d'un NAS Synology, utilisé uniquement pour appliquer un
 * quota par utilisateur LDAP (voir App\Core\LdapService) sur le dossier partagé configuré
 * (SYNO.Core.Quota) — jamais pour gérer des fichiers, cette appli n'écrit jamais directement
 * sur le NAS, seulement la comptabilité applicative (voir App\Core\FileStore) côté serveur web.
 *
 * Authentification par session DSM (SYNO.API.Auth), comme App\Core\Mailcow pour son API.
 */
class SynologyService
{
    private const TIMEOUT = 15;

    private static function request(string $api, string $method, string $version, array $params, ?string $sid = null): array
    {
        $settings = StorageSettings::get();
        if (!$settings || !$settings['synology_url']) {
            return ['ok' => false, 'error' => 'Synology non configuré.'];
        }
        $query = array_merge($params, [
            'api'     => $api,
            'method'  => $method,
            'version' => $version,
        ]);
        if ($sid) $query['_sid'] = $sid;

        $url = rtrim($settings['synology_url'], '/') . '/webapi/entry.cgi?' . http_build_query($query);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => self::TIMEOUT,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT      => 'FamilyBoard/1.0',
            // Même précaution que NextcloudService : un reverse-proxy devant le DSM peut
            // renvoyer un en-tête "Upgrade: h2" invalide une fois négocié en HTTP/2 via ALPN.
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
        ]);
        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            return ['ok' => false, 'error' => 'Connexion au NAS impossible : ' . $curlError];
        }
        $body = json_decode($response, true);
        if (!is_array($body) || empty($body['success'])) {
            $code = $body['error']['code'] ?? 0;
            return ['ok' => false, 'error' => 'Erreur API Synology (code ' . $code . ').'];
        }
        return ['ok' => true, 'data' => $body['data'] ?? []];
    }

    /** Session DSM — un sid par appel (pas de cache process-long : ce service n'est utilisé que
     *  par des jobs cron ponctuels, jamais sur une requête web à forte fréquence). */
    private static function login(): ?string
    {
        $settings = StorageSettings::get();
        if (!$settings || !$settings['synology_username']) return null;

        $r = self::request('SYNO.API.Auth', 'login', '3', [
            'account' => $settings['synology_username'],
            'passwd'  => $settings['synology_password'],
            'session' => 'FamilyBoard',
            'format'  => 'sid',
        ]);
        return $r['ok'] ? ($r['data']['sid'] ?? null) : null;
    }

    private static function logout(string $sid): void
    {
        self::request('SYNO.API.Auth', 'logout', '1', ['session' => 'FamilyBoard'], $sid);
    }

    public static function testConnection(): array
    {
        $settings = StorageSettings::get();
        if (!$settings || !$settings['synology_url'] || !$settings['synology_username']) {
            return ['ok' => false, 'error' => 'URL, utilisateur et mot de passe Synology requis.'];
        }
        $sid = self::login();
        if (!$sid) {
            return ['ok' => false, 'error' => 'Connexion refusée — vérifiez URL/utilisateur/mot de passe.'];
        }
        if (!$settings['synology_shared_folder']) {
            self::logout($sid);
            return ['ok' => false, 'error' => 'Connecté, mais aucun dossier partagé renseigné pour les quotas.'];
        }
        self::logout($sid);
        return ['ok' => true, 'error' => ''];
    }

    /**
     * Pousse le quota (Go) d'un utilisateur LDAP sur le dossier partagé configuré. Sans effet
     * (retourne false) si Synology n'est pas configuré — appelant responsable de rejouer le job
     * plus tard (voir App\Models\ProvisioningJob). $error est renseigné avec le motif exact en
     * cas d'échec, pour remonter jusqu'au panneau admin sans consulter les journaux serveur.
     */
    public static function setUserQuota(string $username, int $quotaGb, ?string &$error = null): bool
    {
        $settings = StorageSettings::get();
        if (!$settings || !$settings['synology_shared_folder']) {
            $error = 'Synology non configuré (URL/dossier partagé manquant).';
            return false;
        }

        $sid = self::login();
        if (!$sid) {
            $error = 'Connexion Synology refusée — vérifiez URL/utilisateur/mot de passe.';
            return false;
        }

        try {
            $r = self::request('SYNO.Core.Quota', 'set', '1', [
                'name'      => $username,
                'type'      => 'user',
                'volume_path' => $settings['synology_shared_folder'],
                'limit'     => (string)($quotaGb * 1024 * 1024 * 1024),
            ], $sid);
            if (!$r['ok']) $error = $r['error'] ?? 'Erreur Synology inconnue.';
            return $r['ok'];
        } finally {
            self::logout($sid);
        }
    }
}
