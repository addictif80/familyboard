<?php
namespace App\Core;

use App\Models\StorageSettings;

/**
 * Client minimal pour l'API OCS Provisioning de Nextcloud (authentification Basic avec un
 * compte admin, ou de préférence un mot de passe d'application Nextcloud dédié) — utilisé
 * uniquement pour appliquer un quota par utilisateur, jamais pour gérer des fichiers.
 *
 * Le compte Nextcloud lui-même n'est pas créé par FamilyBoard dans le cas courant : si
 * Nextcloud est configuré (côté NAS, hors de cette appli) pour s'authentifier contre le même
 * Serveur LDAP Synology (voir LdapService), le compte apparaît de lui-même à la première
 * connexion. ensureUserAndQuota() le crée quand même de façon proactive si besoin (pour que le
 * quota soit déjà en place avant la première connexion), sans jamais définir de mot de passe
 * utilisable (le compte s'authentifie via LDAP, pas via Nextcloud).
 */
class NextcloudService
{
    private const TIMEOUT = 15;

    private static function request(string $method, string $path, array $params = []): array
    {
        $settings = StorageSettings::get();
        if (!$settings['nextcloud_url'] || !$settings['nextcloud_admin_user']) {
            return ['ok' => false, 'status' => 0, 'error' => 'Nextcloud non configuré.'];
        }

        $url = rtrim($settings['nextcloud_url'], '/') . '/ocs/v1.php' . $path;
        $ch = curl_init();
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => self::TIMEOUT,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_USERPWD        => $settings['nextcloud_admin_user'] . ':' . $settings['nextcloud_admin_password'],
            CURLOPT_HTTPHEADER     => ['OCS-APIRequest: true', 'Accept: application/json'],
            CURLOPT_USERAGENT      => 'FamilyBoard/1.0',
            // Certains reverse-proxies devant Nextcloud renvoient un en-tête "Upgrade: h2"
            // invalide une fois la connexion négociée en HTTP/2 via ALPN (l'en-tête Upgrade
            // n'existe qu'en HTTP/1.1) — curl rejette alors la réponse ("Invalid HTTP header
            // field... name: [upgrade], value: [h2]"). Forcer HTTP/1.1 évite cette négociation.
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
        ];
        if ($method !== 'GET' && !empty($params)) {
            $opts[CURLOPT_POSTFIELDS] = http_build_query($params);
        } elseif (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        $url .= (str_contains($url, '?') ? '&' : '?') . 'format=json';
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt_array($ch, $opts);

        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            return ['ok' => false, 'status' => 0, 'error' => 'Connexion impossible : ' . $curlError];
        }
        $body = json_decode($response, true);
        $statuscode = (int)($body['ocs']['meta']['statuscode'] ?? 0);
        $ok = $status >= 200 && $status < 300 && in_array($statuscode, [100, 200], true);
        return [
            'ok'     => $ok,
            'status' => $status,
            'data'   => $body['ocs']['data'] ?? null,
            'error'  => $ok ? '' : ($body['ocs']['meta']['message'] ?? "Erreur API Nextcloud (HTTP $status)."),
        ];
    }

    public static function testConnection(): array
    {
        $settings = StorageSettings::get();
        if (!$settings['nextcloud_url'] || !$settings['nextcloud_admin_user']) {
            return ['ok' => false, 'error' => 'URL et identifiants administrateur Nextcloud requis.'];
        }
        // Vérifie que le compte admin est valide ET a les droits d'administration des quotas.
        $r = self::request('GET', '/cloud/users/' . rawurlencode($settings['nextcloud_admin_user']));
        if (!$r['ok']) {
            return ['ok' => false, 'error' => $r['error'] ?: 'Connexion refusée — vérifiez URL/utilisateur/mot de passe.'];
        }
        return ['ok' => true, 'error' => ''];
    }

    private static function userExists(string $username): bool
    {
        $r = self::request('GET', '/cloud/users/' . rawurlencode($username));
        return $r['ok'];
    }

    /** Recherche un compte par uid, en vue du diagnostic admin ("tester un compte"). */
    public static function findUser(string $username): array
    {
        $r = self::request('GET', '/cloud/users/' . rawurlencode($username));
        return $r;
    }

    /** $error est renseigné avec le motif exact en cas d'échec, pour remonter jusqu'au panneau
     *  admin sans consulter les journaux serveur. */
    public static function setUserQuota(string $username, int $quotaGb, ?string &$error = null): bool
    {
        if (!self::userExists($username)) {
            // Pré-provisionne le compte (sans mot de passe utilisable, voir docblock de classe)
            // pour que le quota soit déjà en place avant la première connexion via LDAP.
            $create = self::request('POST', '/cloud/users', [
                'userid'   => $username,
                'password' => bin2hex(random_bytes(24)),
            ]);
            if (!$create['ok']) {
                $error = 'Création du compte Nextcloud échouée : ' . ($create['error'] ?? 'erreur inconnue');
                return false;
            }
        }
        $r = self::request('PUT', '/cloud/users/' . rawurlencode($username) . '/quota', [
            'key'   => 'quota',
            'value' => $quotaGb . ' GB',
        ]);
        if (!$r['ok']) $error = $r['error'] ?? 'Erreur Nextcloud inconnue.';
        return $r['ok'];
    }

    /** Désactive le compte (ne le supprime pas — conserve ses fichiers pour une éventuelle
     *  réintégration, cohérent avec le reste de l'appli qui ne supprime jamais de contenu
     *  familial à l'insu de l'admin). */
    public static function disableUser(string $username): void
    {
        self::request('PUT', '/cloud/users/' . rawurlencode($username) . '/disable');
    }
}
