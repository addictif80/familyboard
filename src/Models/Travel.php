<?php
namespace App\Models;

use App\Core\Database;

class Travel
{
    public const RESERVATION_TYPES = [
        'transport'   => ['label' => 'Transport',    'icon' => '✈️'],
        'hebergement' => ['label' => 'Hébergement',  'icon' => '🏨'],
        'activite'    => ['label' => 'Activité',      'icon' => '🎟️'],
        'autre'       => ['label' => 'Autre',        'icon' => '📌'],
    ];

    public static function getByFamily(int $familyId): array
    {
        return Database::fetchAll('SELECT * FROM travels WHERE family_id=? ORDER BY start_date DESC', [$familyId]);
    }

    public static function getById(int $id): ?array
    {
        return Database::fetch('SELECT * FROM travels WHERE id=?', [$id]);
    }

    public static function create(int $familyId, int $userId, array $d): int
    {
        return Database::insert(
            'INSERT INTO travels (family_id, title, destination, start_date, end_date, budget_cents, notes, created_by)
             VALUES (?,?,?,?,?,?,?,?)',
            [$familyId, $d['title'], $d['destination'], $d['start_date'], $d['end_date'], $d['budget_cents'], $d['notes'], $userId]
        );
    }

    public static function update(int $id, int $familyId, array $d): void
    {
        Database::execute(
            'UPDATE travels SET title=?, destination=?, start_date=?, end_date=?, budget_cents=?, notes=? WHERE id=? AND family_id=?',
            [$d['title'], $d['destination'], $d['start_date'], $d['end_date'], $d['budget_cents'], $d['notes'], $id, $familyId]
        );
    }

    public static function delete(int $id, int $familyId): void
    {
        Database::execute('DELETE FROM travels WHERE id=? AND family_id=?', [$id, $familyId]);
    }

    public static function getReservations(int $travelId): array
    {
        return Database::fetchAll('SELECT * FROM travel_reservations WHERE travel_id=? ORDER BY reservation_date IS NULL, reservation_date', [$travelId]);
    }

    public static function getReservationById(int $id): ?array
    {
        return Database::fetch('SELECT * FROM travel_reservations WHERE id=?', [$id]);
    }

    public static function addReservation(int $travelId, int $userId, array $d): int
    {
        return Database::insert(
            'INSERT INTO travel_reservations (travel_id, type, title, confirmation_number, cost_cents, reservation_date, notes, created_by) VALUES (?,?,?,?,?,?,?,?)',
            [$travelId, $d['type'], $d['title'], $d['confirmation_number'], $d['cost_cents'], $d['reservation_date'], $d['notes'], $userId]
        );
    }

    public static function deleteReservation(int $id, int $travelId): void
    {
        Database::execute('DELETE FROM travel_reservations WHERE id=? AND travel_id=?', [$id, $travelId]);
    }
}
