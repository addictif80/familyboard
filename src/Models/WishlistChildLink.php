<?php
namespace App\Models;

use App\Core\Database;

/** Lien public (sans compte) vers la liste de cadeaux d'un enfant du registre familial — un
 *  jeton par enfant, créé à la demande, même principe que BirthList::ensureForFamily(). */
class WishlistChildLink
{
    public static function getByChild(int $familyChildId): ?array
    {
        return Database::fetch('SELECT * FROM wishlist_child_links WHERE family_child_id=?', [$familyChildId]);
    }

    public static function findByToken(string $token): ?array
    {
        return Database::fetch('SELECT * FROM wishlist_child_links WHERE token=?', [$token]);
    }

    public static function ensureForChild(int $familyId, int $familyChildId, int $userId): array
    {
        $existing = self::getByChild($familyChildId);
        if ($existing) return $existing;
        $token = bin2hex(random_bytes(24));
        try {
            Database::insert(
                'INSERT INTO wishlist_child_links (family_id, family_child_id, token, created_by) VALUES (?,?,?,?)',
                [$familyId, $familyChildId, $token, $userId]
            );
        } catch (\Throwable) {
            // Créé entre-temps par une requête concurrente (contrainte uq_wishlist_child_links_child) : relit.
        }
        return self::getByChild($familyChildId);
    }

    public static function regenerateToken(int $familyChildId): void
    {
        Database::execute(
            'UPDATE wishlist_child_links SET token=? WHERE family_child_id=?',
            [bin2hex(random_bytes(24)), $familyChildId]
        );
    }
}
