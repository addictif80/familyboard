<?php
namespace App\Models;

use App\Core\Database;

class AuthToken
{
    public static function create(int $userId, string $selector, string $validatorHash, string $expiresAt): void
    {
        Database::execute(
            'INSERT INTO auth_tokens (user_id, selector, validator_hash, expires_at) VALUES (?,?,?,?)',
            [$userId, $selector, $validatorHash, $expiresAt]
        );
    }

    public static function findBySelector(string $selector): ?array
    {
        return Database::fetch('SELECT * FROM auth_tokens WHERE selector=? AND expires_at > NOW()', [$selector]);
    }

    public static function deleteBySelector(string $selector): void
    {
        Database::execute('DELETE FROM auth_tokens WHERE selector=?', [$selector]);
    }

    public static function deleteForUser(int $userId): void
    {
        Database::execute('DELETE FROM auth_tokens WHERE user_id=?', [$userId]);
    }
}
