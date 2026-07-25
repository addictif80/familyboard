<?php
namespace App\Core;

/**
 * TOTP (RFC 6238) minimal, sans dépendance externe — compatible avec toute application
 * d'authentification standard (Google Authenticator, Authy, Ente Auth, Bitwarden...).
 * Pas de génération d'image QR : la clé est affichée en saisie manuelle + lien otpauth://
 * (la plupart des apps l'acceptent aussi en collant le lien), pour éviter d'ajouter une
 * dépendance juste pour ça.
 */
class Totp
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    private const PERIOD = 30;
    private const DIGITS = 6;

    public static function generateSecret(int $bytes = 20): string
    {
        return self::base32Encode(random_bytes($bytes));
    }

    public static function provisioningUri(string $secretBase32, string $accountName, string $issuer = 'FamilyBoard'): string
    {
        $label = rawurlencode($issuer . ':' . $accountName);
        $query = http_build_query([
            'secret'    => $secretBase32,
            'issuer'    => $issuer,
            'algorithm' => 'SHA1',
            'digits'    => self::DIGITS,
            'period'    => self::PERIOD,
        ]);
        return "otpauth://totp/{$label}?{$query}";
    }

    /** Vérifie un code à 6 chiffres, avec une tolérance de ±1 pas (30s) pour l'horloge du téléphone. */
    public static function verifyCode(string $secretBase32, string $code, int $window = 1): bool
    {
        return self::matchCounter($secretBase32, $code, $window) !== null;
    }

    /** Comme verifyCode(), mais retourne le pas de temps (compteur HOTP) qui a matché, pour
     *  permettre à l'appelant de bloquer le rejeu d'un code déjà utilisé (voir
     *  TwoFactorAuth::verifyTotpCode) — verifyCode() seul ne peut pas empêcher qu'un même code
     *  intercepté soit soumis plusieurs fois tant qu'il reste dans sa fenêtre de validité. */
    public static function matchCounter(string $secretBase32, string $code, int $window = 1): ?int
    {
        $code = preg_replace('/\D/', '', $code);
        if (strlen($code) !== self::DIGITS) return null;

        $counter = intdiv(time(), self::PERIOD);
        for ($i = -$window; $i <= $window; $i++) {
            if (hash_equals(self::hotp($secretBase32, $counter + $i), $code)) return $counter + $i;
        }
        return null;
    }

    private static function hotp(string $secretBase32, int $counter): string
    {
        $key = self::base32Decode($secretBase32);
        $binCounter = pack('N*', 0, $counter);
        $hash = hash_hmac('sha1', $binCounter, $key, true);
        $offset = ord($hash[19]) & 0x0F;
        $code = ((ord($hash[$offset]) & 0x7F) << 24)
            | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8)
            | (ord($hash[$offset + 3]) & 0xFF);
        return str_pad((string)($code % (10 ** self::DIGITS)), self::DIGITS, '0', STR_PAD_LEFT);
    }

    private static function base32Encode(string $data): string
    {
        $bits = '';
        foreach (str_split($data) as $char) {
            $bits .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        }
        $output = '';
        foreach (str_split($bits, 5) as $chunk) {
            $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
            $output .= self::ALPHABET[bindec($chunk)];
        }
        return $output;
    }

    private static function base32Decode(string $b32): string
    {
        $b32 = strtoupper(preg_replace('/[^A-Z2-7]/', '', $b32));
        $bits = '';
        foreach (str_split($b32) as $char) {
            $pos = strpos(self::ALPHABET, $char);
            if ($pos === false) continue;
            $bits .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
        }
        $output = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) $output .= chr(bindec($byte));
        }
        return $output;
    }
}
