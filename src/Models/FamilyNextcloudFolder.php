<?php
namespace App\Models;

use App\Core\Database;

/** Groupe/Group Folder Nextcloud provisionné pour l'espace familial partagé d'une famille —
 *  voir App\Core\NextcloudGroupFolderService (création) et App\Core\FileStore (lecture/écriture
 *  du contenu une fois le flag storage_nextcloud_files_enabled actif). */
class FamilyNextcloudFolder
{
    public static function findByFamily(int $familyId): ?array
    {
        return Database::fetch('SELECT * FROM family_nextcloud_folders WHERE family_id = ?', [$familyId]);
    }

    public static function create(int $familyId, string $group, int $groupFolderId, string $mountPoint): void
    {
        Database::execute(
            'INSERT INTO family_nextcloud_folders (family_id, nextcloud_group, groupfolder_id, mount_point) VALUES (?,?,?,?)',
            [$familyId, $group, $groupFolderId, $mountPoint]
        );
    }

    /** À appeler uniquement après un renommage réussi côté Nextcloud (voir
     *  NextcloudGroupFolderService::ensureReadableMountPoint) — cette colonne doit toujours
     *  refléter le mount_point réel côté serveur, utilisé tel quel comme segment d'URL WebDAV
     *  par NextcloudFileClient. */
    public static function updateMountPoint(int $familyId, string $mountPoint): void
    {
        Database::execute(
            'UPDATE family_nextcloud_folders SET mount_point = ? WHERE family_id = ?',
            [$mountPoint, $familyId]
        );
    }
}
