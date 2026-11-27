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

    /** Portée d'affichage dans l'onglet "Photos" du mur social : NULL retire l'album du mur
     *  (comportement par défaut), sinon même portée qu'une publication (famille/amis/familles amies). */
    public static function setWallScope(int $id, ?string $scope): void
    {
        Database::execute('UPDATE albums SET wall_scope=? WHERE id=?', [$scope, $id]);
    }

    /**
     * Albums visibles dans l'onglet "Photos" du mur pour le viewer : mêmes règles de portée
     * que Post::getVisibleForUser() (famille → ma famille, amis → abonnements, familles amies →
     * ma famille + mes familles amies), restreintes aux albums dont le propriétaire a choisi de
     * les afficher sur le mur (wall_scope non NULL).
     */
    public static function getWallVisibleForUser(int $familyId, array $visibleAuthorIds, array $friendFamilyIds): array
    {
        $authorIds = array_values(array_unique(array_map('intval', $visibleAuthorIds)));
        $friendIds = array_values(array_unique(array_map('intval', $friendFamilyIds)));
        $authorPh = $authorIds ? implode(',', array_fill(0, count($authorIds), '?')) : '0';
        $friendPh = $friendIds ? implode(',', array_fill(0, count($friendIds), '?')) : '0';
        return Database::fetchAll(
            "SELECT a.*, u.name as user_name, u.color as user_color,
             (SELECT COUNT(*) FROM album_photos p WHERE p.album_id=a.id) as photo_count
             FROM albums a JOIN users u ON u.id=a.user_id
             WHERE (
               (a.wall_scope='family' AND a.family_id=?)
               OR (a.wall_scope='personal' AND a.user_id IN ($authorPh))
               OR (a.wall_scope='network' AND (a.family_id=? OR a.family_id IN ($friendPh)))
             )
             ORDER BY a.created_at DESC",
            [$familyId, ...$authorIds, $familyId, ...$friendIds]
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
