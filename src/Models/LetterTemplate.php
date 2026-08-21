<?php
namespace App\Models;

use App\Core\Database;

class LetterTemplate
{
    public static function getByFamily(int $familyId): array
    {
        return Database::fetchAll(
            'SELECT t.*, u.name AS author_name
             FROM letter_templates t JOIN users u ON u.id = t.created_by
             WHERE t.family_id = ? ORDER BY t.name',
            [$familyId]
        );
    }

    public static function create(int $familyId, int $userId, string $name, string $subject, string $body, array $variables): int
    {
        return Database::insert(
            'INSERT INTO letter_templates (family_id, created_by, name, subject, body, variables) VALUES (?,?,?,?,?,?)',
            [$familyId, $userId, $name, $subject, $body, json_encode(array_values($variables))]
        );
    }

    public static function delete(int $id, int $familyId): void
    {
        Database::execute('DELETE FROM letter_templates WHERE id=? AND family_id=?', [$id, $familyId]);
    }
}
