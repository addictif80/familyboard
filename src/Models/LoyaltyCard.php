<?php
namespace App\Models;

use App\Core\Database;

class LoyaltyCard
{
    public const FORMATS = [
        'ean13'    => 'EAN-13 (code-barres classique)',
        'code128'  => 'Code 128 (code-barres long)',
        'qrcode'   => 'QR code',
    ];

    public static function getByFamily(int $familyId): array
    {
        return Database::fetchAll('SELECT * FROM loyalty_cards WHERE family_id=? ORDER BY store_name', [$familyId]);
    }

    public static function getById(int $id): ?array
    {
        return Database::fetch('SELECT * FROM loyalty_cards WHERE id=?', [$id]);
    }

    public static function create(int $familyId, int $userId, array $d): int
    {
        return Database::insert(
            'INSERT INTO loyalty_cards (family_id, store_name, card_color, barcode_value, barcode_format, notes, created_by) VALUES (?,?,?,?,?,?,?)',
            [$familyId, $d['store_name'], $d['card_color'], $d['barcode_value'], $d['barcode_format'], $d['notes'], $userId]
        );
    }

    public static function update(int $id, int $familyId, array $d): void
    {
        Database::execute(
            'UPDATE loyalty_cards SET store_name=?, card_color=?, barcode_value=?, barcode_format=?, notes=? WHERE id=? AND family_id=?',
            [$d['store_name'], $d['card_color'], $d['barcode_value'], $d['barcode_format'], $d['notes'], $id, $familyId]
        );
    }

    public static function delete(int $id, int $familyId): void
    {
        Database::execute('DELETE FROM loyalty_cards WHERE id=? AND family_id=?', [$id, $familyId]);
    }
}
