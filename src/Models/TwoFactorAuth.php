<?php
namespace App\Models;

use App\Core\Database;

class TwoFactorAuth
{
    private const EMAIL_CODE_TTL_MINUTES = 10;

    public static function getMethod(int $userId): ?string
    {
        $row = Database::fetch('SELECT two_factor_method FROM users WHERE id = ?', [$userId]);
        return $row['two_factor_method'] ?? null;
    }

    public static function getTotpSecret(int $userId): ?string
    {
        $row = Database::fetch('SELECT totp_secret FROM users WHERE id = ?', [$userId]);
        return $row['totp_secret'] ?? null;
    }

    /** Active l'authentification par application : révoque aussi les sessions "se souvenir de
     *  moi" existantes pour forcer une re-vérification 2FA sur tous les appareils déjà connectés. */
    public static function enableTotp(int $userId, string $secret): void
    {
        Database::execute('UPDATE users SET two_factor_method = ?, totp_secret = ? WHERE id = ?', ['totp', $secret, $userId]);
        AuthToken::deleteForUser($userId);
    }

    public static function enableEmail(int $userId): void
    {
        Database::execute('UPDATE users SET two_factor_method = ?, totp_secret = NULL WHERE id = ?', ['email', $userId]);
        AuthToken::deleteForUser($userId);
    }

    public static function disable(int $userId): void
    {
        Database::execute('UPDATE users SET two_factor_method = NULL, totp_secret = NULL WHERE id = ?', [$userId]);
        Database::execute('DELETE FROM two_factor_codes WHERE user_id = ?', [$userId]);
    }

    /** Génère un code à usage unique (10 min) et retourne sa valeur en clair pour l'envoyer par email. */
    public static function issueEmailCode(int $userId): string
    {
        $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiresAt = date('Y-m-d H:i:s', time() + self::EMAIL_CODE_TTL_MINUTES * 60);
        Database::execute('DELETE FROM two_factor_codes WHERE user_id = ?', [$userId]);
        Database::execute(
            'INSERT INTO two_factor_codes (user_id, code_hash, expires_at) VALUES (?, ?, ?)',
            [$userId, hash('sha256', $code), $expiresAt]
        );
        return $code;
    }

    public static function verifyEmailCode(int $userId, string $code): bool
    {
        $row = Database::fetch('SELECT * FROM two_factor_codes WHERE user_id = ? AND expires_at > NOW()', [$userId]);
        if (!$row || !hash_equals($row['code_hash'], hash('sha256', trim($code)))) return false;
        Database::execute('DELETE FROM two_factor_codes WHERE id = ?', [$row['id']]);
        return true;
    }
}
