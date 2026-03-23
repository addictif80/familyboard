<?php
namespace App\Models;

use App\Core\Database;

class Invitation
{
    public static function create(int $familyId, int $userId, string $email): string
    {
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+7 days'));
        Database::insert(
            'INSERT INTO invitation_tokens (family_id, invited_by, email, token, expires_at) VALUES (?,?,?,?,?)',
            [$familyId, $userId, $email, $token, $expires]
        );
        return $token;
    }

    public static function getByToken(string $token): ?array
    {
        return Database::fetch(
            'SELECT it.*, f.name as family_name, u.name as invited_by_name
             FROM invitation_tokens it
             JOIN families f ON f.id = it.family_id
             JOIN users u ON u.id = it.invited_by
             WHERE it.token = ? AND it.expires_at > NOW() AND it.used_at IS NULL',
            [$token]
        );
    }

    public static function markUsed(string $token): void
    {
        Database::execute('UPDATE invitation_tokens SET used_at = NOW() WHERE token = ?', [$token]);
    }
}
