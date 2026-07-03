<?php
namespace App\Core;

use App\Models\AuthToken;
use App\Models\User;

/**
 * Long-lived "remember me" cookie, separate from the PHP session cookie.
 * Lets a PWA-installed user stay logged in (and keep receiving push
 * notifications) even if the server-side session is cleared or expires.
 */
class RememberMe
{
    private const COOKIE_NAME = 'fb_remember';
    private const LIFETIME_DAYS = 365;

    public static function issue(int $userId): void
    {
        $selector = bin2hex(random_bytes(9));
        $validator = bin2hex(random_bytes(33));
        $expiresAt = gmdate('Y-m-d H:i:s', time() + self::LIFETIME_DAYS * 86400);

        AuthToken::create($userId, $selector, hash('sha256', $validator), $expiresAt);
        self::setCookie($selector . '.' . $validator, time() + self::LIFETIME_DAYS * 86400);
    }

    /**
     * Attempt to restore a session from the remember-me cookie. Rotates the
     * token on success. Returns true if the user is now logged in.
     */
    public static function attempt(): bool
    {
        $cookie = $_COOKIE[self::COOKIE_NAME] ?? '';
        if (!$cookie || !str_contains($cookie, '.')) return false;

        [$selector, $validator] = explode('.', $cookie, 2);
        $token = AuthToken::findBySelector($selector);
        if (!$token || !hash_equals($token['validator_hash'], hash('sha256', $validator))) {
            self::clear();
            return false;
        }

        $user = User::findById((int)$token['user_id']);
        if (!$user || !empty($user['blocked_at'])) {
            self::clear();
            return false;
        }

        AuthToken::deleteBySelector($selector);
        Session::login($user);
        self::issue($user['id']);
        return true;
    }

    public static function clear(): void
    {
        $cookie = $_COOKIE[self::COOKIE_NAME] ?? '';
        if ($cookie && str_contains($cookie, '.')) {
            [$selector] = explode('.', $cookie, 2);
            AuthToken::deleteBySelector($selector);
        }
        self::setCookie('', time() - 3600);
    }

    private static function setCookie(string $value, int $expires): void
    {
        setcookie(self::COOKIE_NAME, $value, [
            'expires'  => $expires,
            'path'     => (BASE_URL ?: '') . '/',
            'httponly' => true,
            'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'samesite' => 'Lax',
        ]);
        $_COOKIE[self::COOKIE_NAME] = $value;
    }
}
