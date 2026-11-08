<?php
namespace App\Models;

use App\Core\Database;

/**
 * Lien de partage ponctuel (sans compte) du carnet de santé d'un sujet précis (enfant ou membre),
 * pour le transmettre à un médecin avant un rendez-vous — voir aussi la note dans
 * database/add_health_share_links.sql sur l'expiration toujours obligatoire.
 */
class HealthShareLink
{
    public const DURATIONS_DAYS = [7, 14, 30];

    public static function findByToken(string $token): ?array
    {
        return Database::fetch('SELECT * FROM health_share_links WHERE token=?', [$token]);
    }

    /** Vrai uniquement si le lien existe, n'a pas été révoqué, et n'a pas expiré. */
    public static function isActive(array $link): bool
    {
        if ($link['revoked_at']) return false;
        return new \DateTime($link['expires_at']) > new \DateTime();
    }

    public static function getActiveForSubject(int $familyId, string $subjectType, int $subjectId): array
    {
        return array_values(array_filter(
            Database::fetchAll(
                'SELECT * FROM health_share_links WHERE family_id=? AND subject_type=? AND subject_id=? ORDER BY created_at DESC',
                [$familyId, $subjectType, $subjectId]
            ),
            fn($l) => self::isActive($l)
        ));
    }

    public static function create(int $familyId, int $userId, string $subjectType, int $subjectId, int $durationDays): array
    {
        $durationDays = in_array($durationDays, self::DURATIONS_DAYS, true) ? $durationDays : 7;
        $token = bin2hex(random_bytes(24));
        $id = Database::insert(
            'INSERT INTO health_share_links (family_id, subject_type, subject_id, token, created_by, expires_at)
             VALUES (?,?,?,?,?, DATE_ADD(NOW(), INTERVAL ? DAY))',
            [$familyId, $subjectType, $subjectId, $token, $userId, $durationDays]
        );
        return Database::fetch('SELECT * FROM health_share_links WHERE id=?', [$id]);
    }

    public static function revoke(int $id, int $familyId): void
    {
        Database::execute(
            "UPDATE health_share_links SET revoked_at=NOW() WHERE id=? AND family_id=? AND revoked_at IS NULL",
            [$id, $familyId]
        );
    }
}
