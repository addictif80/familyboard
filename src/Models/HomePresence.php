<?php
namespace App\Models;

use App\Core\Database;

/**
 * "Quelqu'un est-il à la maison ?" — déduit de la dernière position connue de chaque membre
 * (users.last_lat/last_lng/last_location_at, mise à jour périodiquement côté client tant que
 * l'app est ouverte et que le suivi n'est pas désactivé) comparée au domicile de la famille
 * (families.home_lat/home_lng/home_radius_m). Utilisé uniquement par les minuteurs (alerte
 * différée) — jamais un historique de déplacement, une seule position par utilisateur est
 * conservée (voir add_home_tracking.sql).
 */
class HomePresence
{
    /** Une position plus ancienne que ça n'est plus considérée fiable pour dire "est chez soi". */
    private const STALE_MINUTES = 30;

    /**
     * true = quelqu'un est chez soi (ou la fonction n'est pas configurée/utilisée pour cette
     * famille — fail-open pour ne jamais bloquer indéfiniment une alarme faute de données).
     */
    public static function isAnyoneHome(int $familyId): bool
    {
        $family = Database::fetch('SELECT home_lat, home_lng, home_radius_m FROM families WHERE id=?', [$familyId]);
        if (!$family || $family['home_lat'] === null || $family['home_lng'] === null) {
            return true;
        }

        $members = Database::fetchAll(
            "SELECT last_lat, last_lng, last_location_at FROM users
             WHERE family_id=? AND location_tracking_enabled=1 AND last_location_at IS NOT NULL",
            [$familyId]
        );
        // Personne n'a jamais partagé sa position : la fonction n'est pas réellement utilisée
        // par cette famille, ne pas s'en servir pour retenir indéfiniment une alarme.
        if (!$members) return true;

        $cutoff = time() - self::STALE_MINUTES * 60;
        foreach ($members as $m) {
            if (strtotime($m['last_location_at'] . ' UTC') < $cutoff) continue;
            $dist = self::haversineMeters(
                (float)$m['last_lat'], (float)$m['last_lng'],
                (float)$family['home_lat'], (float)$family['home_lng']
            );
            if ($dist <= (int)$family['home_radius_m']) return true;
        }
        return false;
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
