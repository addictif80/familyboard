<?php
namespace App\Models;

use App\Core\Database;

/** Fichier détecté dans le sous-dossier "à rattacher" de l'espace Nextcloud familial, déposé
 *  directement hors FamilyBoard — voir App\Core\NextcloudInboxSync (détection) et
 *  App\Controllers\NextcloudInboxController (rattachement à un module). */
class NextcloudInboxFile
{
    public static function findByFamilyAndPath(int $familyId, string $path): ?array
    {
        return Database::fetch('SELECT * FROM nextcloud_inbox_files WHERE family_id=? AND remote_path=?', [$familyId, $path]);
    }

    public static function create(int $familyId, array $item, ?int $uploaderId): int
    {
        return Database::insert(
            'INSERT INTO nextcloud_inbox_files (family_id, remote_path, original_name, nextcloud_file_id, nextcloud_etag, size_bytes, added_by_user_id)
             VALUES (?,?,?,?,?,?,?)',
            [$familyId, $item['path'], $item['name'], $item['file_id'], $item['etag'], $item['size'], $uploaderId]
        );
    }

    public static function getById(int $id): ?array
    {
        return Database::fetch('SELECT * FROM nextcloud_inbox_files WHERE id=?', [$id]);
    }

    /** En attente, visibles pour $userId : toutes pour un admin, seulement celles qu'il a
     *  lui-même déposées sinon (voir NextcloudInboxSync — résolu via ldap_username). */
    public static function getPendingForUser(int $familyId, int $userId, bool $isAdmin): array
    {
        $sql = "SELECT i.*, u.name as uploader_name FROM nextcloud_inbox_files i
                LEFT JOIN users u ON u.id = i.added_by_user_id
                WHERE i.family_id=? AND i.status='pending'";
        $params = [$familyId];
        if (!$isAdmin) {
            $sql .= ' AND i.added_by_user_id = ?';
            $params[] = $userId;
        }
        $sql .= ' ORDER BY i.discovered_at DESC';
        return Database::fetchAll($sql, $params);
    }

    public static function countPending(int $familyId): int
    {
        $row = Database::fetch("SELECT COUNT(*) as n FROM nextcloud_inbox_files WHERE family_id=? AND status='pending'", [$familyId]);
        return (int)($row['n'] ?? 0);
    }

    public static function markResolved(int $id, string $status, ?string $module, ?int $recordId): void
    {
        Database::execute(
            'UPDATE nextcloud_inbox_files SET status=?, target_module=?, target_record_id=?, resolved_at=NOW() WHERE id=?',
            [$status, $module, $recordId, $id]
        );
    }
}
