<?php
namespace App\Models;

use App\Core\Database;

class Meter
{
    public const TYPES = [
        'eau'         => ['label' => 'Eau',         'icon' => '💧', 'unit' => 'm³'],
        'electricite' => ['label' => 'Électricité', 'icon' => '⚡', 'unit' => 'kWh'],
        'gaz'         => ['label' => 'Gaz',         'icon' => '🔥', 'unit' => 'kWh'],
        'autre'       => ['label' => 'Autre',       'icon' => '📊', 'unit' => ''],
    ];

    public static function getByFamily(int $familyId): array
    {
        return Database::fetchAll('SELECT * FROM meters WHERE family_id=? ORDER BY name', [$familyId]);
    }

    public static function getById(int $id): ?array
    {
        return Database::fetch('SELECT * FROM meters WHERE id=?', [$id]);
    }

    public static function create(int $familyId, int $userId, array $d): int
    {
        return Database::insert(
            'INSERT INTO meters (family_id, name, meter_type, has_hp_hc, unit, provider, contract_ref, created_by) VALUES (?,?,?,?,?,?,?,?)',
            [$familyId, $d['name'], $d['meter_type'], $d['has_hp_hc'], $d['unit'], $d['provider'], $d['contract_ref'], $userId]
        );
    }

    public static function update(int $id, int $familyId, array $d): void
    {
        Database::execute(
            'UPDATE meters SET name=?, meter_type=?, has_hp_hc=?, unit=?, provider=?, contract_ref=? WHERE id=? AND family_id=?',
            [$d['name'], $d['meter_type'], $d['has_hp_hc'], $d['unit'], $d['provider'], $d['contract_ref'], $id, $familyId]
        );
    }

    public static function delete(int $id, int $familyId): void
    {
        Database::execute('DELETE FROM meters WHERE id=? AND family_id=?', [$id, $familyId]);
    }

    public static function getReadings(int $meterId): array
    {
        return Database::fetchAll('SELECT * FROM meter_readings WHERE meter_id=? ORDER BY reading_at', [$meterId]);
    }

    public static function getReadingById(int $id): ?array
    {
        return Database::fetch('SELECT * FROM meter_readings WHERE id=?', [$id]);
    }

    public static function addReading(int $meterId, int $userId, array $d): int
    {
        return Database::insert(
            'INSERT INTO meter_readings (meter_id, reading_at, `value`, value_hp, value_hc, notes, created_by) VALUES (?,?,?,?,?,?,?)',
            [$meterId, $d['reading_at'], $d['value'], $d['value_hp'], $d['value_hc'], $d['notes'], $userId]
        );
    }

    public static function deleteReading(int $id, int $meterId): void
    {
        Database::execute('DELETE FROM meter_readings WHERE id=? AND meter_id=?', [$id, $meterId]);
    }

    /** Consommation entre chaque relevé consécutif (delta), pour visualiser la conso par
     *  période plutôt que le seul index cumulatif. Pour un compteur heures pleines/heures
     *  creuses, value_hp/value_hc sont deux index cumulatifs indépendants (pas une répartition
     *  d'un même total) : chacun a son propre delta, et la conso totale de la période est leur
     *  somme — jamais un delta calculé sur `value`, qui reste NULL dans ce cas. */
    public static function consumptionSeries(array $readings): array
    {
        $series = [];
        $prev = $prevHp = $prevHc = null;
        foreach ($readings as $r) {
            $hasHpHc = $r['value_hp'] !== null || $r['value_hc'] !== null;
            $hp = $r['value_hp'] !== null ? (float)$r['value_hp'] : null;
            $hc = $r['value_hc'] !== null ? (float)$r['value_hc'] : null;
            $consumptionHp = ($hp !== null && $prevHp !== null) ? round($hp - $prevHp, 2) : null;
            $consumptionHc = ($hc !== null && $prevHc !== null) ? round($hc - $prevHc, 2) : null;
            $consumption = $hasHpHc
                ? (($consumptionHp !== null || $consumptionHc !== null) ? round(($consumptionHp ?? 0) + ($consumptionHc ?? 0), 2) : null)
                : ($prev !== null && $r['value'] !== null ? round((float)$r['value'] - $prev, 2) : null);
            $series[] = [
                'id' => (int)$r['id'],
                'reading_at' => $r['reading_at'],
                'value' => $r['value'] !== null ? (float)$r['value'] : null,
                'value_hp' => $hp,
                'value_hc' => $hc,
                'notes' => $r['notes'],
                'consumption' => $consumption,
                'consumption_hp' => $consumptionHp,
                'consumption_hc' => $consumptionHc,
            ];
            if ($r['value'] !== null) $prev = (float)$r['value'];
            if ($hp !== null) $prevHp = $hp;
            if ($hc !== null) $prevHc = $hc;
        }
        return $series;
    }
}
