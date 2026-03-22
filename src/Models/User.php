<?php
namespace App\Models;

use App\Core\Database;

class User
{
    public static function findById(int $id): ?array
    {
        return Database::fetch('SELECT * FROM users WHERE id = ?', [$id]);
    }

    public static function findByEmail(string $email): ?array
    {
        return Database::fetch('SELECT * FROM users WHERE email = ?', [$email]);
    }

    public static function create(int $familyId, string $name, string $email, string $password, string $role = 'member', string $color = '#4A90D9'): int
    {
        return Database::insert(
            'INSERT INTO users (family_id, name, email, password, role, color) VALUES (?, ?, ?, ?, ?, ?)',
            [$familyId, $name, $email, password_hash($password, PASSWORD_DEFAULT), $role, $color]
        );
    }

    public static function getByFamily(int $familyId): array
    {
        return Database::fetchAll('SELECT id, name, email, role, avatar, color, created_at FROM users WHERE family_id = ? ORDER BY name', [$familyId]);
    }

    public static function update(int $id, array $data): void
    {
        $allowed = ['name', 'email', 'avatar', 'color'];
        $sets = [];
        $params = [];
        foreach ($allowed as $field) {
            if (isset($data[$field])) {
                $sets[] = "$field = ?";
                $params[] = $data[$field];
            }
        }
        if (!$sets) return;
        $params[] = $id;
        Database::execute('UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = ?', $params);
    }

    public static function updatePassword(int $id, string $password): void
    {
        Database::execute('UPDATE users SET password = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $id]);
    }

    public static function delete(int $id): void
    {
        Database::execute('DELETE FROM users WHERE id = ?', [$id]);
    }

    public static function verify(string $email, string $password): ?array
    {
        $user = self::findByEmail($email);
        if ($user && password_verify($password, $user['password'])) {
            return $user;
        }
        return null;
    }
}
