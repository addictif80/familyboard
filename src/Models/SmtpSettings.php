<?php
namespace App\Models;

use App\Core\Database;

class SmtpSettings
{
    public static function getByFamily(int $familyId): ?array
    {
        return Database::fetch('SELECT * FROM smtp_settings WHERE family_id = ?', [$familyId]);
    }

    public static function save(int $familyId, array $data): void
    {
        $existing = self::getByFamily($familyId);
        if ($existing) {
            Database::execute(
                'UPDATE smtp_settings SET host=?, port=?, username=?, password=?, from_email=?, from_name=?, encryption=? WHERE family_id=?',
                [$data['host'], $data['port'], $data['username'], $data['password'], $data['from_email'], $data['from_name'], $data['encryption'], $familyId]
            );
        } else {
            Database::insert(
                'INSERT INTO smtp_settings (family_id, host, port, username, password, from_email, from_name, encryption) VALUES (?,?,?,?,?,?,?,?)',
                [$familyId, $data['host'], $data['port'], $data['username'], $data['password'], $data['from_email'], $data['from_name'], $data['encryption']]
            );
        }
    }
}
