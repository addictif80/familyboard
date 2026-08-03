<?php
namespace App\Models;

use App\Core\Database;

class PortalLink
{
    /** Liens d'une famille + liens "certifiés" globaux (family_id NULL) ajoutés par
     *  l'administrateur système, diffusés dans le portail de toutes les familles. */
    public static function getByFamily(int $familyId, ?string $status = null): array
    {
        $sql = 'SELECT l.*, s.name as submitted_by_name, r.name as reviewed_by_name
                FROM portal_links l
                LEFT JOIN users s ON s.id = l.submitted_by
                LEFT JOIN users r ON r.id = l.reviewed_by
                WHERE (l.family_id = ? OR l.family_id IS NULL)';
        $params = [$familyId];
        if ($status) {
            $sql .= ' AND l.status = ?';
            $params[] = $status;
        }
        $sql .= ' ORDER BY l.certified DESC, l.status ASC, l.click_count DESC, l.created_at DESC';
        return Database::fetchAll($sql, $params);
    }

    /** Liens approuvés et marqués visibles pour un accès co-parent, pour un ensemble de
     *  familles — inclut aussi les liens certifiés globaux. */
    public static function getCoparentVisibleForFamilies(array $familyIds): array
    {
        $familyIds = array_values(array_unique(array_map('intval', $familyIds)));
        if (empty($familyIds)) return [];
        $ph = implode(',', array_fill(0, count($familyIds), '?'));
        return Database::fetchAll(
            "SELECT l.*, f.name as family_name FROM portal_links l
             LEFT JOIN families f ON f.id = l.family_id
             WHERE (l.family_id IN ($ph) OR l.family_id IS NULL) AND l.status='approved' AND l.visible_to_coparent=1
             ORDER BY l.certified DESC, l.click_count DESC, l.created_at DESC",
            $familyIds
        );
    }

    /** Liens certifiés globaux uniquement — panneau d'administration système. */
    public static function getCertified(): array
    {
        return Database::fetchAll(
            "SELECT * FROM portal_links WHERE certified=1 ORDER BY created_at DESC"
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

    /** Lien certifié : porté par l'administrateur système, jamais rattaché à une famille
     *  précise, toujours publié immédiatement (pas de circuit d'approbation par un tiers). */
    public static function createCertified(array $data): int
    {
        return Database::insert(
            "INSERT INTO portal_links (family_id, title, url, description, image_path, status, visible_to_coparent, certified, submitted_by, reviewed_at)
             VALUES (NULL,?,?,?,?,'approved',?,1,NULL,NOW())",
            [
                $data['title'],
                $data['url'],
                $data['description'] ?: null,
                $data['image_path'] ?? null,
                !empty($data['visible_to_coparent']) ? 1 : 0,
            ]
        );
    }

    public static function updateCertified(int $id, array $data): void
    {
        $fields = ['title=?', 'description=?', 'url=?', 'visible_to_coparent=?'];
        $params = [$data['title'], $data['description'] ?: null, $data['url'], !empty($data['visible_to_coparent']) ? 1 : 0];
        if (!empty($data['image_path'])) {
            $fields[] = 'image_path=?';
            $params[] = $data['image_path'];
        }
        $params[] = $id;
        Database::execute('UPDATE portal_links SET ' . implode(', ', $fields) . ' WHERE id=? AND certified=1', $params);
    }

    public static function update(int $id, array $data): void
    {
        Database::execute(
            'UPDATE portal_links SET title=?, description=?, visible_to_coparent=? WHERE id=?',
            [$data['title'], $data['description'] ?: null, !empty($data['visible_to_coparent']) ? 1 : 0, $id]
        );
    }

    public static function updatePreviewImage(int $id, string $imagePath): void
    {
        Database::execute('UPDATE portal_links SET image_path=? WHERE id=?', [$imagePath, $id]);
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
