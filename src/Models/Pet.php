<?php
namespace App\Models;

use App\Core\Database;

class Pet
{
    public const CARE_TYPES = [
        'vaccin'    => ['label' => 'Vaccin',     'icon' => '💉'],
        'vermifuge' => ['label' => 'Vermifuge',  'icon' => '💊'],
        'veto'      => ['label' => 'Vétérinaire', 'icon' => '🩺'],
        'autre'     => ['label' => 'Autre',      'icon' => '🐾'],
    ];

    public static function getByFamily(int $familyId): array
    {
        return Database::fetchAll('SELECT * FROM pets WHERE family_id=? ORDER BY name', [$familyId]);
    }

    public static function getById(int $id): ?array
    {
        return Database::fetch('SELECT * FROM pets WHERE id=?', [$id]);
    }

    public static function create(int $familyId, int $userId, array $d): int
    {
        return Database::insert(
            'INSERT INTO pets (family_id, name, species, breed, birth_date, vet_name, vet_phone, notes, created_by) VALUES (?,?,?,?,?,?,?,?,?)',
            [$familyId, $d['name'], $d['species'], $d['breed'], $d['birth_date'], $d['vet_name'], $d['vet_phone'], $d['notes'], $userId]
        );
    }

    public static function update(int $id, int $familyId, array $d): void
    {
        Database::execute(
            'UPDATE pets SET name=?, species=?, breed=?, birth_date=?, vet_name=?, vet_phone=?, notes=? WHERE id=? AND family_id=?',
            [$d['name'], $d['species'], $d['breed'], $d['birth_date'], $d['vet_name'], $d['vet_phone'], $d['notes'], $id, $familyId]
        );
    }

    public static function delete(int $id, int $familyId): void
    {
        Database::execute('DELETE FROM pets WHERE id=? AND family_id=?', [$id, $familyId]);
    }

    public static function getCareEntries(int $petId): array
    {
        return Database::fetchAll('SELECT * FROM pet_care_entries WHERE pet_id=? ORDER BY done_at DESC', [$petId]);
    }

    public static function getCareEntryById(int $id): ?array
    {
        return Database::fetch('SELECT * FROM pet_care_entries WHERE id=?', [$id]);
    }

    public static function addCareEntry(int $petId, int $userId, array $d): int
    {
        return Database::insert(
            'INSERT INTO pet_care_entries (pet_id, entry_type, title, done_at, reminder_date, notes, created_by) VALUES (?,?,?,?,?,?,?)',
            [$petId, $d['entry_type'], $d['title'], $d['done_at'], $d['reminder_date'], $d['notes'], $userId]
        );
    }

    public static function deleteCareEntry(int $id, int $petId): void
    {
        Database::execute('DELETE FROM pet_care_entries WHERE id=? AND pet_id=?', [$id, $petId]);
    }
}
