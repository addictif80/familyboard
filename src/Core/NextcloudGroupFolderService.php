<?php
namespace App\Core;

use App\Models\Family;
use App\Models\FamilyNextcloudFolder;
use App\Models\StorageSettings;

/**
 * Client minimal pour l'API de l'app Nextcloud "Group Folders" (à activer manuellement par
 * l'administrateur sur le serveur Nextcloud — FamilyBoard ne peut pas l'activer à distance),
 * utilisé pour provisionner un dossier partagé en lecture/écriture par famille, avec son propre
 * quota natif indépendant (voir applyFamilyQuota — -3 = illimité, constante
 * \OCP\Files\FileInfo::SPACE_UNLIMITED côté Nextcloud, vérifiée contre le code source réel).
 *
 * Contrairement à NextcloudService (API OCS, préfixe /ocs/v1.php), les routes Group Folders
 * vivent directement sous /apps/groupfolders/... (vérifié contre une instance réelle).
 *
 * Le compte technique (admin) est systématiquement ajouté au groupe de la famille : un Group
 * Folder ne se monte dans l'espace DAV d'un compte que s'il est membre du groupe assigné — sans
 * cette étape, FileStore ne pourrait pas lire/écrire via WebDAV en tant qu'admin (vérifié contre
 * une instance réelle : PROPFIND renvoie 404 tant que l'admin n'est pas dans le groupe).
 */
class NextcloudGroupFolderService
{
    private const TIMEOUT = 15;

    /** Identifiant technique du groupe — jamais modifié après création (un groupe Nextcloud ne
     *  peut pas être renommé sans le recréer, ce qui perdrait son rattachement au Group Folder et
     *  à ses membres) ; voir ensureGroupDisplayName() pour le nom lisible affiché à l'admin. */
    private static function groupName(int $familyId): string
    {
        return 'family_' . $familyId;
    }

    /** Même convention que LdapService::familyAdminGroupName — c'est à ce groupe (synchronisé
     *  côté LDAP, synchronisé côté Nextcloud via son app LDAP) qu'est déléguée la gestion de
     *  CE Group Folder précis, voir ensureManageAcl(). */
    private static function adminGroupName(int $familyId): string
    {
        return 'family_' . $familyId . '_admins';
    }

