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
                    r.name as reserved_by_user_name, f.name as family_name
             FROM wishlist_items w
             LEFT JOIN users u ON u.id = w.user_id
             JOIN families f ON f.id = w.family_id
             LEFT JOIN users r ON r.id = w.reserved_by
             WHERE w.family_id = ? AND w.family_child_id IS NULL
             ORDER BY w.is_purchased ASC, FIELD(w.priority,\'high\',\'medium\',\'low\'), w.created_at DESC',
            [$familyId]
        );
    }

    /** Souhaits d'un enfant du registre familial (sans compte) — jamais mélangés aux souhaits
     *  "adultes" de getByFamily() : un enfant n'a pas de session pour voir/gérer les siens,
     *  c'est entièrement porté par la famille + le lien public (voir WishlistChildLink). */
    public static function getByChild(int $familyChildId): array
    {
        return Database::fetchAll(
            'SELECT * FROM wishlist_items WHERE family_child_id = ? ORDER BY is_purchased ASC, created_at DESC',
            [$familyChildId]
        );
    }

    public static function getById(int $id): ?array
    {
        return Database::fetch('SELECT * FROM wishlist_items WHERE id = ?', [$id]);
    }

    /** @param int|null $userId Souhait "adulte" classique ($familyChildId doit alors être null).
     *  @param int|null $familyChildId Souhait d'un enfant sans compte ($userId doit alors être null). */
    public static function create(int $familyId, ?int $userId, ?int $familyChildId, array $data): int
    {
        return Database::insert(
            'INSERT INTO wishlist_items (family_id, user_id, family_child_id, title, description, url, image_path, price, priority)
             VALUES (?,?,?,?,?,?,?,?,?)',
            [
                $familyId, $userId, $familyChildId,
                $data['title'],
                !empty($data['description']) ? $data['description'] : null,
                self::sanitizeUrl($data['url'] ?? null),
                $data['image_path'] ?? null,
                isset($data['price']) && $data['price'] !== '' ? (float)$data['price'] : null,
                in_array($data['priority'] ?? 'medium', ['low', 'medium', 'high'], true) ? $data['priority'] : 'medium',
            ]
        );
    }

    public static function update(int $id, array $data): void
    {
        $existing = self::getById($id);
        $imagePath = $existing['image_path'] ?? null;
        if (!empty($data['remove_image'])) {
            if ($imagePath) @unlink(BASE_PATH . $imagePath);
            $imagePath = null;
        }
        if (!empty($data['image_path'])) $imagePath = $data['image_path'];

        Database::execute(
            'UPDATE wishlist_items SET title=?, description=?, url=?, image_path=?, price=?, priority=? WHERE id=?',
            [
                $data['title'],
                !empty($data['description']) ? $data['description'] : null,
                self::sanitizeUrl($data['url'] ?? null),
                $imagePath,
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
        $item = self::getById($id);
        if ($item && $item['image_path']) @unlink(BASE_PATH . $item['image_path']);
        Database::execute('DELETE FROM wishlist_items WHERE id=?', [$id]);
    }

    public static function reserve(int $id, int $userId): void
    {
        Database::execute(
            'UPDATE wishlist_items SET reserved_by=?, reserved_at=NOW() WHERE id=? AND reserved_by IS NULL AND reservation_token IS NULL',
            [$userId, $id]
        );
    }

    public static function unreserve(int $id): void
    {
        Database::execute('UPDATE wishlist_items SET reserved_by=NULL, reserved_at=NULL, is_purchased=0 WHERE id=?', [$id]);
    }

    /** Réservation anonyme (lien public d'un enfant, voir WishlistChildLink) : pas de compte,
     *  donc un prénom libre + un jeton rendu au navigateur pour pouvoir annuler plus tard — même
     *  principe que BirthList::reserve(). */
    public static function reserveAnonymous(int $id, int $familyChildId, string $name): ?string
    {
        $token = bin2hex(random_bytes(24));
        $affected = Database::execute(
            'UPDATE wishlist_items SET reserved_by_name=?, reservation_token=?, reserved_at=NOW()
             WHERE id=? AND family_child_id=? AND reserved_by IS NULL AND reservation_token IS NULL',
            [$name, $token, $id, $familyChildId]
        );
        return $affected > 0 ? $token : null;
    }

    public static function unreserveAnonymous(int $id, int $familyChildId, string $reservationToken): bool
    {
        $affected = Database::execute(
            'UPDATE wishlist_items SET reserved_by_name=NULL, reservation_token=NULL, reserved_at=NULL, is_purchased=0
             WHERE id=? AND family_child_id=? AND reservation_token=?',
            [$id, $familyChildId, $reservationToken]
        );
        return $affected > 0;
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
            $item['reserved_by_user_name'] = null;
            $item['reserved_at'] = null;
            $item['is_purchased'] = 0;
            $item['is_reserved_by_me'] = false;
        } else {
            $item['is_reserved_by_me'] = ((int)($item['reserved_by'] ?? 0) === $viewerId);
        }
        return $item;
    }

    /** Vue famille de la liste d'un enfant : jamais le statut de réservation (anonyme, via le
     *  lien public), pour préserver la surprise — même principe que BirthList::scrubForOwner(). */
    public static function scrubForChildFamilyView(array $item): array
    {
        unset($item['reserved_by_name'], $item['reservation_token'], $item['reserved_at']);
        $item['is_purchased'] = 0;
        return $item;
    }
}
