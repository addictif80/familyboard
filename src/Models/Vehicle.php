<?php
namespace App\Models;

use App\Core\Database;

class Vehicle
{
    public static function getByFamily(int $familyId): array
    {
        return Database::fetchAll('SELECT * FROM vehicles WHERE family_id=? ORDER BY name', [$familyId]);
    }

    public static function getById(int $id): ?array
    {
        return Database::fetch('SELECT * FROM vehicles WHERE id=?', [$id]);
    }

    public static function create(int $familyId, int $userId, array $d): int
    {
        return Database::insert(
            'INSERT INTO vehicles (family_id, name, brand, model, plate, purchase_date, mileage, insurance_company, insurance_expiry, technical_control_expiry, notes, created_by)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
            [$familyId, $d['name'], $d['brand'], $d['model'], $d['plate'], $d['purchase_date'], $d['mileage'],
             $d['insurance_company'], $d['insurance_expiry'], $d['technical_control_expiry'], $d['notes'], $userId]
        );
    }

    public static function update(int $id, int $familyId, array $d): void
    {
        Database::execute(
            'UPDATE vehicles SET name=?, brand=?, model=?, plate=?, purchase_date=?, mileage=?, insurance_company=?, insurance_expiry=?, technical_control_expiry=?, notes=? WHERE id=? AND family_id=?',
            [$d['name'], $d['brand'], $d['model'], $d['plate'], $d['purchase_date'], $d['mileage'],
             $d['insurance_company'], $d['insurance_expiry'], $d['technical_control_expiry'], $d['notes'], $id, $familyId]
        );
    }

    public static function delete(int $id, int $familyId): void
    {
        Database::execute('DELETE FROM vehicles WHERE id=? AND family_id=?', [$id, $familyId]);
    }

    public static function getMaintenance(int $vehicleId): array
    {
        return Database::fetchAll('SELECT * FROM vehicle_maintenance WHERE vehicle_id=? ORDER BY done_at DESC', [$vehicleId]);
    }

    public static function getMaintenanceById(int $id): ?array
    {
        return Database::fetch('SELECT * FROM vehicle_maintenance WHERE id=?', [$id]);
    }

    public static function addMaintenance(int $vehicleId, int $userId, array $d): int
    {
        return Database::insert(
            'INSERT INTO vehicle_maintenance (vehicle_id, title, done_at, mileage, cost_cents, notes, created_by) VALUES (?,?,?,?,?,?,?)',
            [$vehicleId, $d['title'], $d['done_at'], $d['mileage'], $d['cost_cents'], $d['notes'], $userId]
        );
    }

    public static function deleteMaintenance(int $id, int $vehicleId): void
    {
        Database::execute('DELETE FROM vehicle_maintenance WHERE id=? AND vehicle_id=?', [$id, $vehicleId]);
    }
}
