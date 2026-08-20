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
        // Normalisé explicitement plutôt que de dépendre uniquement de la collation de la
        // colonne `email` (insensible à la casse aujourd'hui) : une future migration de
        // collation ou de moteur de base ne doit pas pouvoir réintroduire une confusion de
        // compte entre "User@x.com" et "user@x.com".
        return Database::fetch('SELECT * FROM users WHERE email = ?', [self::normalizeEmail($email)]);
    }

    public static function create(int $familyId, string $name, string $email, string $password, string $role = 'member', string $color = '#4A90D9'): int
    {
        return Database::insert(
            'INSERT INTO users (family_id, name, email, password, role, color) VALUES (?, ?, ?, ?, ?, ?)',
            [$familyId, $name, self::normalizeEmail($email), password_hash($password, PASSWORD_DEFAULT), $role, $color]
        );
    }

    public static function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    public static function getByFamily(int $familyId): array
    {
        return Database::fetchAll('SELECT id, name, email, phone, role, avatar, color, created_at FROM users WHERE family_id = ? ORDER BY name', [$familyId]);
    }

    /**
     * Vérifie qu'un ID utilisateur reçu du client (ex. assigned_to, user_id) appartient bien à
     * la famille courante avant de s'en servir (assignation de tâche, notification...) — sans
     * ce contrôle, un utilisateur peut cibler n'importe quel compte d'une autre famille en
     * devinant un ID (les ID sont séquentiels).
     */
    public static function belongsToFamily(int $userId, int $familyId): bool
    {
        return (bool)Database::fetch('SELECT 1 FROM users WHERE id = ? AND family_id = ?', [$userId, $familyId]);
    }

    public static function update(int $id, array $data): void
    {
        $allowed = ['name', 'email', 'avatar', 'color', 'birthday', 'location_tracking_enabled', 'phone'];
        $sets = [];
        $params = [];
        foreach ($allowed as $field) {
            if (isset($data[$field])) {
                $sets[] = "$field = ?";
                $params[] = $field === 'email' ? self::normalizeEmail($data[$field]) : $data[$field];
            }
        }
        if (!$sets) return;
        $params[] = $id;
        Database::execute('UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = ?', $params);
    }

    /** Barre de navigation rapide (mobile/PWA) : jusqu'à 3 slugs de module choisis par
     *  l'utilisateur lui-même — préférence personnelle, jamais recalculée automatiquement.
     *  $slugs === null réinitialise vers la sélection par défaut (Family::defaultQuickNav). */
    public static function updateQuickNav(int $id, ?array $slugs): void
    {
        $value = $slugs ? implode(',', array_slice($slugs, 0, 3)) : null;
        Database::execute('UPDATE users SET quick_nav = ? WHERE id = ?', [$value, $id]);
    }

    public static function updatePassword(int $id, string $password): void
    {
        Database::execute('UPDATE users SET password = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $id]);
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
