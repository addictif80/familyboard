<?php
namespace App\Models;

use App\Core\Database;

class Family
{
    public static function findById(int $id): ?array
    {
        return Database::fetch('SELECT * FROM families WHERE id = ?', [$id]);
    }

    public static function findByInviteCode(string $code): ?array
    {
        return Database::fetch('SELECT * FROM families WHERE invite_code = ?', [$code]);
    }

    public static function create(string $name): int
    {
        $code = self::generateCode();
        return Database::insert(
            'INSERT INTO families (name, invite_code) VALUES (?, ?)',
            [$name, $code]
        );
    }

    public static function update(int $id, string $name): void
    {
        Database::execute('UPDATE families SET name = ? WHERE id = ?', [$name, $id]);
    }

    public static function regenerateCode(int $id): string
    {
        $code = self::generateCode();
        Database::execute('UPDATE families SET invite_code = ? WHERE id = ?', [$code, $id]);
        return $code;
    }

    private static function generateCode(): string
    {
        do {
            $code = strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
            $existing = Database::fetch('SELECT id FROM families WHERE invite_code = ?', [$code]);
        } while ($existing);
        return $code;
    }
}
