<?php
namespace App\Models;

use App\Core\Database;

/**
 * Mise en avant des autres services ABHD (éditeur de FamilyBoard) — jamais appelée "publicité"
 * dans le code ni l'interface (choix produit explicite), et gérée uniquement par
 * l'administrateur système. Sans lien avec une régie tierce : pas de tracking par utilisateur,
 * seulement un compteur de clics agrégé.
 */
class AbhdHighlight
{
    public const PLACEMENTS = ['dashboard', 'module_pages', 'email', 'coparent'];

    public static function getAll(): array
    {
        return Database::fetchAll('SELECT * FROM abhd_highlights ORDER BY is_active DESC, created_at DESC');
    }

    public static function getById(int $id): ?array
    {
        return Database::fetch('SELECT * FROM abhd_highlights WHERE id = ?', [$id]);
    }

    /** Une mise en avant active au hasard parmi celles ciblant cet emplacement (ou aucune). */
    public static function pickForPlacement(string $placement): ?array
    {
        if (!in_array($placement, self::PLACEMENTS, true)) return null;
        $rows = Database::fetchAll(
            "SELECT * FROM abhd_highlights WHERE is_active=1 AND show_{$placement}=1"
        );
        return $rows ? $rows[array_rand($rows)] : null;
    }

    /** Une mise en avant active au hasard parmi celles cochées "fenêtre modale" — indépendant
     *  des 4 emplacements ci-dessus, puisqu'une mise en avant peut être en modale sans être
     *  affichée ailleurs (ou l'inverse). */
    public static function pickModal(): ?array
    {
        $rows = Database::fetchAll('SELECT * FROM abhd_highlights WHERE is_active=1 AND show_modal=1');
        return $rows ? $rows[array_rand($rows)] : null;
    }

    public static function create(array $data): int
    {
        return Database::insert(
            'INSERT INTO abhd_highlights (title, description, url, image_path, show_dashboard, show_module_pages, show_email, show_coparent, show_modal, is_active)
             VALUES (?,?,?,?,?,?,?,?,?,?)',
            [
                $data['title'],
                $data['description'] ?: null,
                $data['url'],
                $data['image_path'] ?? null,
                !empty($data['show_dashboard']) ? 1 : 0,
                !empty($data['show_module_pages']) ? 1 : 0,
                !empty($data['show_email']) ? 1 : 0,
                !empty($data['show_coparent']) ? 1 : 0,
                !empty($data['show_modal']) ? 1 : 0,
                !empty($data['is_active']) ? 1 : 0,
            ]
        );
    }

    public static function update(int $id, array $data): void
    {
        $fields = ['title=?', 'description=?', 'url=?', 'show_dashboard=?', 'show_module_pages=?', 'show_email=?', 'show_coparent=?', 'show_modal=?', 'is_active=?'];
        $params = [
            $data['title'],
            $data['description'] ?: null,
            $data['url'],
            !empty($data['show_dashboard']) ? 1 : 0,
            !empty($data['show_module_pages']) ? 1 : 0,
            !empty($data['show_email']) ? 1 : 0,
            !empty($data['show_coparent']) ? 1 : 0,
            !empty($data['show_modal']) ? 1 : 0,
            !empty($data['is_active']) ? 1 : 0,
        ];
        if (!empty($data['image_path'])) {
            $fields[] = 'image_path=?';
            $params[] = $data['image_path'];
        }
        $params[] = $id;
        Database::execute('UPDATE abhd_highlights SET ' . implode(', ', $fields) . ' WHERE id=?', $params);
    }

    public static function delete(int $id): void
    {
        Database::execute('DELETE FROM abhd_highlights WHERE id=?', [$id]);
    }

    public static function registerClick(int $id): void
    {
        Database::execute('UPDATE abhd_highlights SET click_count = click_count + 1 WHERE id=?', [$id]);
    }
}