    /** ASCII, minuscules, séparé par des tirets — utilisé à la fois pour le mount_point (qui
     *  tolère des espaces/accents, mais reste plus sûr à transmettre en ASCII pur sur un chemin
     *  WebDAV) et comme base du nom de groupe lisible. */
    private static function slug(string $text): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT', $text);
        $ascii = strtolower($ascii !== false ? $ascii : $text);
        $ascii = preg_replace('/[^a-z0-9]+/', '-', $ascii);
        $ascii = trim($ascii, '-');
        return $ascii !== '' ? $ascii : 'famille';
    }

    /** Nom de famille lisible pour le mount_point (voir docblock de classe : contrairement au
     *  nom de groupe, le mount_point d'un Group Folder se renomme librement via l'API sans aucun
     *  risque pour les fichiers déjà stockés — seul le libellé affiché change). L'id reste
     *  présent en suffixe pour garantir l'unicité même si deux familles portent le même nom
     *  (vérifié : Nextcloud rejette la création d'un second Group Folder avec un mount_point déjà
     *  pris, HTTP 400). */
    private static function mountPoint(int $familyId): string
    {
        $family = Family::findById($familyId);
        $slug = $family ? self::slug($family['name']) : 'famille';
        return 'famille-' . $slug . '-' . $familyId;
    }

    private static function request(string $method, string $path, array $params = []): array
    {
        $settings = StorageSettings::get();
        if (!$settings['nextcloud_url'] || !$settings['nextcloud_admin_user']) {
            return ['ok' => false, 'status' => 0, 'data' => null, 'error' => 'Nextcloud non configuré.'];
        }
        // Le préfixe /index.php/ est requis : vérifié en conditions réelles (Nextcloud 34 /
        // GroupFolders 22), une requête non-GET envoyée sans ce préfixe est mal routée côté
        // serveur et dispatchée vers le contrôleur GET (liste des dossiers) au lieu du bon
        // contrôleur (création, attribution de groupe, quota, suppression...), qui échoue donc
        // silencieusement à chaque fois (seules les requêtes GET, qui tombent "par accident" sur
        // le bon contrôleur, fonctionnaient).
        $url = rtrim($settings['nextcloud_url'], '/') . '/index.php/apps/groupfolders' . $path;
        $ch = curl_init();
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => self::TIMEOUT,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_USERPWD        => $settings['nextcloud_admin_user'] . ':' . $settings['nextcloud_admin_password'],
            CURLOPT_HTTPHEADER     => ['OCS-APIRequest: true', 'Accept: application/json'],
            CURLOPT_USERAGENT      => 'FamilyBoard/1.0',
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            // Une connexion persistante réutilisée à travers un reverse-proxy mal configuré peut
            // désynchroniser requête/réponse (la réponse d'un appel précédent "fuit" dans le
            // suivant) — observé en conditions réelles (deux familles traitées à la suite dans un
            // même run recevaient des réponses croisées). Force une connexion neuve à chaque appel.
            CURLOPT_FORBID_REUSE   => true,
            CURLOPT_FRESH_CONNECT  => true,
        ];
        if (!empty($params)) $opts[CURLOPT_POSTFIELDS] = http_build_query($params);
        $url .= (str_contains($url, '?') ? '&' : '?') . 'format=json';
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt_array($ch, $opts);

        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            return ['ok' => false, 'status' => 0, 'data' => null, 'error' => 'Connexion impossible : ' . $curlError];
        }
        $body = json_decode($response, true);
        $statuscode = (int)($body['ocs']['meta']['statuscode'] ?? 0);
        $ok = $status >= 200 && $status < 300 && in_array($statuscode, [100, 200], true);
        $message = trim($body['ocs']['meta']['message'] ?? '');
        return [
            'ok'     => $ok,
            'status' => $status,
            'data'   => $body['ocs']['data'] ?? null,
            'error'  => $ok ? '' : ($message !== '' ? $message : "Erreur API Group Folders (HTTP $status, code OCS $statuscode)."),
        ];
    }

    public static function testConnection(): array
    {
        $r = self::request('GET', '/folders');
        if (!$r['ok']) {
            return ['ok' => false, 'error' => $r['error'] ?: "Connexion refusée — vérifiez que l'app Group Folders est activée sur le serveur Nextcloud."];
        }
        return ['ok' => true, 'error' => ''];
    }

    /**
     * Provisionne (si besoin) le groupe + Group Folder d'une famille, idempotent — retourne les
     * infos existantes si déjà provisionné (voir family_nextcloud_folders). Ajoute aussi le
     * compte technique admin au groupe (sans quoi il ne peut pas monter/écrire dans le dossier,
     * vérifié contre une instance réelle).
     */
    public static function ensureFamilyFolder(int $familyId, ?string &$error = null): ?array
    {
        $existing = FamilyNextcloudFolder::findByFamily($familyId);
        if ($existing) {
            // Un membre provisionné LDAP APRÈS la création du Group Folder (ou avant l'activation
            // de cette fonctionnalité) n'était sinon jamais ajouté au groupe : ce chemin ne
            // s'exécutait qu'une fois, à la création — vérifié en situation réelle (deux membres
            // déjà provisionnés avant l'activation du backend fichiers Nextcloud n'étaient jamais
            // rattrapés). Désormais revérifié à chaque appel (idempotent côté Nextcloud, sans
            // effet néfaste si déjà membre).
            self::ensureAllMembersInGroup($familyId, $existing['nextcloud_group']);
            self::ensureReadableNaming($familyId, $existing);
            self::ensureAdminDelegationGroups($familyId, self::adminGroupName($familyId));
            self::ensureManageAcl($familyId, (int)$existing['groupfolder_id']);
            return $existing;
        }

        $settings = StorageSettings::get();
        $group = self::groupName($familyId);
        $mountPoint = self::mountPoint($familyId);

        try {
            NextcloudService::ensureTechnicalAccountDisplayName();
            self::ensureGroupDisplayName($group, $familyId);
        } catch (\Throwable $e) {
            // Cosmétique uniquement (voir docblock de la méthode) — jamais bloquant.
        }

        if (!NextcloudService::ensureGroup($group, $error)) return null;
        if (!NextcloudService::addUserToGroup($settings['nextcloud_admin_user'], $group, $error)) return null;

        $create = self::request('POST', '/folders', ['mountpoint' => $mountPoint]);
        $folderId = (int)($create['data']['id'] ?? 0);
        if (!$folderId) {
            // Avant d'abandonner (que la création ait échoué — ex. mountpoint déjà pris par une
            // tentative antérieure dont seule l'étape suivante avait échoué, voir ci-dessous — ou
            // qu'elle ait réussi sans id exploitable dans la réponse) : le dossier peut déjà
            // exister réellement côté Nextcloud, on vérifie directement la liste plutôt que de
            // supposer un échec.
            $list = self::request('GET', '/folders');
            foreach (($list['data'] ?? []) as $existingFolder) {
                if (($existingFolder['mount_point'] ?? null) === $mountPoint) {
                    $folderId = (int)($existingFolder['id'] ?? 0);
                    break;
                }
            }
        }
        if (!$folderId) {
            // Ni la création, ni la liste des dossiers existants n'ont permis de trouver ce
            // mountpoint — diagnostic complet journalisé pour identifier la cause exacte côté
            // serveur réel.
            $raw = json_encode($create);
            error_log("NextcloudGroupFolderService: impossible de créer ou retrouver le Group Folder (famille $familyId, mountpoint $mountPoint) : $raw");
            $error = 'Création du Group Folder échouée et dossier introuvable dans la liste existante : ' . ($create['error'] ?: mb_substr($raw, 0, 500));
            return null;
        }

        $assign = self::request('POST', "/folders/$folderId/groups", ['group' => $group]);
        if (!$assign['ok']) {
            $error = 'Attribution du groupe au Group Folder échouée : ' . ($assign['error'] ?? 'erreur inconnue');
            return null;
        }

        FamilyNextcloudFolder::create($familyId, $group, $folderId, $mountPoint);
        self::applyFamilyQuota($familyId, $error);
        self::ensureAllMembersInGroup($familyId, $group);
        self::ensureAdminDelegationGroups($familyId, self::adminGroupName($familyId));
        self::ensureManageAcl($familyId, $folderId);

        return FamilyNextcloudFolder::findByFamily($familyId);
    }

    /**
     * Délègue la gestion de CE Group Folder précis au groupe family_N_admins de sa famille (voir
     * App\Core\LdapService::nextcloudDelegationGroupName pour le schéma complet) — un admin de
     * famille, membre du groupe plat "familyboard_admins" délégué côté Nextcloud au niveau
     * "Accès API" (jamais "admin délégué"), ne verra/ne pourra gérer QUE les Group Folders où son
     * propre groupe apparaît dans cette liste (filtrage vérifié dans le code source réel de
     * l'app Group Folders, OCA\GroupFolders\Service\FoldersFilter::getForApiUser) — jamais les
     * Group Folders des autres familles. Best-effort, jamais bloquant : sans effet si l'appel
     * échoue (log seulement), un rattrapage ultérieur (resync) le retentera.
     */
    private static function ensureManageAcl(int $familyId, int $folderId): void
    {
        $r = self::request('POST', "/folders/$folderId/manageACL", [
            'mappingType' => 'group',
            'mappingId'   => self::adminGroupName($familyId),
            'manageAcl'   => '1',
        ]);
        if (!$r['ok']) {
            error_log("NextcloudGroupFolderService: délégation manageACL échouée (famille $familyId, dossier $folderId) : " . ($r['error'] ?? 'erreur inconnue'));
        }
    }

    /** Affecte au groupe Nextcloud un nom affiché lisible (le nom réel de la famille) sans
     *  toucher à son identifiant technique immuable — best-effort : l'API de renommage des
     *  groupes n'existe que depuis Nextcloud 25, donc sans effet (silencieux) sur une instance
     *  plus ancienne. */
    private static function ensureGroupDisplayName(string $group, int $familyId): void
    {
        $family = Family::findById($familyId);
        if (!$family) return;
        NextcloudService::setGroupDisplayName($group, $family['name']);
    }

    /**
     * Crée/peuple family_N_admins et familyboard_admins comme groupes NATIFS Nextcloud (API OCS,
     * comme le groupe family_N lui-même) — PAS via la synchronisation LDAP. Observé en
     * conditions réelles : sur cette instance, Nextcloud ne "découvre" jamais un groupe LDAP qui
     * n'a aucun équivalent natif déjà connu (contrairement aux comptes, où un uid LDAP sans
     * collision finit par se résoudre), rendant la délégation (voir ensureManageAcl)
     * silencieusement inopérante si on ne compte que sur LDAP. Les groupes LDAP de même nom
     * restent créés par ailleurs (voir LdapService) pour l'annuaire admin et une éventuelle
     * utilisation future, mais ne sont plus le chemin dont dépend Nextcloud ici.
     */
    /** Point d'entrée public — appelé par SettingsController lors d'une promotion/rétrogradation,
     *  pour que la délégation Nextcloud suive immédiatement le changement de rôle sans attendre
     *  le prochain passage de "Resynchroniser les groupes Nextcloud". Sans effet si la famille
     *  n'a pas encore de Group Folder provisionné (rien à déléguer). */
    public static function syncAdminDelegation(int $familyId): void
    {
        if (!FamilyNextcloudFolder::findByFamily($familyId)) return;
        self::ensureAdminDelegationGroups($familyId, self::adminGroupName($familyId));
    }

    private static function ensureAdminDelegationGroups(int $familyId, string $adminGroup): void
    {
        $delegationGroup = \App\Core\LdapService::nextcloudDelegationGroupNamePublic();
        NextcloudService::ensureGroup($adminGroup);
        NextcloudService::ensureGroup($delegationGroup);

        foreach (Database::fetchAll('SELECT ldap_username, nextcloud_username, role FROM users WHERE family_id = ? AND ldap_username IS NOT NULL', [$familyId]) as $row) {
            // Voir users.nextcloud_username : rare cas de collision d'uid où Nextcloud a dû
            // renommer en interne son compte (suffixe numérique) — le uid LDAP seul ne
            // correspondrait alors plus à aucun compte Nextcloud réel.
            $username = $row['nextcloud_username'] ?: $row['ldap_username'];
            try {
                if ($row['role'] === 'admin') {
                    NextcloudService::addUserToGroup($username, $adminGroup);
                    NextcloudService::addUserToGroup($username, $delegationGroup);
                } else {
                    NextcloudService::removeUserFromGroup($username, $adminGroup);
                    NextcloudService::removeUserFromGroup($username, $delegationGroup);
                }
            } catch (\Throwable $e) {
                error_log('NextcloudGroupFolderService: ensureAdminDelegationGroups failed for ' . $username . ' (family ' . $familyId . '): ' . $e->getMessage());
            }
        }
    }

    /** Rattrape, pour une famille déjà provisionnée avant l'introduction de noms lisibles, le
     *  nom de groupe affiché et le mount_point du Group Folder — best-effort, idempotent (ne
     *  renomme rien si déjà à jour). Le mount_point se renomme sans risque pour les fichiers déjà
     *  stockés (voir mountPoint()), mais la ligne family_nextcloud_folders doit être mise à jour
     *  dans la foulée : NextcloudFileClient l'utilise tel quel comme segment d'URL WebDAV. */
    private static function ensureReadableNaming(int $familyId, array $existing): void
    {
        try {
            self::ensureGroupDisplayName($existing['nextcloud_group'], $familyId);

            $desired = self::mountPoint($familyId);
            if ($existing['mount_point'] === $desired) return;

            $rename = self::request('PUT', "/folders/{$existing['groupfolder_id']}", ['mountPoint' => $desired]);
            if ($rename['ok']) {
                FamilyNextcloudFolder::updateMountPoint($familyId, $desired);
            }
        } catch (\Throwable $e) {
            error_log("NextcloudGroupFolderService: renommage lisible échoué (famille $familyId): " . $e->getMessage());
        }
    }

    /** Ajoute au groupe de la famille tout membre déjà provisionné LDAP (ldap_username non nul)
     *  — best-effort par membre : un échec isolé (compte Nextcloud pas encore synchronisé côté
     *  LDAP du NAS, par exemple) ne doit jamais bloquer les autres membres ni l'appelant. */
    private static function ensureAllMembersInGroup(int $familyId, string $group): void
    {
        foreach (Database::fetchAll('SELECT ldap_username, nextcloud_username FROM users WHERE family_id = ? AND ldap_username IS NOT NULL', [$familyId]) as $row) {
            $username = $row['nextcloud_username'] ?: $row['ldap_username'];
            try {
                NextcloudService::addUserToGroup($username, $group);
            } catch (\Throwable $e) {
                error_log('NextcloudGroupFolderService: addUserToGroup failed for ' . $username . ' (family ' . $familyId . '): ' . $e->getMessage());
            }
        }
    }

    /** Ajoute un membre déjà provisionné LDAP au groupe de sa famille (sans quoi il ne verrait
     *  jamais le Group Folder partagé apparaître dans son propre espace Nextcloud). */
    /** $nextcloudUsername : identifiant Nextcloud réel du membre (voir users.nextcloud_username
     *  — généralement identique au uid LDAP, sauf rare collision d'uid résolue par Nextcloud). */
    public static function addMemberToFamilyGroup(int $familyId, string $nextcloudUsername, ?string &$error = null): bool
    {
        $folder = self::ensureFamilyFolder($familyId, $error);
        if (!$folder) return false;
        return NextcloudService::addUserToGroup($nextcloudUsername, $folder['nextcloud_group'], $error);
    }

    /** Applique le quota familial courant (storage_family_quota_gb, ou illimité — voir
     *  StorageQuota::familyQuotaUnlimited) au Group Folder déjà provisionné de cette famille. */
    public static function applyFamilyQuota(int $familyId, ?string &$error = null): bool
    {
        $folder = FamilyNextcloudFolder::findByFamily($familyId);
        if (!$folder) {
            $error = "Group Folder pas encore provisionné pour cette famille.";
            return false;
        }
        $quota = StorageQuota::familyQuotaUnlimited($familyId)
            ? -3 // \OCP\Files\FileInfo::SPACE_UNLIMITED — vérifié contre le code source Nextcloud réel
            : StorageQuota::familyQuotaGb($familyId) * 1024 * 1024 * 1024;

        $r = self::request('POST', "/folders/{$folder['groupfolder_id']}/quota", ['quota' => $quota]);
        if (!$r['ok']) $error = $r['error'] ?? 'Erreur Group Folders inconnue.';
        return $r['ok'];
    }

    /** Supprime entièrement le Group Folder et le groupe dédié d'une famille supprimée (RGPD,
     *  voir AccountDeletion::deleteFamily) — best-effort, jamais bloquant pour la suppression en
     *  cours, et à appeler AVANT la suppression de la ligne `families` (ON DELETE CASCADE sur
     *  family_nextcloud_folders effacerait sinon mount_point/groupfolder_id avant qu'on ait pu
     *  nettoyer côté Nextcloud lui-même). */
    public static function deleteFamilyFolder(int $familyId): void
    {
        $folder = FamilyNextcloudFolder::findByFamily($familyId);
        if (!$folder) return;
        self::request('DELETE', "/folders/{$folder['groupfolder_id']}");
        NextcloudService::deleteGroup($folder['nextcloud_group']);
    }

    /** Usage/quota actuels du Group Folder (octets) — lu nativement depuis Nextcloud, qui
     *  comptabilise aussi les fichiers ajoutés directement depuis son interface (pas seulement
     *  via FamilyBoard), contrairement à un décompte local sur stored_files. */
    public static function usage(int $familyId): ?array
    {
        $folder = FamilyNextcloudFolder::findByFamily($familyId);
        if (!$folder) return null;
        $r = self::request('GET', "/folders/{$folder['groupfolder_id']}");
        if (!$r['ok']) return null;
        return ['size' => (int)($r['data']['size'] ?? 0), 'quota' => (int)($r['data']['quota'] ?? 0)];
    }
}
