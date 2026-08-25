<?php
namespace App\Models;

use App\Core\Database;

class TaskListShare
{
    public static function getByList(int $listId): ?array
    {
        return Database::fetch('SELECT * FROM task_list_shares WHERE list_id=?', [$listId]);
    }

    /** Retourne le lien existant, ou en crée un nouveau (un seul par liste). */
    public static function getOrCreate(int $listId, int $userId): array
    {
        $existing = self::getByList($listId);
        if ($existing) return $existing;

        $token = bin2hex(random_bytes(24));
        Database::insert(
            'INSERT INTO task_list_shares (list_id, token, created_by) VALUES (?,?,?)',
            [$listId, $token, $userId]
        );
        return self::getByList($listId);
    }

    public static function regenerate(int $listId): array
    {
        $token = bin2hex(random_bytes(24));
        Database::execute('UPDATE task_list_shares SET token=? WHERE list_id=?', [$token, $listId]);
        return self::getByList($listId);
    }

    public static function revoke(int $listId): void
    {
        Database::execute('DELETE FROM task_list_shares WHERE list_id=?', [$listId]);
    }

    public static function findValidByToken(string $token): ?array
    {
        return Database::fetch('SELECT * FROM task_list_shares WHERE token=?', [$token]);
    }
}
