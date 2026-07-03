<?php
namespace App\Models;

use App\Core\Database;

class LocationCheckin
{
    public static function create(int $userId, int $familyId, float $lat, float $lng, ?int $placeId, ?string $note, int $durationMinutes): int
    {
        return Database::insert(
            'INSERT INTO location_checkins (user_id, family_id, lat, lng, place_id, note, expires_at)
             VALUES (?,?,?,?,?,?, DATE_ADD(NOW(), INTERVAL ? MINUTE))',
            [$userId, $familyId, $lat, $lng, $placeId, $note, $durationMinutes]
        );
    }

    /** One row per user: their most recent still-active check-in. */
    public static function getCurrentByFamily(int $familyId): array
    {
        return Database::fetchAll(
            'SELECT lc.*, u.name as user_name, u.avatar as user_avatar, u.color as user_color,
                    sp.name as place_name
             FROM location_checkins lc
             JOIN users u ON u.id = lc.user_id
             LEFT JOIN saved_places sp ON sp.id = lc.place_id
             WHERE lc.family_id = ?
               AND lc.expires_at > NOW()
               AND lc.id = (
                   SELECT MAX(lc2.id) FROM location_checkins lc2
                   WHERE lc2.user_id = lc.user_id AND lc2.family_id = lc.family_id
                     AND lc2.expires_at > NOW()
               )
             ORDER BY lc.created_at DESC',
            [$familyId]
        );
    }

    public static function clearForUser(int $userId, int $familyId): void
    {
        Database::execute(
            'UPDATE location_checkins SET expires_at = NOW() WHERE user_id=? AND family_id=? AND expires_at > NOW()',
            [$userId, $familyId]
        );
    }
}
