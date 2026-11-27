<?php
namespace App\Models;

use App\Core\Crypto;
use App\Core\Database;
use App\Core\Totp;

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
        if (empty($row['totp_secret'])) return null;
        $secret = Crypto::decrypt($row['totp_secret']);
        // Migration transparente : un secret encore en clair (installation antérieure au
        // chiffrement) est re-sauvegardé chiffré dès qu'on le relit, sans action de l'utilisateur.
        if ($secret !== null && !str_starts_with($row['totp_secret'], 'v1:')) {
            Database::execute('UPDATE users SET totp_secret = ? WHERE id = ?', [Crypto::encrypt($secret), $userId]);
        }
        return $secret;
    }

    /** Vérifie un code TOTP ET empêche son rejeu : un code déjà accepté pour ce pas de temps (ou
     *  un pas antérieur) n'est plus jamais réaccepté, même s'il reste dans la fenêtre ±1 pas. */
    public static function verifyTotpCode(int $userId, string $secret, string $code): bool
    {
        $counter = Totp::matchCounter($secret, $code);
        if ($counter === null) return false;

        $row = Database::fetch('SELECT last_totp_counter FROM users WHERE id = ?', [$userId]);
        $last = $row['last_totp_counter'] ?? null;
        if ($last !== null && $counter <= (int)$last) return false;

        Database::execute('UPDATE users SET last_totp_counter = ? WHERE id = ?', [$counter, $userId]);
        return true;
    }

    /** Active l'authentification par application : révoque aussi les sessions "se souvenir de
     *  moi" existantes pour forcer une re-vérification 2FA sur tous les appareils déjà connectés. */
    public static function enableTotp(int $userId, string $secret): void
    {
        Database::execute('UPDATE users SET two_factor_method = ?, totp_secret = ? WHERE id = ?', ['totp', Crypto::encrypt($secret), $userId]);
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

    /**
     * Statut de la politique "2FA obligatoire" (réglage admin global) pour un utilisateur donné.
     * Amorce paresseusement le délai de grâce individuel (two_factor_required_notice_at) la
     * première fois qu'on constate que la politique s'applique à lui sans qu'il ait de méthode
     * active — chaque utilisateur a donc son propre décompte à partir du moment où il est
     * concerné, pas depuis l'activation globale par l'admin.
     *
     * @return array{enforced: bool, blocked?: bool, days_left?: int}
     */
    public static function getPolicyStatus(int $userId): array
    {
        $requireAll = (bool)(int)(AppSetting::get('require_2fa_all') ?? '0');
        if (!$requireAll || self::getMethod($userId) !== null) {
            return ['enforced' => false];
        }

        $graceDays = max(0, (int)(AppSetting::get('require_2fa_grace_days') ?? '7'));
        $row = Database::fetch('SELECT two_factor_required_notice_at FROM users WHERE id = ?', [$userId]);
        $noticeAt = $row['two_factor_required_notice_at'] ?? null;
        if ($noticeAt === null) {
            $noticeAt = gmdate('Y-m-d H:i:s');
            Database::execute('UPDATE users SET two_factor_required_notice_at = ? WHERE id = ?', [$noticeAt, $userId]);
        }

        // Les colonnes DATETIME de la base sont stockées en UTC (voir DateHelper::fromUtc) ;
        // il faut parser explicitement en UTC, sinon strtotime() utiliserait le fuseau horaire
        // par défaut de PHP (celui de la famille, ex. Europe/Paris) et décalerait l'échéance.
        $noticeTs = (new \DateTime($noticeAt, new \DateTimeZone('UTC')))->getTimestamp();
        $deadline = $noticeTs + $graceDays * 86400;
        return [
            'enforced'  => true,
            'blocked'   => time() >= $deadline,
            'days_left' => max(0, (int)ceil(($deadline - time()) / 86400)),
        ];
    }
}
