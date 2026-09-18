<?php
namespace App\Models;

use App\Core\Database;

/** Catégories du menu Démarrer de l'interface bureau (PC) — configurées globalement par
 *  l'admin système, appliquées à toutes les familles. Un module non affecté à une catégorie
 *  (nouveau module oublié lors d'une réorganisation, par ex.) atterrit dans un panier "Autre"
 *  calculé à la volée, pour ne jamais disparaître silencieusement du menu Démarrer. */
class NavCategory
{
    public static function getAll(): array
    {
        return Database::fetchAll('SELECT * FROM nav_categories ORDER BY position, id');
    }

    /** Catégories avec leurs modules (slug + position), plus un panier "Autre" en fin de
     *  liste pour tout module de Family::MODULES non encore affecté. */
    public static function getAllWithModules(): array
    {
        $categories = self::getAll();
        $assignments = Database::fetchAll('SELECT * FROM nav_category_modules ORDER BY category_id, position, id');

        $byCategory = [];
        $assignedSlugs = [];
        foreach ($assignments as $a) {
            $byCategory[$a['category_id']][] = $a['module_slug'];
            $assignedSlugs[$a['module_slug']] = true;
        }

        $result = [];
        foreach ($categories as $cat) {
            $result[] = [
                'id' => (int)$cat['id'],
                'name' => $cat['name'],
                'icon' => $cat['icon'],
                'modules' => $byCategory[$cat['id']] ?? [],
            ];
        }

        $unassigned = array_values(array_diff(array_keys(Family::MODULES), array_keys($assignedSlugs)));
        if ($unassigned) {
            $result[] = ['id' => null, 'name' => 'Autre', 'icon' => '📁', 'modules' => $unassigned];
        }

        return $result;
    }

    public static function create(string $name, string $icon): int
    {
        $position = (int)(Database::fetch('SELECT COALESCE(MAX(position), -1) + 1 AS p FROM nav_categories')['p'] ?? 0);
        return Database::insert('INSERT INTO nav_categories (name, icon, position) VALUES (?, ?, ?)', [$name, $icon, $position]);
    }

    public static function update(int $id, string $name, string $icon): void
    {
        Database::query('UPDATE nav_categories SET name = ?, icon = ? WHERE id = ?', [$name, $icon, $id]);
    }

    public static function delete(int $id): void
    {
        // ON DELETE CASCADE libère les modules de cette catégorie ; ils retombent
        // automatiquement dans le panier "Autre" de getAllWithModules().
        Database::query('DELETE FROM nav_categories WHERE id = ?', [$id]);
    }

    public static function reorder(array $orderedIds): void
    {
        foreach ($orderedIds as $position => $id) {
            Database::query('UPDATE nav_categories SET position = ? WHERE id = ?', [$position, (int)$id]);
        }
    }

    /** Remplace la liste (ordonnée) des modules d'une catégorie. Un slug déjà affecté ailleurs
     *  est déplacé ici (la contrainte UNIQUE sur module_slug empêche toute double affectation). */
    public static function setModules(int $categoryId, array $slugs): void
    {
        $validSlugs = array_keys(Family::MODULES);
        Database::query(
            'DELETE FROM nav_category_modules WHERE category_id = ?',
            [$categoryId]
        );
        foreach (array_values($slugs) as $position => $slug) {
            if (!in_array($slug, $validSlugs, true)) continue;
            Database::query(
                'DELETE FROM nav_category_modules WHERE module_slug = ?',
                [$slug]
            );
            Database::query(
                'INSERT INTO nav_category_modules (category_id, module_slug, position) VALUES (?, ?, ?)',
                [$categoryId, $slug, $position]
            );
        }
    }
}
