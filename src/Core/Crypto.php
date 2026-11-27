<?php
namespace App\Core;

/**
 * Chiffrement au repos pour les secrets sensibles stockés en base (ex. secret TOTP) — une fuite
 * de la base seule (dump SQL, sauvegarde mal protégée...) ne doit pas suffire à régénérer des
 * codes 2FA valides ; il faut aussi la clé applicative, qui ne vit jamais dans la base.
 */
class Crypto
{
    private const CIPHER = 'aes-256-gcm';
    private const PREFIX = 'v1:';

    public static function encrypt(string $plaintext): string
    {
        $key = self::getKey();
        $iv = random_bytes(openssl_cipher_iv_length(self::CIPHER));
        $tag = '';
        $ciphertext = openssl_encrypt($plaintext, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv, $tag);
        return self::PREFIX . base64_encode($iv . $tag . $ciphertext);
    }

    /** Retourne null si la valeur n'est pas déchiffrable (mauvaise clé, donnée corrompue). */
    public static function decrypt(string $value): ?string
    {
        if (!str_starts_with($value, self::PREFIX)) {
            // Valeur en clair héritée d'avant l'introduction du chiffrement : on la retourne
            // telle quelle, l'appelant est responsable de la re-chiffrer à la prochaine écriture.
            return $value;
        }
        $raw = base64_decode(substr($value, strlen(self::PREFIX)), true);
        if ($raw === false) return null;

        $ivLen = openssl_cipher_iv_length(self::CIPHER);
        $tagLen = 16;
        if (strlen($raw) < $ivLen + $tagLen) return null;

        $iv = substr($raw, 0, $ivLen);
        $tag = substr($raw, $ivLen, $tagLen);
        $ciphertext = substr($raw, $ivLen + $tagLen);

        $plaintext = openssl_decrypt($ciphertext, self::CIPHER, self::getKey(), OPENSSL_RAW_DATA, $iv, $tag);
        return $plaintext === false ? null : $plaintext;
    }

    private static function getKey(): string
    {
        if (defined('APP_ENCRYPTION_KEY') && APP_ENCRYPTION_KEY !== '') {
            return hash('sha256', APP_ENCRYPTION_KEY, true);
        }
        // Pas de clé configurée explicitement (APP_KEY) : on en génère une et on la persiste
        // hors du webroot, pour que l'app fonctionne sans configuration manuelle obligatoire —
        // mais définir APP_KEY reste recommandé (survit à une réinstallation du dossier storage).
        $keyFile = BASE_PATH . '/storage/app_key.bin';
        if (!is_file($keyFile)) {
            $dir = dirname($keyFile);
            if (!is_dir($dir)) @mkdir($dir, 0700, true);
            $generated = random_bytes(32);
            file_put_contents($keyFile, $generated, LOCK_EX);
            @chmod($keyFile, 0600);
            return $generated;
        }
        return (string)file_get_contents($keyFile);
    }
}
