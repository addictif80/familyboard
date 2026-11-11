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
 * Toute recherche d'un compte/groupe existant se fait par une recherche LDAP (scope sous-arbre,
 * depuis le DN de base) et jamais par une lecture d'un DN reconstruit à la main : un serveur
 * comme le Serveur LDAP Synology place les comptes/groupes dans un conteneur dédié
 * (typiquement "cn=users,<DN de base>" / "cn=group,<DN de base>"), pas directement sous le DN
 * de base — deviner ce chemin ferait échouer silencieusement toute détection d'un compte déjà
 * existant. Seule la CRÉATION d'un compte/groupe tout neuf a besoin d'un DN cible explicite
 * (storage_ldap_people_dn / storage_ldap_groups_dn, par défaut le DN de base lui-même).
 *
 * Utilise l'extension PHP ldap native (ext-ldap) — dégrade proprement (isAvailable()) si elle
 * n'est pas installée, comme les autres intégrations externes de cette appli (Mailcow,
 * Vaultwarden...).
 */

/** uid LDAP attendu déjà pris par un compte d'un e-mail différent — voir
 *  LdapService::deriveUsername(), résolu uniquement par une décision admin explicite. */
class LdapUsernameConflictException extends \RuntimeException {}

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

    /** DN sous lequel un NOUVEAU compte est créé — configurable (storage_ldap_people_dn) pour
     *  les serveurs qui exigent un conteneur dédié (ex. "cn=users,<DN de base>" chez Synology),
     *  retombe sur le DN de base si non renseigné. Sans effet sur la détection d'un compte déjà
     *  existant, qui cherche toujours dans tout l'arbre (voir docblock de classe). */
    private static function peopleCreationDn(): ?string
    {
        return AppSetting::get('storage_ldap_people_dn') ?: self::baseDn();
    }

    private static function groupsCreationDn(): ?string
    {
        return AppSetting::get('storage_ldap_groups_dn') ?: self::baseDn();
    }

    private static function groupName(): string
    {
        return AppSetting::get('storage_ldap_group', 'familyboard') ?: 'familyboard';
    }

    /** Groupe LDAP propre à une famille (distinct du groupe global "familyboard", qui reste
     *  utilisé pour le provisioning Synology) — même convention de nom que le groupe Nextcloud
     *  équivalent (voir NextcloudGroupFolderService::groupName), pour qu'un admin retrouve
     *  facilement la correspondance entre les deux systèmes. Permet, côté Nextcloud, de mapper
     *  nativement ce groupe LDAP pour déléguer la gestion des Group Folders famille par famille
     *  plutôt que via un compte admin global. */
    private static function familyGroupName(int $familyId): string
    {
        return 'family_' . $familyId;
    }

    /** Sous-groupe des administrateurs d'une famille — distinct du groupe famille complet, pour
     *  pouvoir déléguer la gestion Nextcloud (Group Folders) aux seuls admins de la famille,
     *  jamais à tous ses membres. */
    private static function familyAdminGroupName(int $familyId): string
    {
        return 'family_' . $familyId . '_admins';
    }

    /** Groupe plat (toutes familles confondues) réunissant tous les admins de famille — c'est à
     *  CE groupe, et uniquement lui, qu'il faut déléguer côté Nextcloud le niveau "Accès API" de
     *  Group Folders (jamais le niveau "admin délégué", qui donnerait accès à TOUS les Group
     *  Folders du serveur). Le cloisonnement entre familles vient ensuite du fait que chaque
     *  Group Folder n'autorise la gestion qu'au groupe family_N_admins de SA famille (voir
     *  NextcloudGroupFolderService::ensureManageAcl) — ce groupe-ci n'est que la porte d'entrée,
     *  jamais un accès en lui-même. Volontairement distinct de groupName()
     *  (storage_ldap_group) : celui-ci sert au provisioning Synology (tous les membres), pas à
     *  une délégation d'administration Nextcloud.
     */
    private static function nextcloudDelegationGroupName(): string
    {
        return 'familyboard_admins';
    }

    /** Recherche sous-arbre depuis le DN de base — trouve un compte/groupe quel que soit le
     *  conteneur dans lequel le serveur LDAP l'a réellement placé. Retourne ses attributs
     *  (toujours avec 'dn') ou null si rien ne correspond. */
    private static function searchOne($conn, string $filter, array $attrs): ?array
    {
        $baseDn = self::baseDn();
        if (!$baseDn) return null;
        // 'dn' n'est pas demandé explicitement : ldap_get_entries() le renseigne toujours pour
        // chaque entrée, et certains serveurs rejettent une requête d'attribut "dn" littéral.
        $search = @ldap_search($conn, $baseDn, $filter, $attrs);
        if (!$search || ldap_count_entries($conn, $search) === 0) return null;
        return ldap_get_entries($conn, $search)[0];
    }

    private static function findAccount($conn, string $username, array $attrs = []): ?array
    {
        $filter = '(&(objectClass=posixAccount)(uid=' . ldap_escape($username, '', LDAP_ESCAPE_FILTER) . '))';
        return self::searchOne($conn, $filter, $attrs);
    }

    private static function findGroup($conn, string $groupName, array $attrs = []): ?array
    {
        $filter = '(&(objectClass=posixGroup)(cn=' . ldap_escape($groupName, '', LDAP_ESCAPE_FILTER) . '))';
        return self::searchOne($conn, $filter, $attrs);
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

    /**
     * Diagnostic admin : recherche un uid donné dans tout l'arbre sous le DN de base et rapporte
     * s'il existe, ses attributs POSIX, et s'il est bien membre du groupe configuré — vérifie
     * bind + recherche + groupe de bout en bout sans dépendre de connaître le mot de passe de ce
     * compte (voir AdminController::testStorageLdapAccount, "tester un compte en tapant son nom").
     */
    public static function lookupUser(string $username): array
    {
        if (!self::isAvailable()) {
            return ['ok' => false, 'found' => false, 'error' => "L'extension PHP ldap n'est pas installée sur ce serveur."];
        }
        $conn = self::connect();
        if (!$conn) {
            return ['ok' => false, 'found' => false, 'error' => 'Connexion ou authentification LDAP refusée — vérifiez hôte/DN/mot de passe.'];
        }
        try {
            $entry = self::findAccount($conn, $username, ['uid', 'uidNumber', 'gidNumber', 'mail', 'cn']);
            if (!$entry) {
                return ['ok' => true, 'found' => false, 'error' => ''];
            }

            $groupEntry = self::findGroup($conn, self::groupName(), ['memberUid']);
            $inGroup = false;
            if ($groupEntry && isset($groupEntry['memberuid'])) {
                $count = (int)($groupEntry['memberuid']['count'] ?? 0);
                for ($i = 0; $i < $count; $i++) {
                    if (strtolower($groupEntry['memberuid'][$i]) === strtolower($username)) {
                        $inGroup = true;
                        break;
                    }
                }
            }

            return [
                'ok'       => true,
                'found'    => true,
                'error'    => '',
                'dn'       => $entry['dn'] ?? '',
                'uid'      => $entry['uid'][0] ?? $username,
                'cn'       => $entry['cn'][0] ?? '',
                'mail'     => $entry['mail'][0] ?? '',
                'uidNumber' => $entry['uidnumber'][0] ?? '',
                'gidNumber' => $entry['gidnumber'][0] ?? '',
                'inGroup'  => $inGroup,
            ];
        } finally {
            @ldap_unbind($conn);
        }
    }

    /** Crée le groupe posixGroup configuré s'il n'existe pas déjà (recherché dans tout l'arbre,
     *  jamais par un DN deviné), et retourne son gidNumber. */
    private static function ensureGroup($conn): int
    {
        $group = self::groupName();
        $entry = self::findGroup($conn, $group, ['gidNumber']);
        if ($entry) {
            return (int)$entry['gidnumber'][0];
        }

        $cachedGid = AppSetting::get('storage_ldap_group_gid');
        $gidNumber = $cachedGid ? (int)$cachedGid : 2000;

        $groupDn = 'cn=' . ldap_escape($group, '', LDAP_ESCAPE_DN) . ',' . self::groupsCreationDn();
        $newEntry = [
            'objectClass' => ['posixGroup', 'top'],
            'cn'          => $group,
            'gidNumber'   => (string)$gidNumber,
        ];
        if (!@ldap_add($conn, $groupDn, $newEntry)) {
            throw new \RuntimeException(ldap_error($conn));
        }
        AppSetting::set('storage_ldap_group_gid', (string)$gidNumber);
        return $gidNumber;
    }

    /** Prochain gidNumber disponible pour un groupe famille/admin — compteur dédié (distinct de
     *  storage_ldap_group_gid et de nextUidNumber) protégé par la même transaction SQL que
     *  nextUidNumber(), pour éviter toute collision entre deux provisionnings concurrents. */
    private static function nextFamilyGidNumber(): int
    {
        $pdo = Database::getInstance();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("SELECT `value` FROM app_settings WHERE `key`='storage_ldap_next_family_gid' FOR UPDATE");
            $stmt->execute();
            $current = (int)($stmt->fetchColumn() ?: 3000);
            $next = $current + 1;
            $pdo->prepare("INSERT INTO app_settings (`key`,`value`) VALUES ('storage_ldap_next_family_gid', ?)
                ON DUPLICATE KEY UPDATE `value` = ?")->execute([(string)$next, (string)$next]);
            $pdo->commit();
            return $current;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /** Crée (si besoin) un groupe posixGroup générique — factorise ensureFamilyGroup() et
     *  ensureFamilyAdminGroup(), qui ne diffèrent que par le nom du groupe. */
    private static function ensureNamedGroup($conn, string $groupName): int
    {
        $entry = self::findGroup($conn, $groupName, ['gidNumber']);
        if ($entry) {
            return (int)$entry['gidnumber'][0];
        }
        $gidNumber = self::nextFamilyGidNumber();
        $groupDn = 'cn=' . ldap_escape($groupName, '', LDAP_ESCAPE_DN) . ',' . self::groupsCreationDn();
        $newEntry = [
            'objectClass' => ['posixGroup', 'top'],
            'cn'          => $groupName,
            'gidNumber'   => (string)$gidNumber,
        ];
        if (!@ldap_add($conn, $groupDn, $newEntry)) {
            throw new \RuntimeException(ldap_error($conn));
        }
        return $gidNumber;
    }

    private static function ensureFamilyGroup($conn, int $familyId): int
    {
        return self::ensureNamedGroup($conn, self::familyGroupName($familyId));
    }

    private static function ensureFamilyAdminGroup($conn, int $familyId): int
    {
        return self::ensureNamedGroup($conn, self::familyAdminGroupName($familyId));
    }

    /** Ajoute $username à un groupe nommé, idempotent (voir addToGroup — même logique,
     *  factorisée pour les groupes famille/admin). */
    private static function addToNamedGroup($conn, string $username, string $groupName): void
    {
        $groupEntry = self::findGroup($conn, $groupName);
        if (!$groupEntry) return;
        if (!@ldap_mod_add($conn, $groupEntry['dn'], ['memberUid' => [$username]])) {
            $err = ldap_errno($conn);
            if ($err !== 20 /* LDAP_TYPE_OR_VALUE_EXISTS */) {
                error_log("LdapService::addToNamedGroup ($groupName) failed: " . ldap_error($conn));
            }
        }
    }

    /** Retire $username d'un groupe nommé — sans effet (pas une erreur) s'il n'en était pas
     *  membre. */
    private static function removeFromNamedGroup($conn, string $username, string $groupName): void
    {
        $groupEntry = self::findGroup($conn, $groupName);
        if (!$groupEntry) return;
        if (!@ldap_mod_del($conn, $groupEntry['dn'], ['memberUid' => [$username]])) {
            $err = ldap_errno($conn);
            if ($err !== 16 /* LDAP_NO_SUCH_ATTRIBUTE (pas membre) */) {
                error_log("LdapService::removeFromNamedGroup ($groupName) failed: " . ldap_error($conn));
            }
        }
    }

    /**
     * Rattache (ou détache, si $isAdmin devient faux) $username au groupe famille et à son
     * sous-groupe admin — appelé par createOrUpdateUser() à chaque provisioning, et séparément
     * par SettingsController lors d'une promotion/rétrogradation (sans toucher au mot de passe).
     * Best-effort : sans effet si LDAP n'est pas configuré.
     */
    public static function syncFamilyRole(string $username, int $familyId, bool $isAdmin): void
    {
        $conn = self::connect();
        if (!$conn) return;
        try {
            self::ensureFamilyGroup($conn, $familyId);
            self::addToNamedGroup($conn, $username, self::familyGroupName($familyId));

            self::ensureFamilyAdminGroup($conn, $familyId);
            $adminGroup = self::familyAdminGroupName($familyId);
            self::ensureNamedGroup($conn, self::nextcloudDelegationGroupName());
            if ($isAdmin) {
                self::addToNamedGroup($conn, $username, $adminGroup);
                self::addToNamedGroup($conn, $username, self::nextcloudDelegationGroupName());
            } else {
                self::removeFromNamedGroup($conn, $username, $adminGroup);
                self::removeFromNamedGroup($conn, $username, self::nextcloudDelegationGroupName());
            }
        } finally {
            @ldap_unbind($conn);
        }
    }

    /**
     * Diagnostic pour la migration des comptes créés avant cette intégration (voir
     * AdminController::analyzeStorageLdapMigration) : calcule le uid que recevrait ce membre et
     * indique s'il existe déjà, et si oui si son e-mail correspond (→ sera adopté
     * automatiquement à la prochaine connexion) ou diffère (→ conflit nécessitant une décision
     * admin). N'écrit jamais rien.
     *
     * @return array{candidate:string, exists:bool, mail:?string, matches:bool, uidNumber:?string}
     */
    public static function analyzeExistingAccount(string $email): array
    {
        $candidate = self::baseUsernameFor($email);
        $lookup = self::lookupUser($candidate);
        if (!$lookup['ok'] || !$lookup['found']) {
            return ['candidate' => $candidate, 'exists' => false, 'mail' => null, 'matches' => false, 'uidNumber' => null];
        }
        return [
            'candidate' => $candidate,
            'exists'    => true,
            'mail'      => $lookup['mail'] ?? null,
            'matches'   => strtolower($lookup['mail'] ?? '') === strtolower($email),
            'uidNumber' => $lookup['uidNumber'] ?? null,
        ];
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
     * (à stocker dans users.ldap_username), ou null si LDAP n'est pas disponible/configuré —
     * $error est alors renseigné avec le motif exact, pour qu'il remonte jusqu'au panneau admin
     * (voir ProvisioningJob::markFailed) sans avoir à aller chercher dans les journaux serveur.
     */
    public static function createOrUpdateUser(int $userId, string $email, string $displayName, string $plainPassword, ?string $existingLdapUsername, ?string &$error = null, ?int $familyId = null, bool $isAdmin = false): ?string
    {
        if (!self::isAvailable()) {
            $error = "L'extension PHP ldap n'est pas installée sur ce serveur.";
            return null;
        }
        $conn = self::connect();
        if (!$conn) {
            $error = 'Connexion ou authentification LDAP refusée — vérifiez hôte/DN/mot de passe.';
            return null;
        }

        try {
            $gidNumber = self::ensureGroup($conn);
            try {
                $username = $existingLdapUsername ?: self::deriveUsername($conn, $email);
            } catch (LdapUsernameConflictException $e) {
                // Un compte existe déjà sous le nom attendu, pour un e-mail différent — décision
                // réservée à l'admin (voir AdminController::analyzeStorageLdapMigration), jamais
                // résolu automatiquement par un suffixe arbitraire qui créerait un second compte
                // sans que personne ne le sache.
                $error = $e->getMessage();
                error_log('LdapService: conflit de compte pour ' . $email . ' (' . $e->getMessage() . ')');
                return null;
            }

            $existing = self::findAccount($conn, $username);

            if ($existing) {
                $ok = @ldap_mod_replace($conn, $existing['dn'], ['userPassword' => [self::sshaHash($plainPassword)]]);
                if (!$ok) throw new \RuntimeException(ldap_error($conn));
                self::addToGroup($conn, $username); // idempotent — au cas où le compte a été adopté (voir deriveUsername) sans être déjà dans le groupe
            } else {
                $uidNumber = self::nextUidNumber();
                $dn = 'uid=' . ldap_escape($username, '', LDAP_ESCAPE_DN) . ',' . self::peopleCreationDn();
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

            if ($familyId !== null) {
                self::ensureFamilyGroup($conn, $familyId);
                self::addToNamedGroup($conn, $username, self::familyGroupName($familyId));

                self::ensureFamilyAdminGroup($conn, $familyId);
                $adminGroup = self::familyAdminGroupName($familyId);
                self::ensureNamedGroup($conn, self::nextcloudDelegationGroupName());
                if ($isAdmin) {
                    self::addToNamedGroup($conn, $username, $adminGroup);
                    self::addToNamedGroup($conn, $username, self::nextcloudDelegationGroupName());
                } else {
                    self::removeFromNamedGroup($conn, $username, $adminGroup);
                    self::removeFromNamedGroup($conn, $username, self::nextcloudDelegationGroupName());
                }
            }

            return $username;
        } finally {
            @ldap_unbind($conn);
        }
    }

    private static function addToGroup($conn, string $username): void
    {
        $groupEntry = self::findGroup($conn, self::groupName());
        if (!$groupEntry) return; // ne devrait pas arriver : ensureGroup() vient d'être appelé par l'appelant
        // memberUid peut déjà contenir d'autres membres : ldap_mod_add ajoute une valeur à
        // l'attribut multi-valué sans toucher aux existantes (contrairement à mod_replace).
        if (!@ldap_mod_add($conn, $groupEntry['dn'], ['memberUid' => [$username]])) {
            // "attribute or value exists" (déjà membre) n'est pas une erreur à remonter.
            $err = ldap_errno($conn);
            if ($err !== 20 /* LDAP_TYPE_OR_VALUE_EXISTS */) {
                error_log('LdapService::addToGroup failed: ' . ldap_error($conn));
            }
        }
    }

    /** uid LDAP = partie locale de l'e-mail, normalisée — calcul pur, sans accès LDAP (utilisé
     *  aussi par le diagnostic admin de migration, voir analyzeExistingAccount()). */
    public static function baseUsernameFor(string $email): string
    {
        return preg_replace('/[^a-z0-9._-]/', '', strtolower(explode('@', $email)[0])) ?: 'membre';
    }

    /**
     * uid LDAP = partie locale de l'e-mail. Si ce nom existe déjà (recherché dans tout l'arbre,
     * jamais par un DN deviné) : adopté tel quel si son attribut mail correspond exactement à
     * l'e-mail du membre (même personne migrée depuis un compte créé avant cette intégration —
     * ni mot de passe ni groupe touchés au-delà de ce que createOrUpdateUser fait normalement),
     * sinon conflit explicite — jamais résolu silencieusement par un suffixe qui créerait un
     * second compte à l'insu de l'admin.
     */
    private static function deriveUsername($conn, string $email): string
    {
        $base = self::baseUsernameFor($email);
        $entry = self::findAccount($conn, $base, ['mail']);
        if (!$entry) {
            return $base;
        }
        $existingMail = strtolower($entry['mail'][0] ?? '');
        if ($existingMail === strtolower($email)) {
            return $base;
        }
        throw new LdapUsernameConflictException(
            "uid \"$base\" déjà utilisé par le compte LDAP $existingMail (différent de $email)"
        );
    }

    /** Supprime le compte LDAP d'un membre (résiliation/suppression de compte) — sans effet si
     *  LDAP n'est pas configuré ou si le compte n'existe déjà plus. */
    public static function deleteUser(string $username, ?int $familyId = null): void
    {
        $conn = self::connect();
        if (!$conn) return;
        try {
            $entry = self::findAccount($conn, $username);
            if ($entry) @ldap_delete($conn, $entry['dn']);

            $groupEntry = self::findGroup($conn, self::groupName());
            if ($groupEntry) @ldap_mod_del($conn, $groupEntry['dn'], ['memberUid' => [$username]]);

            if ($familyId !== null) {
                self::removeFromNamedGroup($conn, $username, self::familyGroupName($familyId));
                self::removeFromNamedGroup($conn, $username, self::familyAdminGroupName($familyId));
            }
        } finally {
            @ldap_unbind($conn);
        }
    }

    /**
     * Diagnostic de cohérence (voir AdminController::auditLdapConsistency) : pour un compte LDAP
     * donné, indique s'il appartient réellement au groupe famille et, si attendu, au sous-groupe
     * admin — sans rien modifier. Utilisé pour vérifier qu'aucun membre n'a été omis après une
     * migration ou un changement de rôle.
     */
    public static function checkFamilyMembership(string $username, int $familyId): array
    {
        $empty = ['inFamilyGroup' => false, 'inAdminGroup' => false, 'inDelegationGroup' => false];
        if (!self::isAvailable()) {
            return ['ok' => false, 'error' => "L'extension PHP ldap n'est pas installée sur ce serveur."] + $empty;
        }
        $conn = self::connect();
        if (!$conn) {
            return ['ok' => false, 'error' => 'Connexion ou authentification LDAP refusée.'] + $empty;
        }
        try {
            return [
                'ok'                => true,
                'error'             => '',
                'inFamilyGroup'     => self::isMemberOfNamedGroup($conn, $username, self::familyGroupName($familyId)),
                'inAdminGroup'      => self::isMemberOfNamedGroup($conn, $username, self::familyAdminGroupName($familyId)),
                'inDelegationGroup' => self::isMemberOfNamedGroup($conn, $username, self::nextcloudDelegationGroupName()),
            ];
        } finally {
            @ldap_unbind($conn);
        }
    }

    private static function isMemberOfNamedGroup($conn, string $username, string $groupName): bool
    {
        $groupEntry = self::findGroup($conn, $groupName, ['memberUid']);
        if (!$groupEntry || !isset($groupEntry['memberuid'])) return false;
        $count = (int)($groupEntry['memberuid']['count'] ?? 0);
        for ($i = 0; $i < $count; $i++) {
            if (strtolower($groupEntry['memberuid'][$i]) === strtolower($username)) return true;
        }
        return false;
    }
}
