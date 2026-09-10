<?php
namespace App\Models;

use App\Core\Database;

class AdminProcedure
{
    public const TYPES = [
        'cni'          => ['label' => "Carte d'identité", 'icon' => '🪪'],
        'passeport'    => ['label' => 'Passeport',        'icon' => '📘'],
        'permis'       => ['label' => 'Permis de conduire', 'icon' => '🚙'],
        'carte_vitale' => ['label' => 'Carte vitale',     'icon' => '💳'],
        'caf'          => ['label' => 'CAF',              'icon' => '👪'],
        'mutuelle'     => ['label' => 'Mutuelle',         'icon' => '🏥'],
        'autre'        => ['label' => 'Autre',            'icon' => '📄'],
    ];

    public static function getByFamily(int $familyId): array
    {
        return Database::fetchAll('SELECT * FROM admin_procedures WHERE family_id=? ORDER BY done, deadline_date', [$familyId]);
    }

    public static function getById(int $id): ?array
    {
        return Database::fetch('SELECT * FROM admin_procedures WHERE id=?', [$id]);
    }

    public static function create(int $familyId, int $userId, array $d): int
    {
        return Database::insert(
            'INSERT INTO admin_procedures (family_id, subject_type, subject_id, procedure_type, title, deadline_date, notes, created_by) VALUES (?,?,?,?,?,?,?,?)',
            [$familyId, $d['subject_type'], $d['subject_id'], $d['procedure_type'], $d['title'], $d['deadline_date'], $d['notes'], $userId]
        );
    }

    public static function toggleDone(int $id, int $familyId, bool $done): void
    {
        Database::execute('UPDATE admin_procedures SET done=? WHERE id=? AND family_id=?', [$done ? 1 : 0, $id, $familyId]);
    }

    public static function delete(int $id, int $familyId): void
    {
        Database::execute('DELETE FROM admin_procedures WHERE id=? AND family_id=?', [$id, $familyId]);
    }
}
