<?php
namespace App\Models;

use App\Core\Database;

class PortalLink
{
    public static function getByFamily(int $familyId, ?string $status = null): array
    {
        $sql = 'SELECT l.*, s.name as submitted_by_name, r.name as reviewed_by_name
                FROM portal_links l
                JOIN users s ON s.id = l.submitted_by
                LEFT JOIN users r ON r.id = l.reviewed_by
                WHERE l.family_id = ?';
        $params = [$familyId];
        if ($status) {
            $sql .= ' AND l.status = ?';
            $params[] = $status;
        }
        $sql .= ' ORDER BY l.status ASC, l.click_count DESC, l.created_at DESC';
        return Database::fetchAll($sql, $params);
    }

    /** Liens approuvés et marqués visibles pour un accès co-parent, pour un ensemble de familles. */
    public static function getCoparentVisibleForFamilies(array $familyIds): array
    {
        $familyIds = array_values(array_unique(array_map('intval', $familyIds)));
        if (empty($familyIds)) return [];
        $ph = implode(',', array_fill(0, count($familyIds), '?'));
        return Database::fetchAll(
            "SELECT l.*, f.name as family_name FROM portal_links l
             JOIN families f ON f.id = l.family_id
             WHERE l.family_id IN ($ph) AND l.status='approved' AND l.visible_to_coparent=1
             ORDER BY l.click_count DESC, l.created_at DESC",
            $familyIds
        );
    }

    public static function getById(int $id): ?array
    {
        return Database::fetch('SELECT * FROM portal_links WHERE id = ?', [$id]);
    }

    public static function create(int $familyId, int $userId, array $data, string $status): int
    {
        return Database::insert(
            'INSERT INTO portal_links (family_id, title, url, description, image_path, status, visible_to_coparent, submitted_by, reviewed_by, reviewed_at)
             VALUES (?,?,?,?,?,?,?,?,?,?)',
            [
                $familyId,
                $data['title'],
                $data['url'],
                $data['description'] ?: null,
                $data['image_path'] ?? null,
                $status,
                !empty($data['visible_to_coparent']) ? 1 : 0,
                $userId,
                $status === 'approved' ? $userId : null,
                $status === 'approved' ? gmdate('Y-m-d H:i:s') : null,
            ]
        );
    }

    public static function update(int $id, array $data): void
    {
        Database::execute(
            'UPDATE portal_links SET title=?, description=?, visible_to_coparent=? WHERE id=?',
            [$data['title'], $data['description'] ?: null, !empty($data['visible_to_coparent']) ? 1 : 0, $id]
        );
    }

    public static function approve(int $id, int $reviewerId): void
    {
        Database::execute(
            "UPDATE portal_links SET status='approved', reviewed_by=?, reviewed_at=NOW(), rejection_reason=NULL WHERE id=?",
            [$reviewerId, $id]
        );
    }

    public static function reject(int $id, int $reviewerId, string $reason): void
    {
        Database::execute(
            "UPDATE portal_links SET status='rejected', reviewed_by=?, reviewed_at=NOW(), rejection_reason=? WHERE id=?",
            [$reviewerId, $reason ?: null, $id]
        );
    }

    public static function delete(int $id): void
    {
        Database::execute('DELETE FROM portal_links WHERE id=?', [$id]);
    }

    public static function registerClick(int $id): void
    {
        Database::execute('UPDATE portal_links SET click_count = click_count + 1 WHERE id=?', [$id]);
    }
}
