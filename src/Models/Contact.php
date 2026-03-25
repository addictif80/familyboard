<?php
namespace App\Models;

use App\Core\Database;

class Contact
{
    private static array $COLORS = [
        '#4A90D9','#E74C3C','#27AE60','#F39C12','#9B59B6',
        '#1ABC9C','#E67E22','#2ECC71','#3498DB','#E91E63',
    ];

    public static function getByFamily(int $familyId): array
    {
        return Database::fetchAll(
            'SELECT * FROM contacts WHERE family_id=? ORDER BY last_name ASC, first_name ASC',
            [$familyId]
        );
    }

    public static function getById(int $id): ?array
    {
        return Database::fetch('SELECT * FROM contacts WHERE id=?', [$id]);
    }

    public static function create(int $familyId, int $userId, array $data): int
    {
        $color = $data['color'] ?? self::$COLORS[array_rand(self::$COLORS)];
        return Database::insert(
            'INSERT INTO contacts (family_id, user_id, first_name, last_name, email, phone, phone2, address, birthday, notes, color)
             VALUES (?,?,?,?,?,?,?,?,?,?,?)',
            [
                $familyId, $userId,
                $data['first_name'],
                $data['last_name']  ?? '',
                $data['email']      ?? null,
                $data['phone']      ?? null,
                $data['phone2']     ?? null,
                $data['address']    ?? null,
                $data['birthday']   ?? null,
                $data['notes']      ?? null,
                $color,
            ]
        );
    }

    public static function update(int $id, array $data): void
    {
        Database::execute(
            'UPDATE contacts SET first_name=?, last_name=?, email=?, phone=?, phone2=?, address=?, birthday=?, notes=?, color=? WHERE id=?',
            [
                $data['first_name'],
                $data['last_name']  ?? '',
                $data['email']      ?? null,
                $data['phone']      ?? null,
                $data['phone2']     ?? null,
                $data['address']    ?? null,
                $data['birthday']   ?? null,
                $data['notes']      ?? null,
                $data['color']      ?? '#4A90D9',
                $id,
            ]
        );
    }

    public static function updateAvatar(int $id, string $path): void
    {
        Database::execute('UPDATE contacts SET avatar=? WHERE id=?', [$path, $id]);
    }

    public static function delete(int $id): void
    {
        Database::execute('DELETE FROM contacts WHERE id=?', [$id]);
    }

    public static function toVcard(array $c): string
    {
        $fn  = trim($c['first_name'] . ' ' . $c['last_name']);
        $vcf = "BEGIN:VCARD\r\nVERSION:3.0\r\n";
        $vcf .= "FN:" . self::vcfEscape($fn) . "\r\n";
        $vcf .= "N:" . self::vcfEscape($c['last_name']) . ";" . self::vcfEscape($c['first_name']) . ";;;\r\n";
        if ($c['phone'])   $vcf .= "TEL;TYPE=CELL:" . self::vcfEscape($c['phone'])  . "\r\n";
        if ($c['phone2'])  $vcf .= "TEL;TYPE=WORK:" . self::vcfEscape($c['phone2']) . "\r\n";
        if ($c['email'])   $vcf .= "EMAIL:" . self::vcfEscape($c['email'])   . "\r\n";
        if ($c['address']) $vcf .= "ADR;TYPE=HOME:;;" . self::vcfEscape(str_replace(["\r\n","\n"], ' ', $c['address'])) . ";;;;\r\n";
        if ($c['birthday'])$vcf .= "BDAY:" . str_replace('-', '', $c['birthday']) . "\r\n";
        if ($c['notes'])   $vcf .= "NOTE:" . self::vcfEscape($c['notes']) . "\r\n";
        $vcf .= "END:VCARD\r\n";
        return $vcf;
    }

    private static function vcfEscape(string $s): string
    {
        return str_replace([',', ';', "\n", "\r"], ['\\,', '\\;', '\\n', ''], $s);
    }
}
