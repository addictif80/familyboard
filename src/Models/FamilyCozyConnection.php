<?php
namespace App\Models;

use App\Core\Crypto;
use App\Core\Database;

/**
 * Connexion Cozy Cloud d'une famille (une instance par famille, voir App\Core\CozyProvisioner) —
 * access_token chiffré via App\Core\Crypto, jamais en clair en base (même exigence que les mots
 * de passe LDAP/Synology, voir StorageSettings). client_secret/refresh_token ne sont pas
 * utilisés : le jeton est généré directement côté serveur (cozy-stack instances token-oauth,
 * voir CozyProvisioner::mintToken()), jamais via le parcours OAuth navigateur habituel.
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
        $row['access_token'] = $row['access_token'] ? Crypto::decrypt($row['access_token']) : null;
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

    public static function storeOAuthClient(int $id, string $clientId): void
    {
        Database::execute(
            "UPDATE family_cozy_connections SET client_id=?, status='pending_oauth' WHERE id=?",
            [$clientId, $id]
        );
    }

    /** Jeton généré via cozy-stack instances token-oauth --expire 87600h (10 ans, voir
     *  CozyProvisioner::mintToken()) — pas de notion de rafraîchissement : un jeton expiré (ou
     *  jamais obtenu) se régénère simplement en rappelant mintToken(), jamais via un
     *  refresh_token OAuth classique. */
    public static function storeToken(int $id, string $accessToken): void
    {
        $expiresAt = date('Y-m-d H:i:s', time() + 87600 * 3600);
        Database::execute(
            "UPDATE family_cozy_connections SET access_token=?, token_expires_at=?, status='connected', last_error=NULL WHERE id=?",
            [Crypto::encrypt($accessToken), $expiresAt, $id]
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
