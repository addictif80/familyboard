<?php
namespace App\Models;

use App\Core\Database;

class PushSubscription
{
    public static function save(int $userId, string $endpoint, string $p256dh, string $authKey): void
    {
        Database::execute(
            'INSERT INTO push_subscriptions (user_id, endpoint, p256dh, auth_key) VALUES (?,?,?,?)
             ON DUPLICATE KEY UPDATE p256dh=VALUES(p256dh), auth_key=VALUES(auth_key)',
            [$userId, $endpoint, $p256dh, $authKey]
        );
    }

    public static function getByUser(int $userId): array
    {
        return Database::fetchAll('SELECT * FROM push_subscriptions WHERE user_id=?', [$userId]);
    }

    public static function deleteByEndpoint(string $endpoint): void
    {
        Database::execute('DELETE FROM push_subscriptions WHERE endpoint=?', [$endpoint]);
    }

    public static function deleteForUser(int $userId, string $endpoint): void
    {
        Database::execute('DELETE FROM push_subscriptions WHERE user_id=? AND endpoint=?', [$userId, $endpoint]);
    }
}
