<?php
namespace App\Models;

use App\Core\Database;

class EmergencyCard
{
    public static function getByFamily(int $familyId): array
    {
        return Database::fetchAll('SELECT * FROM emergency_cards WHERE family_id=?', [$familyId]);
    }

    public static function getBySubject(int $familyId, string $subjectType, int $subjectId): ?array
    {
        return Database::fetch(
            'SELECT * FROM emergency_cards WHERE family_id=? AND subject_type=? AND subject_id=?',
            [$familyId, $subjectType, $subjectId]
        );
    }

    public static function getByToken(string $token): ?array
    {
        return Database::fetch('SELECT * FROM emergency_cards WHERE public_token=?', [$token]);
    }

    public static function save(int $familyId, int $userId, string $subjectType, int $subjectId, array $data): array
    {
        $existing = self::getBySubject($familyId, $subjectType, $subjectId);
        $fields = [
            'blood_type'              => $data['blood_type'] ?? null,
            'allergies'               => $data['allergies'] ?? null,
            'medications'             => $data['medications'] ?? null,
            'conditions'              => $data['conditions'] ?? null,
            'doctor_name'             => $data['doctor_name'] ?? null,
            'doctor_phone'            => $data['doctor_phone'] ?? null,
            'emergency_contact_name'  => $data['emergency_contact_name'] ?? null,
            'emergency_contact_phone' => $data['emergency_contact_phone'] ?? null,
            'notes'                   => $data['notes'] ?? null,
        ];

        if ($existing) {
            Database::execute(
                'UPDATE emergency_cards SET blood_type=?, allergies=?, medications=?, conditions=?,
                 doctor_name=?, doctor_phone=?, emergency_contact_name=?, emergency_contact_phone=?, notes=?
                 WHERE id=?',
                [...array_values($fields), $existing['id']]
            );
            return self::getBySubject($familyId, $subjectType, $subjectId);
        }

        $token = bin2hex(random_bytes(24));
        Database::insert(
            'INSERT INTO emergency_cards
             (family_id, subject_type, subject_id, blood_type, allergies, medications, conditions,
              doctor_name, doctor_phone, emergency_contact_name, emergency_contact_phone, notes,
              public_token, created_by)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [$familyId, $subjectType, $subjectId, ...array_values($fields), $token, $userId]
        );
        return self::getBySubject($familyId, $subjectType, $subjectId);
    }
}
