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
        // "message" existe parfois mais vide (ex. un rejet par une politique de mot de passe
        // sans message dédié) — ?? ne couvre que la clé absente/null, jamais une chaîne vide.
        $message = trim($body['ocs']['meta']['message'] ?? '');
        return [
            'ok'     => $ok,
            'status' => $status,
            'data'   => $body['ocs']['data'] ?? null,
            'error'  => $ok ? '' : ($message !== '' ? $message : "Erreur API Nextcloud (HTTP $status, code OCS $statuscode)."),
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

    /**
     * S'assure que le compte technique (admin) affiche "FamilyBoard" plutôt que son nom de
     * compte brut — vérifié contre une instance réelle : pour un calendrier ou un Group Folder
     * partagé, Nextcloud affiche toujours "(<nom affiché du propriétaire>)" au destinataire du
     * partage (comportement natif, non contournable), donc un compte technique sans nom
     * affiché expose directement son identifiant brut (ex. "admin") à chaque membre de chaque
     * famille. Idempotent (ne réapplique rien si déjà correct) et appelé en best-effort depuis
     * NextcloudGroupFolderService::ensureFamilyFolder() — jamais bloquant pour le provisioning.
     */
    public static function ensureTechnicalAccountDisplayName(string $displayName = 'FamilyBoard'): void
    {
        $settings = StorageSettings::get();
        if (!$settings['nextcloud_url'] || !$settings['nextcloud_admin_user']) return;

        $current = self::findUser($settings['nextcloud_admin_user']);
        if (!$current['ok'] || ($current['data']['displayname'] ?? '') === $displayName) return;

        self::request('PUT', '/cloud/users/' . rawurlencode($settings['nextcloud_admin_user']), [
            'key'   => 'display',
            'value' => $displayName,
        ]);
    }

    private static function groupExists(string $groupId): bool
    {
        $r = self::request('GET', '/cloud/groups/' . rawurlencode($groupId));
        return $r['ok'];
    }

    /** Crée le groupe Nextcloud s'il n'existe pas déjà (idempotent) — voir
     *  App\Core\NextcloudGroupFolderService, qui rattache ce groupe à l'espace familial partagé
     *  (Group Folder) d'une famille FamilyBoard. */
    public static function ensureGroup(string $groupId, ?string &$error = null): bool
    {
        if (self::groupExists($groupId)) return true;
        $r = self::request('POST', '/cloud/groups', ['groupid' => $groupId]);
        if (!$r['ok']) $error = $r['error'] ?? 'Erreur Nextcloud inconnue.';
        return $r['ok'];
    }

    /** Supprime un groupe Nextcloud — best-effort (RGPD, famille supprimée), sans effet si déjà
     *  absent. */
    public static function deleteGroup(string $groupId): bool
    {
        $r = self::request('DELETE', '/cloud/groups/' . rawurlencode($groupId));
        return $r['ok'] || $r['status'] === 404;
    }

    /** Ajoute un compte à un groupe — idempotent (ajouter un membre déjà présent est sans effet
     *  néfaste côté Nextcloud). Utilisé à la fois pour les membres de la famille et pour le
     *  compte technique admin lui-même (qui doit être membre du groupe pour que le Group Folder
     *  se monte dans son propre espace DAV, voir NextcloudGroupFolderService). */
    public static function addUserToGroup(string $username, string $groupId, ?string &$error = null): bool
    {
        $r = self::request('POST', '/cloud/users/' . rawurlencode($username) . '/groups', ['groupid' => $groupId]);
        if (!$r['ok']) $error = $r['error'] ?? 'Erreur Nextcloud inconnue.';
        return $r['ok'];
    }

    /** Mot de passe aléatoire respectant la politique de complexité par défaut de Nextcloud
     *  (app "Password Policy" : majuscule + minuscule + chiffre + caractère spécial) — jamais
     *  utilisable de toute façon (voir docblock de classe, le compte s'authentifie via LDAP),
     *  seule sa capacité à passer la validation de création de compte compte. */
    private static function randomCompliantPassword(): string
    {
        $lower = 'abcdefghijkmnpqrstuvwxyz';
        $upper = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
        $digits = '23456789';
        $special = '!@#$%^&*-_=+';
        $all = $lower . $upper . $digits . $special;

        $password = $lower[random_int(0, strlen($lower) - 1)]
            . $upper[random_int(0, strlen($upper) - 1)]
            . $digits[random_int(0, strlen($digits) - 1)]
            . $special[random_int(0, strlen($special) - 1)];
        for ($i = 0; $i < 20; $i++) {
            $password .= $all[random_int(0, strlen($all) - 1)];
        }
        return str_shuffle($password);
    }

    /** $unlimited : envoie la valeur spéciale "none" (quota illimité natif Nextcloud) et ignore
     *  $quotaGb. $error est renseigné avec le motif exact en cas d'échec, pour remonter jusqu'au
     *  panneau admin sans consulter les journaux serveur. */
    public static function setUserQuota(string $username, int $quotaGb, bool $unlimited, ?string &$error = null): bool
    {
        if (!self::userExists($username)) {
            // Pré-provisionne le compte (sans mot de passe utilisable, voir docblock de classe)
            // pour que le quota soit déjà en place avant la première connexion via LDAP.
            $create = self::request('POST', '/cloud/users', [
                'userid'   => $username,
                'password' => self::randomCompliantPassword(),
            ]);
            if (!$create['ok']) {
                $error = 'Création du compte Nextcloud échouée : ' . ($create['error'] ?? 'erreur inconnue');
                return false;
            }
        }
        // L'endpoint est PUT /cloud/users/{userId} avec key=quota dans le corps — PAS
        // /cloud/users/{userId}/quota (ce chemin matche en réalité la route OCS
        // editUserMultiValue, qui ne permet de modifier que l'e-mail, pas le quota, et
        // renvoie un statuscode 103 sans message exploitable — vérifié contre le code
        // source réel de provisioning_api/UsersController).
        $r = self::request('PUT', '/cloud/users/' . rawurlencode($username), [
            'key'   => 'quota',
            'value' => $unlimited ? 'none' : ($quotaGb . ' GB'),
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
