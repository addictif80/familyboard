<?php
namespace App\Models;

use App\Core\Database;

/**
 * Liste de cadeaux avec réservation secrète : un membre de la famille peut réserver le cadeau
 * d'un autre membre sans que celui-ci le sache — c'est tout l'intérêt de la fonctionnalité, donc
 * scrubOwnerView() doit être appliqué systématiquement avant de renvoyer des items à leur
 * propriétaire (jamais côté SQL seul, où un oubli serait invisible en test superficiel).
 */
class WishlistItem
{
    public static function getByFamily(int $familyId): array
    {
        return Database::fetchAll(
            'SELECT w.*, u.name as owner_name, u.color as owner_color, u.avatar as owner_avatar,
                    r.name as reserved_by_name
             FROM wishlist_items w
             JOIN users u ON u.id = w.user_id
             LEFT JOIN users r ON r.id = w.reserved_by
             WHERE w.family_id = ?
             ORDER BY w.is_purchased ASC, FIELD(w.priority,\'high\',\'medium\',\'low\'), w.created_at DESC',
            [$familyId]
        );
    }

    public static function getById(int $id): ?array
    {
        return Database::fetch('SELECT * FROM wishlist_items WHERE id = ?', [$id]);
    }

    public static function create(int $familyId, int $userId, array $data): int
    {
        return Database::insert(
            'INSERT INTO wishlist_items (family_id, user_id, title, description, url, price, priority)
             VALUES (?,?,?,?,?,?,?)',
            [
                $familyId, $userId,
                $data['title'],
                !empty($data['description']) ? $data['description'] : null,
                self::sanitizeUrl($data['url'] ?? null),
                isset($data['price']) && $data['price'] !== '' ? (float)$data['price'] : null,
                in_array($data['priority'] ?? 'medium', ['low', 'medium', 'high'], true) ? $data['priority'] : 'medium',
            ]
        );
    }

    public static function update(int $id, array $data): void
    {
        Database::execute(
            'UPDATE wishlist_items SET title=?, description=?, url=?, price=?, priority=? WHERE id=?',
            [
                $data['title'],
                !empty($data['description']) ? $data['description'] : null,
                self::sanitizeUrl($data['url'] ?? null),
                isset($data['price']) && $data['price'] !== '' ? (float)$data['price'] : null,
                in_array($data['priority'] ?? 'medium', ['low', 'medium', 'high'], true) ? $data['priority'] : 'medium',
                $id,
            ]
        );
    }

    /** N'accepte que http(s) — un lien "javascript:" stocké serait exécuté au clic par
     *  n'importe quel autre membre de la famille consultant le souhait (XSS stocké). */
    private static function sanitizeUrl(?string $url): ?string
    {
        $url = trim((string)$url);
        if ($url === '') return null;
        $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
        return in_array($scheme, ['http', 'https'], true) ? $url : null;
    }

    public static function delete(int $id): void
    {
        Database::execute('DELETE FROM wishlist_items WHERE id=?', [$id]);
    }

    public static function reserve(int $id, int $userId): void
    {
        Database::execute(
            'UPDATE wishlist_items SET reserved_by=?, reserved_at=NOW() WHERE id=? AND reserved_by IS NULL',
            [$userId, $id]
        );
    }

    public static function unreserve(int $id): void
    {
        Database::execute('UPDATE wishlist_items SET reserved_by=NULL, reserved_at=NULL, is_purchased=0 WHERE id=?', [$id]);
    }

    public static function markPurchased(int $id, bool $purchased): void
    {
        Database::execute('UPDATE wishlist_items SET is_purchased=? WHERE id=?', [$purchased ? 1 : 0, $id]);
    }

    /**
     * Retire toute trace de réservation/achat des items appartenant au spectateur lui-même —
     * à appeler sur CHAQUE item avant affichage ou sérialisation JSON, jamais seulement en
     * agrégat, pour ne pas dépendre d'un futur appelant qui l'oublierait sur un nouveau point
     * d'entrée.
     */
    public static function scrubForViewer(array $item, int $viewerId): array
    {
        if ((int)$item['user_id'] === $viewerId) {
            $item['reserved_by'] = null;
            $item['reserved_by_name'] = null;
            $item['reserved_at'] = null;
            $item['is_purchased'] = 0;
            $item['is_reserved_by_me'] = false;
        } else {
            $item['is_reserved_by_me'] = ((int)($item['reserved_by'] ?? 0) === $viewerId);
        }
        return $item;
    }
}
