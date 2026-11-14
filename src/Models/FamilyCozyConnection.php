<?php
namespace App\Models;

use App\Core\Crypto;
use App\Core\Database;

/**
 * Connexion Cozy Cloud d'une famille (une instance par famille, voir App\Core\CozyProvisioner) —
 * client_id/client_secret/access_token/refresh_token chiffrés via App\Core\Crypto, jamais en
 * clair en base (même exigence que les mots de passe LDAP/Synology, voir StorageSettings).
 */
class FamilyCozyConnection
{
    public static function findByFamily(int $familyId): ?array
    {
        $row = Database::fetch('SELECT * FROM family_cozy_connections WHERE family_id = ?', [$familyId]);
        return $row ? self::decorate($row) : null;
    }

    /** Connexions prêtes à être synchronisées (jeton déjà échangé). */
    public static function getConnected(): array
    {
        return array_map(
            [self::class, 'decorate'],
            Database::fetchAll("SELECT * FROM family_cozy_connections WHERE status = 'connected'")
        );
    }

    private static function decorate(array $row): array
    {
        $row['client_secret']  = $row['client_secret']  ? Crypto::decrypt($row['client_secret'])  : null;
        $row['access_token']   = $row['access_token']   ? Crypto::decrypt($row['access_token'])   : null;
        $row['refresh_token']  = $row['refresh_token']  ? Crypto::decrypt($row['refresh_token'])  : null;
        return $row;
    }

    /**
     * Slug stable pour le sous-domaine de l'instance Cozy d'une famille (<slug>.<suffixe
     * configuré>) — dérivé du nom au moment de la connexion, désambiguïsé par un suffixe
     * numérique en cas de collision (même principe que l'ancien slug d'alias e-mail, retiré).
     */
    public static function generateDomain(string $familyName, string $suffix): string
    {
        $base = strtolower((string)iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $familyName));
        $base = preg_replace('/[^a-z0-9]+/', '-', $base);
        $base = trim((string)$base, '-');
        if ($base === '') $base = 'famille';
        $base = substr($base, 0, 60);

        $slug = $base;
        $i = 2;
        while (Database::fetch('SELECT id FROM family_cozy_connections WHERE cozy_domain = ?', [$slug . '.' . $suffix])) {
            $slug = $base . '-' . $i;
            $i++;
        }
        return $slug . '.' . $suffix;
    }

    public static function createProvisioning(int $familyId, string $cozyDomain): int
    {
        return Database::insert(
            "INSERT INTO family_cozy_connections (family_id, cozy_domain, status) VALUES (?, ?, 'provisioning')",
            [$familyId, $cozyDomain]
        );
    }

    public static function markPendingOnboarding(int $id): void
    {
        Database::execute("UPDATE family_cozy_connections SET status='pending_onboarding' WHERE id=?", [$id]);
    }

    public static function storeOAuthClient(int $id, string $clientId, string $clientSecret): void
    {
        Database::execute(
            "UPDATE family_cozy_connections SET client_id=?, client_secret=?, status='pending_oauth' WHERE id=?",
            [$clientId, Crypto::encrypt($clientSecret), $id]
        );
    }

    public static function storeTokens(int $id, string $accessToken, ?string $refreshToken, ?int $expiresIn): void
    {
        // expires_in absent de la réponse Cozy observée : on retombe sur 23h (le jeton est
        // documenté valide 24h) pour toujours rafraîchir avec une marge de sécurité.
        $expiresAt = date('Y-m-d H:i:s', time() + ($expiresIn ?: 23 * 3600));
        Database::execute(
            "UPDATE family_cozy_connections SET access_token=?, refresh_token=COALESCE(?, refresh_token),
             token_expires_at=?, status='connected', last_error=NULL WHERE id=?",
            [Crypto::encrypt($accessToken), $refreshToken ? Crypto::encrypt($refreshToken) : null, $expiresAt, $id]
        );
    }

    public static function markSynced(int $id): void
    {
        Database::execute("UPDATE family_cozy_connections SET last_sync_at = NOW() WHERE id = ?", [$id]);
    }

    public static function markError(int $id, string $error): void
    {
        Database::execute(
            "UPDATE family_cozy_connections SET status='error', last_error=? WHERE id=?",
            [mb_substr($error, 0, 2000), $id]
        );
    }

    public static function deleteByFamily(int $familyId): void
    {
        Database::execute('DELETE FROM family_cozy_connections WHERE family_id = ?', [$familyId]);
    }
}
