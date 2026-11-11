<?php
namespace App\Core;

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

    private static function groupName(int $familyId): string
    {
        return 'family_' . $familyId;
    }

    private static function mountPoint(int $familyId): string
    {
        return 'familyboard_family_' . $familyId;
    }

    private static function request(string $method, string $path, array $params = []): array
    {
        $settings = StorageSettings::get();
        if (!$settings['nextcloud_url'] || !$settings['nextcloud_admin_user']) {
            return ['ok' => false, 'status' => 0, 'data' => null, 'error' => 'Nextcloud non configuré.'];
        }
        $url = rtrim($settings['nextcloud_url'], '/') . '/apps/groupfolders' . $path;
        $ch = curl_init();
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => self::TIMEOUT,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_USERPWD        => $settings['nextcloud_admin_user'] . ':' . $settings['nextcloud_admin_password'],
            CURLOPT_HTTPHEADER     => ['OCS-APIRequest: true', 'Accept: application/json'],
            CURLOPT_USERAGENT      => 'FamilyBoard/1.0',
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
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
        if ($existing) return $existing;

        $settings = StorageSettings::get();
        $group = self::groupName($familyId);
        $mountPoint = self::mountPoint($familyId);

        if (!NextcloudService::ensureGroup($group, $error)) return null;
        if (!NextcloudService::addUserToGroup($settings['nextcloud_admin_user'], $group, $error)) return null;

        $create = self::request('POST', '/folders', ['mountpoint' => $mountPoint]);
        if (!$create['ok']) {
            $error = 'Création du Group Folder échouée : ' . ($create['error'] ?? 'erreur inconnue');
            return null;
        }
        $folderId = (int)($create['data']['id'] ?? 0);
        if (!$folderId) {
            $error = 'Création du Group Folder : identifiant introuvable dans la réponse Nextcloud.';
            return null;
        }

        $assign = self::request('POST', "/folders/$folderId/groups", ['group' => $group]);
        if (!$assign['ok']) {
            $error = 'Attribution du groupe au Group Folder échouée : ' . ($assign['error'] ?? 'erreur inconnue');
            return null;
        }

        FamilyNextcloudFolder::create($familyId, $group, $folderId, $mountPoint);
        self::applyFamilyQuota($familyId, $error);

        return FamilyNextcloudFolder::findByFamily($familyId);
    }

    /** Ajoute un membre déjà provisionné LDAP au groupe de sa famille (sans quoi il ne verrait
     *  jamais le Group Folder partagé apparaître dans son propre espace Nextcloud). */
    public static function addMemberToFamilyGroup(int $familyId, string $ldapUsername, ?string &$error = null): bool
    {
        $folder = self::ensureFamilyFolder($familyId, $error);
        if (!$folder) return false;
        return NextcloudService::addUserToGroup($ldapUsername, $folder['nextcloud_group'], $error);
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
