<?php
namespace App\Models;

use App\Core\Database;

class Letter
{
    public static function getByFamily(int $familyId): array
    {
        return Database::fetchAll(
            'SELECT l.*, u.name AS author_name
             FROM letters l JOIN users u ON u.id = l.user_id
             WHERE l.family_id = ? ORDER BY l.letter_date DESC',
            [$familyId]
        );
    }

    public static function getById(int $id): ?array
    {
        return Database::fetch(
            'SELECT l.*, u.name AS author_name
             FROM letters l JOIN users u ON u.id = l.user_id
             WHERE l.id = ?',
            [$id]
        );
    }

    public static function create(int $familyId, int $userId, array $d): int
    {
        return Database::insert(
            'INSERT INTO letters
             (family_id, user_id, civility, recipient_last_name, recipient_first_name,
              recipient_display_name, recipient_complement, recipient_address,
              recipient_address_complement, recipient_postal_city, place, subject, body)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [
                $familyId, $userId, $d['civility'], $d['recipient_last_name'], $d['recipient_first_name'],
                $d['recipient_display_name'], $d['recipient_complement'], $d['recipient_address'],
                $d['recipient_address_complement'], $d['recipient_postal_city'], $d['place'],
                $d['subject'], $d['body'],
            ]
        );
    }

    public static function update(int $id, int $familyId, array $d): void
    {
        Database::execute(
            'UPDATE letters SET civility=?, recipient_last_name=?, recipient_first_name=?,
             recipient_display_name=?, recipient_complement=?, recipient_address=?,
             recipient_address_complement=?, recipient_postal_city=?, place=?, subject=?, body=?
             WHERE id=? AND family_id=?',
            [
                $d['civility'], $d['recipient_last_name'], $d['recipient_first_name'],
                $d['recipient_display_name'], $d['recipient_complement'], $d['recipient_address'],
                $d['recipient_address_complement'], $d['recipient_postal_city'], $d['place'],
                $d['subject'], $d['body'],
                $id, $familyId,
            ]
        );
    }

    public static function delete(int $id, int $familyId): void
    {
        Database::execute('DELETE FROM letters WHERE id=? AND family_id=?', [$id, $familyId]);
    }
}
