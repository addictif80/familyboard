<?php
namespace App\Models;

use App\Core\Database;

class MealPlan
{
    public const TYPES = ['breakfast' => 'Petit-déj', 'lunch' => 'Midi', 'dinner' => 'Soir', 'snack' => 'Goûter'];

    /** All planned meals for [startDate, startDate+6 days], keyed by "date_mealtype". */
    public static function getWeek(int $familyId, string $startDate): array
    {
        $endDate = date('Y-m-d', strtotime($startDate . ' +6 days'));
        $rows = Database::fetchAll(
            'SELECT mp.*, r.title as recipe_title
             FROM meal_plans mp
             LEFT JOIN recipes r ON r.id = mp.recipe_id
             WHERE mp.family_id=? AND mp.date BETWEEN ? AND ?',
            [$familyId, $startDate, $endDate]
        );
        $byKey = [];
        foreach ($rows as $row) {
            $byKey[$row['date'] . '_' . $row['meal_type']] = $row;
        }
        return $byKey;
    }

    public static function set(int $familyId, int $userId, string $date, string $mealType, ?int $recipeId, ?string $customTitle): void
    {
        Database::execute(
            'INSERT INTO meal_plans (family_id, date, meal_type, recipe_id, custom_title, created_by)
             VALUES (?,?,?,?,?,?)
             ON DUPLICATE KEY UPDATE recipe_id=VALUES(recipe_id), custom_title=VALUES(custom_title), created_by=VALUES(created_by)',
            [$familyId, $date, $mealType, $recipeId, $customTitle, $userId]
        );
    }

    public static function clear(int $familyId, string $date, string $mealType): void
    {
        Database::execute('DELETE FROM meal_plans WHERE family_id=? AND date=? AND meal_type=?', [$familyId, $date, $mealType]);
    }

    public static function getById(int $id): ?array
    {
        return Database::fetch('SELECT * FROM meal_plans WHERE id=?', [$id]);
    }
}
