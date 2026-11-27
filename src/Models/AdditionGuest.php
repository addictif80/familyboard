<?php
namespace App\Models;

use App\Core\Database;

/**
 * Identité stable d'une personne invitée sans compte FamilyBoard, à travers toutes les
 * additions où elle est conviée (un seul lien magique → tout son "espace additions").
 */
class AdditionGuest
{
    public static function findOrCreate(string $email, string $name): array
    {
        $email = strtolower(trim($email));
        $existing = Database::fetch('SELECT * FROM addition_guests WHERE email=?', [$email]);
        if ($existing) return $existing;

        $token = bin2hex(random_bytes(32));
        $id = Database::insert(
            'INSERT INTO addition_guests (email, name, access_token) VALUES (?,?,?)',
            [$email, $name ?: null, $token]
        );
        return Database::fetch('SELECT * FROM addition_guests WHERE id=?', [$id]);
    }

    public static function findByToken(string $token): ?array
    {
        return Database::fetch('SELECT * FROM addition_guests WHERE access_token=?', [$token]);
    }

    public static function findByEmail(string $email): ?array
    {
        return Database::fetch('SELECT * FROM addition_guests WHERE email=?', [strtolower(trim($email))]);
    }

    /**
     * Appelé à la création d'un compte FamilyBoard : si cette adresse e-mail correspond à un
     * invité externe existant, migre ses participations vers le nouveau compte — la personne
     * retrouve alors les mêmes additions directement dans son compte, plus besoin du lien.
     */
    public static function linkToUser(string $email, int $userId): void
    {
        $guest = self::findByEmail($email);
        if (!$guest) return;
        Database::execute('UPDATE addition_guests SET linked_user_id=? WHERE id=?', [$userId, $guest['id']]);
        Database::execute(
            'UPDATE addition_participants SET user_id=?, guest_id=NULL WHERE guest_id=?',
            [$userId, $guest['id']]
        );
    }
}
