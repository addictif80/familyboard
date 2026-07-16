<?php
namespace App\Core;

/** Jeton CSRF simple pour les formulaires POST classiques (non-JSON) du panneau admin. */
class Csrf
{
    private const SESSION_KEY = 'csrf_token';

    public static function token(): string
    {
        if (empty($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        }
        return $_SESSION[self::SESSION_KEY];
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . htmlspecialchars(self::token()) . '">';
    }

    public static function verify(): bool
    {
        $sent = $_POST['_csrf'] ?? '';
        $expected = $_SESSION[self::SESSION_KEY] ?? '';
        return $sent !== '' && $expected !== '' && hash_equals($expected, $sent);
    }
}
