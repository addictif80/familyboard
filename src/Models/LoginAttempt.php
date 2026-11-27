<?php
namespace App\Models;

use App\Core\Database;

/** Limitation des tentatives de connexion par IP (protection brute-force). */
class LoginAttempt
{
    private const MAX_ATTEMPTS = 5;
    private const WINDOW_MINUTES = 15;

    public static function record(string $scope, string $ip): void
    {
        Database::insert('INSERT INTO login_attempts (scope, ip) VALUES (?,?)', [$scope, $ip]);
    }

    public static function isLocked(string $scope, string $ip): bool
    {
        $row = Database::fetch(
            'SELECT COUNT(*) as n FROM login_attempts WHERE scope=? AND ip=? AND created_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)',
            [$scope, $ip, self::WINDOW_MINUTES]
        );
        return (int)($row['n'] ?? 0) >= self::MAX_ATTEMPTS;
    }

    public static function clear(string $scope, string $ip): void
    {
        Database::execute('DELETE FROM login_attempts WHERE scope=? AND ip=?', [$scope, $ip]);
    }

    public static function minutesUntilUnlock(): int
    {
        return self::WINDOW_MINUTES;
    }

    /**
     * Clé stable et courte pour verrouiller par compte plutôt que par IP (un attaquant
     * distribué sur plusieurs IP garde sinon un quota de 5 tentatives par IP, donc un budget
     * de tentatives illimité sur un compte précis). Le hash évite de stocker l'email en clair
     * dans cette table technique.
     */
    public static function accountKey(string $email): string
    {
        return 'acct:' . substr(hash('sha256', strtolower(trim($email))), 0, 32);
    }
}
