<?php
namespace App\Models;

use App\Core\Database;

class DisputeShare
{
    public static function getByDispute(int $disputeId): ?array
    {
        return Database::fetch('SELECT * FROM dispute_shares WHERE dispute_id=?', [$disputeId]);
    }

    public static function getOrCreate(int $disputeId, int $userId): array
    {
        $existing = self::getByDispute($disputeId);
        if ($existing) return $existing;

        $token = bin2hex(random_bytes(24));
        Database::insert(
            'INSERT INTO dispute_shares (dispute_id, token, created_by) VALUES (?,?,?)',
            [$disputeId, $token, $userId]
        );
        return self::getByDispute($disputeId);
    }

    public static function regenerate(int $disputeId): array
    {
        $token = bin2hex(random_bytes(24));
        Database::execute('UPDATE dispute_shares SET token=? WHERE dispute_id=?', [$token, $disputeId]);
        return self::getByDispute($disputeId);
    }

    public static function revoke(int $disputeId): void
    {
        Database::execute('DELETE FROM dispute_shares WHERE dispute_id=?', [$disputeId]);
    }

    public static function findValidByToken(string $token): ?array
    {
        return Database::fetch('SELECT * FROM dispute_shares WHERE token=?', [$token]);
    }
}
