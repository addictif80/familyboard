<?php
namespace App\Core;

class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
            session_set_cookie_params([
                'lifetime' => SESSION_LIFETIME,
                'path'     => (BASE_URL ?: '') . '/',
                'httponly' => true,
                'secure'   => self::isHttps(),
                'samesite' => 'Lax',
            ]);
            session_start();
        }
    }

    /**
     * Déploiement documenté (voir .htaccess) : Apache est derrière un reverse proxy qui gère le
     * TLS et transmet du HTTP en interne — $_SERVER['HTTPS'] n'est alors jamais renseigné, ce qui
     * désactivait silencieusement le flag Secure sur le cookie de session ET le cookie "se
     * souvenir de moi" (365 jours) même en production HTTPS. On fait donc aussi confiance à
     * X-Forwarded-Proto, seul moyen dont PHP dispose ici pour savoir si la connexion d'origine
     * était chiffrée.
     */
    public static function isHttps(): bool
    {
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') return true;
        return strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public static function delete(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function destroy(): void
    {
        session_destroy();
        $_SESSION = [];
    }

    public static function flash(string $key, string $value): void
    {
        $_SESSION['_flash'][$key] = $value;
    }

    public static function getFlash(string $key): ?string
    {
        $val = $_SESSION['_flash'][$key] ?? null;
        unset($_SESSION['_flash'][$key]);
        return $val;
    }

    public static function isLoggedIn(): bool
    {
        return isset($_SESSION['user_id']);
    }

    public static function userId(): ?int
    {
        return $_SESSION['user_id'] ?? null;
    }

    public static function user(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    public static function login(array $user): void
    {
        // Régénère l'ID de session à chaque authentification (fixation de session) : sans ça,
        // un ID de session connu à l'avance par un attaquant (cookie planté, sous-domaine
        // partagé...) resterait valide après connexion et deviendrait une session authentifiée.
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['family_id'] = $user['family_id'];
        $_SESSION['user'] = $user;
        $_SESSION['login_at'] = time();
    }
}
