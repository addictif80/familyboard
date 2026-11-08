<?php
namespace App\Models;

use App\Core\Database;

/** Stock du frigo et du congélateur : quantités et dates de péremption. */
class StockItem
{
    public const LOCATIONS = [
        'fridge'  => ['label' => 'Frigo',        'icon' => '🧊'],
        'freezer' => ['label' => 'Congélateur',  'icon' => '🥶'],
    ];

    public static function getByFamily(int $familyId, string $location = '', string $search = ''): array
    {
        $sql = 'SELECT s.*, u.name as user_name FROM stock_items s JOIN users u ON u.id = s.user_id WHERE s.family_id = ?';
        $params = [$familyId];
        if ($location !== '' && isset(self::LOCATIONS[$location])) {
            $sql .= ' AND s.location = ?';
            $params[] = $location;
        }
        if ($search !== '') {
            $sql .= ' AND (s.name LIKE ? OR s.category LIKE ?)';
            $like = '%' . $search . '%';
            $params[] = $like;
            $params[] = $like;
        }
        $sql .= ' ORDER BY s.expiry_date IS NULL, s.expiry_date ASC, s.name ASC';
        return array_map([self::class, 'decorate'], Database::fetchAll($sql, $params));
    }

    public static function findById(int $id, int $familyId): ?array
    {
        $row = Database::fetch('SELECT * FROM stock_items WHERE id=? AND family_id=?', [$id, $familyId]);
        return $row ? self::decorate($row) : null;
    }

    public static function getExpiringSoon(int $familyId, int $days = 3): array
    {
        $rows = Database::fetchAll(
            'SELECT * FROM stock_items
             WHERE family_id=? AND expiry_date IS NOT NULL
               AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY)
             ORDER BY expiry_date ASC',
            [$familyId, $days]
        );
        return array_map([self::class, 'decorate'], $rows);
    }

    public static function create(int $familyId, int $userId, array $data): int
    {
        return Database::insert(
            'INSERT INTO stock_items (family_id, user_id, name, location, category, quantity, unit, expiry_date, notes)
             VALUES (?,?,?,?,?,?,?,?,?)',
            [
                $familyId, $userId,
                trim($data['name']),
                in_array($data['location'] ?? '', ['fridge', 'freezer'], true) ? $data['location'] : 'fridge',
                trim((string)($data['category'] ?? '')) ?: null,
                max(0, (int)($data['quantity'] ?? 1)),
                trim((string)($data['unit'] ?? '')) ?: null,
                $data['expiry_date'] ?: null,
                trim((string)($data['notes'] ?? '')) ?: null,
            ]
        );
    }

    public static function update(int $id, int $familyId, array $data): void
    {
        Database::execute(
            'UPDATE stock_items SET name=?, location=?, category=?, quantity=?, unit=?, expiry_date=?, notes=?
             WHERE id=? AND family_id=?',
            [
                trim($data['name']),
                in_array($data['location'] ?? '', ['fridge', 'freezer'], true) ? $data['location'] : 'fridge',
                trim((string)($data['category'] ?? '')) ?: null,
                max(0, (int)($data['quantity'] ?? 1)),
                trim((string)($data['unit'] ?? '')) ?: null,
                $data['expiry_date'] ?: null,
                trim((string)($data['notes'] ?? '')) ?: null,
                $id, $familyId,
            ]
        );
    }

    /** +1/-1 depuis la liste (jamais sous zéro) — pour ajuster la quantité sans ouvrir le
     *  formulaire complet à chaque fois qu'on sort un produit du frigo. */
    public static function adjustQuantity(int $id, int $familyId, int $delta): ?int
    {
        $item = Database::fetch('SELECT quantity FROM stock_items WHERE id=? AND family_id=?', [$id, $familyId]);
        if (!$item) return null;
        $newQty = max(0, (int)$item['quantity'] + $delta);
        Database::execute('UPDATE stock_items SET quantity=? WHERE id=? AND family_id=?', [$newQty, $id, $familyId]);
        return $newQty;
    }

    public static function delete(int $id, int $familyId): void
    {
        Database::execute('DELETE FROM stock_items WHERE id=? AND family_id=?', [$id, $familyId]);
    }

    private static function decorate(array $row): array
    {
        $row['days_remaining'] = null;
        $row['status'] = 'none';
        if (!empty($row['expiry_date'])) {
            $today = new \DateTime('today');
            $end = new \DateTime($row['expiry_date']);
            $diff = (int)$today->diff($end)->days * ($end >= $today ? 1 : -1);
            $row['days_remaining'] = $diff;
            if ($diff < 0)       $row['status'] = 'expired';
            elseif ($diff <= 3)  $row['status'] = 'critical';
            elseif ($diff <= 7)  $row['status'] = 'soon';
            else                 $row['status'] = 'ok';
        }
        return $row;
    }
}
