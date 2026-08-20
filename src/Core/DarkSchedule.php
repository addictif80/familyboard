<?php
namespace App\Core;

/**
 * Mode sombre programmé pour l'écran mural / le kiosque — horaires fixes ou coucher/lever du
 * soleil (via la fonction native date_sun_info(), calculée depuis le domicile de la famille,
 * voir App\Models\HomePresence). Jamais utilisé pour l'app normale, qui garde son propre bouton
 * clair/sombre par utilisateur.
 */
class DarkSchedule
{
    public static function isDarkNow(array $family): bool
    {
        $type = $family['dark_mode_type'] ?? 'off';
        if ($type === 'off') return false;

        $tz  = new \DateTimeZone($family['timezone'] ?? 'Europe/Paris');
        $now = new \DateTime('now', $tz);

        if ($type === 'fixed') {
            if (empty($family['dark_mode_start']) || empty($family['dark_mode_end'])) return false;
            return self::withinWindow($now, $family['dark_mode_start'], $family['dark_mode_end']);
        }

        if ($type === 'sunset') {
            if ($family['home_lat'] === null || $family['home_lng'] === null) return false;
            $info = date_sun_info($now->getTimestamp(), (float)$family['home_lat'], (float)$family['home_lng']);
            // false/true (jour ou nuit polaire) au lieu d'un timestamp : pas de coucher/lever
            // exploitable, on ne bascule jamais en sombre plutôt que de deviner.
            if (!is_array($info) || !is_int($info['sunset']) || !is_int($info['sunrise'])) return false;

            $sunset       = (new \DateTime('@' . $info['sunset']))->setTimezone($tz);
            $sunriseToday = (new \DateTime('@' . $info['sunrise']))->setTimezone($tz);
            // Sombre du coucher du soleil d'aujourd'hui jusqu'au lever du lendemain — couvre la
            // tranche après minuit (avant le lever du jour) sans avoir besoin du coucher de demain.
            return $now >= $sunset || $now < $sunriseToday;
        }

        return false;
    }

    private static function withinWindow(\DateTime $now, string $start, string $end): bool
    {
        $nowMin = (int)$now->format('H') * 60 + (int)$now->format('i');
        [$sh, $sm] = array_map('intval', explode(':', $start));
        [$eh, $em] = array_map('intval', explode(':', $end));
        $startMin = $sh * 60 + $sm;
        $endMin   = $eh * 60 + $em;
        if ($startMin === $endMin) return false;
        if ($startMin < $endMin) return $nowMin >= $startMin && $nowMin < $endMin;
        // Fenêtre à cheval sur minuit (ex. 20:00 → 07:00).
        return $nowMin >= $startMin || $nowMin < $endMin;
    }
}
