<?php
namespace App\Models;

use App\Core\Database;

class Album
{
    public static function getByFamily(int $familyId): array
    {
        return Database::fetchAll(
            "SELECT a.*, u.name as user_name, u.color as user_color,
             (SELECT COUNT(*) FROM album_photos p WHERE p.album_id=a.id) as photo_count,
             (SELECT image_path FROM album_photos p WHERE p.album_id=a.id ORDER BY p.created_at DESC LIMIT 1) as cover_path
             FROM albums a JOIN users u ON u.id=a.user_id
             WHERE a.family_id=? ORDER BY a.created_at DESC",
            [$familyId]
        );
    }

    public static function getById(int $id): ?array
    {
        return Database::fetch(
            'SELECT a.*, u.name as user_name, u.color as user_color FROM albums a JOIN users u ON u.id=a.user_id WHERE a.id=?',
            [$id]
        );
    }

    public static function create(int $familyId, int $userId, string $title, string $description = ''): int
    {
        return Database::insert(
            'INSERT INTO albums (family_id, user_id, title, description) VALUES (?,?,?,?)',
            [$familyId, $userId, $title, $description ?: null]
        );
    }

    public static function update(int $id, string $title, string $description = ''): void
    {
        Database::execute('UPDATE albums SET title=?, description=? WHERE id=?', [$title, $description ?: null, $id]);
    }

    public static function delete(int $id): void
    {
        Database::execute('DELETE FROM albums WHERE id=?', [$id]);
    }

    /** Un album peut être partagé avec plusieurs enfants (donc plusieurs accès co-parent) à la
     *  fois — liste de schedule_id en CSV, même convention que Invitation::create(). */
    public static function setCustodySchedules(int $id, array $scheduleIds): void
    {
        $scheduleIds = array_values(array_unique(array_map('intval', $scheduleIds)));
        Database::execute('UPDATE albums SET custody_schedule_ids=? WHERE id=?', [$scheduleIds ? implode(',', $scheduleIds) : null, $id]);
    }

    /** Albums partagés avec un co-parent restreint, accessibles via ses plannings de garde. */
    public static function getForSchedules(array $scheduleIds): array
    {
        $scheduleIds = array_values(array_unique(array_map('intval', $scheduleIds)));
        if (empty($scheduleIds)) return [];
        $ors = implode(' OR ', array_fill(0, count($scheduleIds), 'FIND_IN_SET(?, a.custody_schedule_ids)'));
        return Database::fetchAll(
            "SELECT a.*, u.name as user_name, u.color as user_color,
             (SELECT COUNT(*) FROM album_photos p WHERE p.album_id=a.id) as photo_count,
             (SELECT image_path FROM album_photos p WHERE p.album_id=a.id ORDER BY p.created_at DESC LIMIT 1) as cover_path
             FROM albums a JOIN users u ON u.id=a.user_id
             WHERE $ors ORDER BY a.created_at DESC",
            $scheduleIds
        );
    }

    /** Un album parmi une liste de plannings accessibles à un co-parent, avec vérification d'accès. */
    public static function getForSchedulesById(int $id, array $scheduleIds): ?array
    {
        $scheduleIds = array_values(array_unique(array_map('intval', $scheduleIds)));
        if (empty($scheduleIds)) return null;
        $ors = implode(' OR ', array_fill(0, count($scheduleIds), 'FIND_IN_SET(?, a.custody_schedule_ids)'));
        return Database::fetch(
            "SELECT a.*, u.name as user_name, u.color as user_color
             FROM albums a JOIN users u ON u.id=a.user_id
             WHERE a.id=? AND ($ors)",
            [$id, ...$scheduleIds]
        );
    }
}
