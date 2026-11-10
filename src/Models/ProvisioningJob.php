<?php
namespace App\Models;

use App\Core\Database;

/**
 * File de jobs de provisioning LDAP/Synology (voir App\Core\StorageProvisioner), rejouable et
 * traitée par cron.php (ou déclenchée à la demande depuis /admin, voir
 * AdminController::runStorageProvisioning — aucun accès SSH requis). payload est du JSON libre
 * selon $jobType (ex. {"password":"v1:..."} chiffré via App\Core\Crypto pour provision_ldap,
 * jamais en clair même temporairement).
 */
class ProvisioningJob
{
    private const MAX_TRIES = 3;
    private const BACKOFF_SECONDS = [10, 30, 90];

    public static function enqueue(int $userId, string $jobType, ?array $payload = null): int
    {
        return Database::insert(
            'INSERT INTO provisioning_jobs (user_id, job_type, payload) VALUES (?,?,?)',
            [$userId, $jobType, $payload !== null ? json_encode($payload) : null]
        );
    }

    /** Jobs prêts à être (re)traités : en attente, ou en échec dont le délai de recul est
     *  écoulé — jamais un job déjà marqué 'done', ni un job en échec définitif (tries épuisés). */
    public static function claimReady(int $limit = 50): array
    {
        return Database::fetchAll(
            "SELECT pj.*, u.ldap_username, u.family_id FROM provisioning_jobs pj
             JOIN users u ON u.id = pj.user_id
             WHERE pj.status IN ('pending','failed')
               AND pj.attempts < ?
               AND (pj.run_after IS NULL OR pj.run_after <= NOW())
             ORDER BY pj.created_at
             LIMIT ?",
            [self::MAX_TRIES, $limit]
        );
    }

    public static function markRunning(int $id): void
    {
        Database::execute("UPDATE provisioning_jobs SET status='running' WHERE id=?", [$id]);
    }

    /** $clearPayload : efface le payload après succès (hygiène — un provision_ldap réussi n'a
     *  plus besoin de conserver le mot de passe chiffré). */
    public static function markDone(int $id, bool $clearPayload = false): void
    {
        $sql = "UPDATE provisioning_jobs SET status='done', last_error=NULL" . ($clearPayload ? ', payload=NULL' : '') . " WHERE id=?";
        Database::execute($sql, [$id]);
    }

    public static function markFailed(int $id, int $attempts, string $error): void
    {
        $backoff = self::BACKOFF_SECONDS[min($attempts - 1, count(self::BACKOFF_SECONDS) - 1)] ?? 90;
        Database::execute(
            "UPDATE provisioning_jobs SET status='failed', attempts=?, last_error=?, run_after=DATE_ADD(NOW(), INTERVAL ? SECOND) WHERE id=?",
            [$attempts, mb_substr($error, 0, 2000), $backoff, $id]
        );
    }

    /** Pour l'écran admin : jobs en attente/échec (pas les 'done', qui n'intéressent personne). */
    public static function getOutstanding(int $limit = 100): array
    {
        return Database::fetchAll(
            "SELECT pj.*, u.name AS user_name, u.family_id FROM provisioning_jobs pj
             JOIN users u ON u.id = pj.user_id
             WHERE pj.status != 'done'
             ORDER BY pj.created_at DESC
             LIMIT ?",
            [$limit]
        );
    }

    /** Évite d'empiler un nouveau job tant qu'un précédent du même type pour ce membre n'a pas
     *  encore été traité (ex. connexions répétées avant le passage du cron). */
    public static function hasOutstanding(int $userId, string $jobType): bool
    {
        return (bool)Database::fetch(
            "SELECT 1 FROM provisioning_jobs WHERE user_id=? AND job_type=? AND status != 'done' LIMIT 1",
            [$userId, $jobType]
        );
    }

    public static function countOutstanding(): int
    {
        $row = Database::fetch("SELECT COUNT(*) AS c FROM provisioning_jobs WHERE status != 'done'");
        return (int)($row['c'] ?? 0);
    }
}
