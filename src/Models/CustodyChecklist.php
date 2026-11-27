<?php
namespace App\Models;

use App\Core\Database;

/**
 * Checklist "à ne pas oublier" réutilisée à chaque transfert de garde (cartable, doudou,
 * médicaments...). Les coches sont datées : un nouveau jour de transfert repart d'une checklist
 * vierge sans job de reset dédié — voir add_custody_checklist.sql.
 */
class CustodyChecklist
{
    /** Modèles d'items pour une famille (globaux + spécifiques à un planning). */
    public static function getItemsForFamily(int $familyId): array
    {
        return Database::fetchAll(
            'SELECT * FROM custody_checklist_items WHERE family_id=? ORDER BY sort_order, id',
            [$familyId]
        );
    }

    /** Items applicables à ce planning (globaux + propres au planning), avec leur état coché
     *  pour la date donnée. */
    public static function getForSchedule(int $scheduleId, int $familyId, string $date): array
    {
        $items = Database::fetchAll(
            'SELECT * FROM custody_checklist_items
             WHERE family_id=? AND (schedule_id IS NULL OR schedule_id=?)
             ORDER BY sort_order, id',
            [$familyId, $scheduleId]
        );
        if (!$items) return [];

        $checks = Database::fetchAll(
            'SELECT item_id FROM custody_checklist_checks WHERE check_date=? AND item_id IN (' .
            implode(',', array_fill(0, count($items), '?')) . ')',
            [$date, ...array_column($items, 'id')]
        );
        $checkedIds = array_flip(array_column($checks, 'item_id'));

        foreach ($items as &$item) {
            $item['checked'] = isset($checkedIds[$item['id']]);
        }
        return $items;
    }

    public static function create(int $familyId, ?int $scheduleId, string $label, int $userId): int
    {
        return Database::insert(
            'INSERT INTO custody_checklist_items (family_id, schedule_id, label, created_by) VALUES (?,?,?,?)',
            [$familyId, $scheduleId, $label, $userId]
        );
    }

    public static function itemBelongsToFamily(int $itemId, int $familyId): bool
    {
        return (bool)Database::fetch('SELECT id FROM custody_checklist_items WHERE id=? AND family_id=?', [$itemId, $familyId]);
    }

    public static function delete(int $id, int $familyId): void
    {
        Database::execute('DELETE FROM custody_checklist_items WHERE id=? AND family_id=?', [$id, $familyId]);
    }

    public static function setChecked(int $itemId, string $date, bool $checked, int $userId): void
    {
        if ($checked) {
            Database::execute(
                'INSERT IGNORE INTO custody_checklist_checks (item_id, check_date, checked_by) VALUES (?,?,?)',
                [$itemId, $date, $userId]
            );
        } else {
            Database::execute('DELETE FROM custody_checklist_checks WHERE item_id=? AND check_date=?', [$itemId, $date]);
        }
    }
}
