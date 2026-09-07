<?php
namespace App\Models;

use App\Core\Database;

class Deal
{
    public const CATEGORIES = [
        'alimentation' => ['label' => 'Alimentation', 'icon' => '🛒'],
        'loisirs'      => ['label' => 'Loisirs',      'icon' => '🎡'],
        'enfants'      => ['label' => 'Enfants',      'icon' => '🧸'],
        'shopping'     => ['label' => 'Shopping',      'icon' => '🛍️'],
        'voyage'       => ['label' => 'Voyage',        'icon' => '✈️'],
        'maison'       => ['label' => 'Maison',        'icon' => '🏠'],
        'sante'        => ['label' => 'Santé',         'icon' => '💊'],
        'services'     => ['label' => 'Services',      'icon' => '🔧'],
        'autre'        => ['label' => 'Autre',         'icon' => '🏷️'],
    ];

    public static function getByFamily(int $familyId): array
    {
        return Database::fetchAll(
            'SELECT * FROM deals WHERE family_id=? ORDER BY (expires_at IS NOT NULL AND expires_at < CURDATE()) ASC, title',
            [$familyId]
        );
    }

    public static function getById(int $id): ?array
    {
        return Database::fetch('SELECT * FROM deals WHERE id=?', [$id]);
    }

    public static function create(int $familyId, int $userId, array $d): int
    {
        return Database::insert(
            'INSERT INTO deals (family_id, title, description, url, promo_code, discount, category, expires_at, created_by)
             VALUES (?,?,?,?,?,?,?,?,?)',
            [$familyId, $d['title'], $d['description'], $d['url'], $d['promo_code'], $d['discount'], $d['category'], $d['expires_at'], $userId]
        );
    }

    public static function update(int $id, int $familyId, array $d): void
    {
        Database::execute(
            'UPDATE deals SET title=?, description=?, url=?, promo_code=?, discount=?, category=?, expires_at=? WHERE id=? AND family_id=?',
            [$d['title'], $d['description'], $d['url'], $d['promo_code'], $d['discount'], $d['category'], $d['expires_at'], $id, $familyId]
        );
    }

    public static function delete(int $id, int $familyId): void
    {
        Database::execute('DELETE FROM deals WHERE id=? AND family_id=?', [$id, $familyId]);
    }
}
