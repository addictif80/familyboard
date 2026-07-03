<?php
namespace App\Models;

use App\Core\Database;

class Recipe
{
    public static function getByFamily(int $familyId): array
    {
        return Database::fetchAll('SELECT * FROM recipes WHERE family_id=? ORDER BY title', [$familyId]);
    }

    public static function getById(int $id): ?array
    {
        return Database::fetch('SELECT * FROM recipes WHERE id=?', [$id]);
    }

    public static function create(int $familyId, int $userId, array $data): int
    {
        return Database::insert(
            'INSERT INTO recipes (family_id, title, ingredients, instructions, servings, created_by) VALUES (?,?,?,?,?,?)',
            [$familyId, $data['title'], $data['ingredients'], $data['instructions'] ?? null, (int)($data['servings'] ?? 4), $userId]
        );
    }

    public static function update(int $id, array $data): void
    {
        Database::execute(
            'UPDATE recipes SET title=?, ingredients=?, instructions=?, servings=? WHERE id=?',
            [$data['title'], $data['ingredients'], $data['instructions'] ?? null, (int)($data['servings'] ?? 4), $id]
        );
    }

    public static function delete(int $id): void
    {
        Database::execute('DELETE FROM recipes WHERE id=?', [$id]);
    }

    /** One ingredient per line. */
    public static function ingredientLines(array $recipe): array
    {
        $lines = preg_split('/\r\n|\r|\n/', (string)$recipe['ingredients']);
        return array_values(array_filter(array_map('trim', $lines)));
    }
}
