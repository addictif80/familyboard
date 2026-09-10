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
            'INSERT INTO meters (family_id, name, meter_type, unit, provider, contract_ref, created_by) VALUES (?,?,?,?,?,?,?)',
            [$familyId, $d['name'], $d['meter_type'], $d['unit'], $d['provider'], $d['contract_ref'], $userId]
        );
    }

    public static function update(int $id, int $familyId, array $d): void
    {
        Database::execute(
            'UPDATE meters SET name=?, meter_type=?, unit=?, provider=?, contract_ref=? WHERE id=? AND family_id=?',
            [$d['name'], $d['meter_type'], $d['unit'], $d['provider'], $d['contract_ref'], $id, $familyId]
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
            'INSERT INTO meter_readings (meter_id, reading_at, `value`, notes, created_by) VALUES (?,?,?,?,?)',
            [$meterId, $d['reading_at'], $d['value'], $d['notes'], $userId]
        );
    }

    public static function deleteReading(int $id, int $meterId): void
    {
        Database::execute('DELETE FROM meter_readings WHERE id=? AND meter_id=?', [$id, $meterId]);
    }

    /** Consommation entre chaque relevé consécutif (delta), pour visualiser la conso par
     *  période plutôt que le seul index cumulatif. */
    public static function consumptionSeries(array $readings): array
    {
        $series = [];
        $prev = null;
        foreach ($readings as $r) {
            $series[] = [
                'id' => (int)$r['id'],
                'reading_at' => $r['reading_at'],
                'value' => (float)$r['value'],
                'notes' => $r['notes'],
                'consumption' => $prev !== null ? round((float)$r['value'] - $prev, 2) : null,
            ];
            $prev = (float)$r['value'];
        }
        return $series;
    }
}
