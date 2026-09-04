<?php
namespace App\Models;

use App\Core\Database;

/** Registre central "Enfants de la famille" : une fiche d'identité par enfant (nom, couleur,
 *  date de naissance), créée une seule fois puis réutilisée par les modules qui ont besoin de
 *  lier une donnée à un enfant (Suivi scolaire, Suivi nounou, Garde alternée, Bébé) — chaque
 *  module garde ses propres données spécifiques et ne fait que s'y rattacher via un
 *  family_child_id optionnel. Voir database/add_shared_children_registry.sql. */
class FamilyChild
{
    public static function getByFamily(int $familyId): array
    {
        return Database::fetchAll('SELECT * FROM family_children WHERE family_id=? ORDER BY name', [$familyId]);
    }

    public static function getById(int $id): ?array
    {
        return Database::fetch('SELECT * FROM family_children WHERE id=?', [$id]);
    }

    public static function create(int $familyId, int $createdBy, array $d): int
    {
        return Database::insert(
            'INSERT INTO family_children (family_id, name, color, birth_date, created_by) VALUES (?,?,?,?,?)',
            [$familyId, $d['name'], $d['color'], $d['birth_date'] ?: null, $createdBy]
        );
    }

    public static function update(int $id, int $familyId, array $d): void
    {
        Database::execute(
            'UPDATE family_children SET name=?, color=?, birth_date=? WHERE id=? AND family_id=?',
            [$d['name'], $d['color'], $d['birth_date'] ?: null, $id, $familyId]
        );
    }

    public static function delete(int $id, int $familyId): void
    {
        Database::execute('DELETE FROM family_children WHERE id=? AND family_id=?', [$id, $familyId]);
    }

    /** Réutilisée si un enfant du même nom (insensible à la casse) existe déjà dans la famille,
     *  sinon en crée un nouveau — filet de sécurité pour les flux de création "à la volée" des
     *  modules (ex. saisie libre d'un nom d'enfant non encore dans le registre). */
    public static function findOrCreateByName(int $familyId, int $createdBy, string $name, string $color = '#4A90D9'): int
    {
        $existing = Database::fetch(
            'SELECT id FROM family_children WHERE family_id=? AND LOWER(TRIM(name))=LOWER(TRIM(?))',
            [$familyId, $name]
        );
        if ($existing) return (int)$existing['id'];
        return self::create($familyId, $createdBy, ['name' => trim($name), 'color' => $color, 'birth_date' => null]);
    }
}
