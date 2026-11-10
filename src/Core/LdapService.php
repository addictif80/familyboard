<?php
namespace App\Core;

use App\Models\AppSetting;
use App\Models\StorageSettings;

/**
 * Client LDAP minimal pour le provisioning du stockage perso contre un serveur LDAP
 * (typiquement le paquet "Serveur LDAP" de Synology DSM) : un compte posixAccount par membre,
 * rattaché au groupe posixGroup configuré (storage_ldap_group, défaut "familyboard") — c'est ce
 * groupe/ces comptes que Synology utilise ensuite pour appliquer un quota par utilisateur sur
 * le dossier partagé (voir App\Core\SynologyService).
 *
 * Utilise l'extension PHP ldap native (ext-ldap) — dégrade proprement (isAvailable()) si elle
 * n'est pas installée, comme les autres intégrations externes de cette appli (Mailcow,
 * Vaultwarden...).
 */
class LdapService
{
    public static function isAvailable(): bool
    {
        return extension_loaded('ldap');
    }

    /** @return resource|\LDAP\Connection|null */
    private static function connect()
    {
        if (!self::isAvailable()) return null;
        $settings = StorageSettings::get();
        if (!$settings || !$settings['ldap_host'] || !$settings['ldap_bind_dn']) return null;

        $conn = ldap_connect($settings['ldap_host']);
        if (!$conn) return null;
        ldap_set_option($conn, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($conn, LDAP_OPT_NETWORK_TIMEOUT, 10);
        if (!@ldap_bind($conn, $settings['ldap_bind_dn'], $settings['ldap_bind_password'])) {
            return null;
        }
        return $conn;
    }

    private static function baseDn(): ?string
    {
        $settings = StorageSettings::get();
        return $settings['ldap_base_dn'] ?? null;
    }

    private static function groupName(): string
    {
        return AppSetting::get('storage_ldap_group', 'familyboard') ?: 'familyboard';
    }

    public static function testConnection(): array
    {
        if (!self::isAvailable()) {
            return ['ok' => false, 'error' => "L'extension PHP ldap n'est pas installée sur ce serveur."];
        }
        $conn = self::connect();
        if (!$conn) {
            return ['ok' => false, 'error' => 'Connexion ou authentification LDAP refusée — vérifiez hôte/DN/mot de passe.'];
        }
        try {
            self::ensureGroup($conn);
        } catch (\Throwable $e) {
            @ldap_unbind($conn);
            return ['ok' => false, 'error' => 'Connecté, mais impossible de créer/lire le groupe "' . self::groupName() . '" : ' . $e->getMessage()];
        }
        @ldap_unbind($conn);
        return ['ok' => true, 'error' => ''];
    }

    /** Crée le groupe posixGroup configuré s'il n'existe pas déjà, et retourne son gidNumber. */
    private static function ensureGroup($conn): int
    {
        $baseDn = self::baseDn();
        $group = self::groupName();
        $groupDn = 'cn=' . ldap_escape($group, '', LDAP_ESCAPE_DN) . ',' . $baseDn;

        $search = @ldap_read($conn, $groupDn, '(objectClass=posixGroup)', ['gidNumber']);
        if ($search) {
            $entries = ldap_get_entries($conn, $search);
            if ($entries['count'] > 0) {
                return (int)$entries[0]['gidnumber'][0];
            }
        }

        $cachedGid = AppSetting::get('storage_ldap_group_gid');
        $gidNumber = $cachedGid ? (int)$cachedGid : 2000;

        $entry = [
            'objectClass' => ['posixGroup', 'top'],
            'cn'          => $group,
            'gidNumber'   => (string)$gidNumber,
        ];
        if (!@ldap_add($conn, $groupDn, $entry)) {
            throw new \RuntimeException(ldap_error($conn));
        }
        AppSetting::set('storage_ldap_group_gid', (string)$gidNumber);
        return $gidNumber;
    }

    /**
     * Alloue le prochain uidNumber disponible, protégé par une transaction SQL (SELECT ... FOR
     * UPDATE) : deux provisionnings concurrents ne doivent jamais recevoir le même uidNumber
     * (collision POSIX silencieuse, un utilisateur héritant des accès d'un autre).
     */
    private static function nextUidNumber(): int
    {
        $pdo = Database::getInstance();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("SELECT `value` FROM app_settings WHERE `key`='storage_ldap_next_uid' FOR UPDATE");
            $stmt->execute();
            $current = (int)($stmt->fetchColumn() ?: 10000);
            $next = $current + 1;
            $pdo->prepare("UPDATE app_settings SET `value`=? WHERE `key`='storage_ldap_next_uid'")->execute([(string)$next]);
            $pdo->commit();
            return $current;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    private static function userDn(string $username): string
    {
        return 'uid=' . ldap_escape($username, '', LDAP_ESCAPE_DN) . ',' . self::baseDn();
    }

    /** SSHA — format de hash de mot de passe LDAP le plus largement supporté (userPassword). */
    private static function sshaHash(string $password): string
    {
        $salt = random_bytes(8);
        $hash = sha1($password . $salt, true);
        return '{SSHA}' . base64_encode($hash . $salt);
    }

    /**
     * Crée le compte LDAP d'un membre s'il n'existe pas, ou met à jour son mot de passe sinon
     * (idempotent — safe à appeler à chaque changement de mot de passe). $username est dérivé
     * de l'e-mail (partie locale), rendu unique si collision. Retourne le uid LDAP retenu
     * (à stocker dans users.ldap_username), ou null si LDAP n'est pas disponible/configuré.
     */
    public static function createOrUpdateUser(int $userId, string $email, string $displayName, string $plainPassword, ?string $existingLdapUsername): ?string
    {
        $conn = self::connect();
        if (!$conn) return null;

        try {
            $gidNumber = self::ensureGroup($conn);
            $username = $existingLdapUsername ?: self::deriveUsername($conn, $email);
            $dn = self::userDn($username);

            $search = @ldap_read($conn, $dn, '(objectClass=posixAccount)', ['uidNumber']);
            $exists = $search && ldap_count_entries($conn, $search) > 0;

            if ($exists) {
                $ok = @ldap_mod_replace($conn, $dn, ['userPassword' => [self::sshaHash($plainPassword)]]);
                if (!$ok) throw new \RuntimeException(ldap_error($conn));
            } else {
                $uidNumber = self::nextUidNumber();
                $entry = [
                    'objectClass'      => ['posixAccount', 'shadowAccount', 'inetOrgPerson', 'top'],
                    'uid'              => $username,
                    'cn'               => $displayName ?: $username,
                    'sn'               => $displayName ?: $username,
                    'mail'             => $email,
                    'uidNumber'        => (string)$uidNumber,
                    'gidNumber'        => (string)$gidNumber,
                    'homeDirectory'    => '/home/' . $username,
                    'loginShell'       => '/bin/false',
                    'userPassword'     => self::sshaHash($plainPassword),
                ];
                if (!@ldap_add($conn, $dn, $entry)) {
                    throw new \RuntimeException(ldap_error($conn));
                }
                self::addToGroup($conn, $username);
            }

            return $username;
        } finally {
            @ldap_unbind($conn);
        }
    }

    private static function addToGroup($conn, string $username): void
    {
        $groupDn = 'cn=' . ldap_escape(self::groupName(), '', LDAP_ESCAPE_DN) . ',' . self::baseDn();
        // memberUid peut déjà contenir d'autres membres : ldap_mod_add ajoute une valeur à
        // l'attribut multi-valué sans toucher aux existantes (contrairement à mod_replace).
        if (!@ldap_mod_add($conn, $groupDn, ['memberUid' => [$username]])) {
            // "attribute or value exists" (déjà membre) n'est pas une erreur à remonter.
            $err = ldap_errno($conn);
            if ($err !== 20 /* LDAP_TYPE_OR_VALUE_EXISTS */) {
                error_log('LdapService::addToGroup failed: ' . ldap_error($conn));
            }
        }
    }

    /** uid LDAP = partie locale de l'e-mail, normalisée, rendue unique en cas de collision
     *  (deux membres de familles différentes avec la même partie locale d'e-mail). */
    private static function deriveUsername($conn, string $email): string
    {
        $base = preg_replace('/[^a-z0-9._-]/', '', strtolower(explode('@', $email)[0])) ?: 'membre';
        $candidate = $base;
        $i = 1;
        while (true) {
            $dn = self::userDn($candidate);
            $search = @ldap_read($conn, $dn, '(objectClass=posixAccount)', ['uid']);
            if (!$search || ldap_count_entries($conn, $search) === 0) {
                return $candidate;
            }
            $candidate = $base . $i;
            $i++;
        }
    }

    /** Supprime le compte LDAP d'un membre (résiliation/suppression de compte) — sans effet si
     *  LDAP n'est pas configuré ou si le compte n'existe déjà plus. */
    public static function deleteUser(string $username): void
    {
        $conn = self::connect();
        if (!$conn) return;
        try {
            @ldap_delete($conn, self::userDn($username));
            $groupDn = 'cn=' . ldap_escape(self::groupName(), '', LDAP_ESCAPE_DN) . ',' . self::baseDn();
            @ldap_mod_del($conn, $groupDn, ['memberUid' => [$username]]);
        } finally {
            @ldap_unbind($conn);
        }
    }
}
