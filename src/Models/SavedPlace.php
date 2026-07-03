<?php
namespace App\Models;

use App\Core\Database;

class SavedPlace
{
    public static function getByFamily(int $familyId): array
    {
        return Database::fetchAll('SELECT * FROM saved_places WHERE family_id=? ORDER BY name', [$familyId]);
    }

    public static function getById(int $id): ?array
    {
        return Database::fetch('SELECT * FROM saved_places WHERE id=?', [$id]);
    }

    public static function create(int $familyId, int $userId, string $name, float $lat, float $lng, int $radiusM = 150): int
    {
        return Database::insert(
            'INSERT INTO saved_places (family_id, name, lat, lng, radius_m, created_by) VALUES (?,?,?,?,?,?)',
            [$familyId, $name, $lat, $lng, $radiusM, $userId]
        );
    }

    public static function delete(int $id): void
    {
        Database::execute('DELETE FROM saved_places WHERE id=?', [$id]);
    }

    /** Nearest saved place within its radius, or null if none matches. */
    public static function findNearby(int $familyId, float $lat, float $lng): ?array
    {
        $places = self::getByFamily($familyId);
        $best = null;
        $bestDist = null;

        foreach ($places as $place) {
            $dist = self::haversineMeters($lat, $lng, (float)$place['lat'], (float)$place['lng']);
            if ($dist <= (int)$place['radius_m'] && ($bestDist === null || $dist < $bestDist)) {
                $best = $place;
                $bestDist = $dist;
            }
        }

        return $best;
    }

    private static function haversineMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
