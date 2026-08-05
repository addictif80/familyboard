<?php
namespace App\Models;

use App\Core\Database;

/**
 * Addition : partage de frais entre plusieurs personnes (restaurant, cadeau commun,
 * activité...), répartition en pourcentage ou montant libre. Module indépendant, sans lien
 * avec le calendrier. Les cagnottes (is_cagnotte=1) sont des additions particulières : un
 * objectif et un registre de perceptions manuelles remplacent la répartition d'une dépense
 * déjà engagée — voir AdditionPayment.
 */
class Addition
{
    public static function getByFamily(int $familyId): array
    {
        $rows = Database::fetchAll(
            "SELECT a.*, COALESCE(u.name, 'Compte supprimé') as creator_name
             FROM additions a LEFT JOIN users u ON u.id = a.created_by
             WHERE a.family_id=? ORDER BY a.created_at DESC",
            [$familyId]
        );
        foreach ($rows as &$row) $row = self::decorate($row);
        unset($row);
        return $rows;
    }

    public static function getById(int $id): ?array
    {
        $row = Database::fetch(
            "SELECT a.*, COALESCE(u.name, 'Compte supprimé') as creator_name
             FROM additions a LEFT JOIN users u ON u.id = a.created_by
             WHERE a.id=?",
            [$id]
        );
        return $row ? self::decorate($row) : null;
    }

    public static function create(int $familyId, int $createdBy, array $data): int
    {
        $isCagnotte = !empty($data['is_cagnotte']);
        return Database::insert(
            'INSERT INTO additions (family_id, created_by, title, description, total_amount, split_mode, is_cagnotte, cagnotte_goal, cagnotte_methods, cagnotte_iban, cagnotte_wero_phone)
             VALUES (?,?,?,?,?,?,?,?,?,?,?)',
            [
                $familyId, $createdBy,
                trim($data['title']),
                trim($data['description'] ?? '') ?: null,
                $isCagnotte ? 0 : (float)($data['total_amount'] ?? 0),
                in_array($data['split_mode'] ?? '', ['percentage', 'amount'], true) ? $data['split_mode'] : 'percentage',
                $isCagnotte ? 1 : 0,
                $isCagnotte ? (($data['cagnotte_goal'] ?? '') !== '' ? (float)$data['cagnotte_goal'] : null) : null,
                $isCagnotte ? self::encodeMethods($data['cagnotte_methods'] ?? []) : null,
                $isCagnotte ? (trim($data['cagnotte_iban'] ?? '') ?: null) : null,
                $isCagnotte ? (trim($data['cagnotte_wero_phone'] ?? '') ?: null) : null,
            ]
        );
    }

    public static function delete(int $id, int $familyId): void
    {
        Database::execute('DELETE FROM additions WHERE id=? AND family_id=?', [$id, $familyId]);
    }

    private static function encodeMethods(array $methods): ?string
    {
        $allowed = array_values(array_intersect($methods, ['cash', 'transfer', 'wero']));
        return $allowed ? implode(',', $allowed) : null;
    }

    private static function decorate(array $row): array
    {
        $row['cagnotte_methods_list'] = $row['cagnotte_methods'] ? explode(',', $row['cagnotte_methods']) : [];
        $participants = AdditionParticipant::getByAddition((int)$row['id']);
        $row['participants'] = $participants;
        $row['participants_accepted_count'] = count(array_filter($participants, fn($p) => $p['status'] === 'accepted'));

        if ($row['is_cagnotte']) {
            $row['payments'] = AdditionPayment::getByAddition((int)$row['id']);
            $row['collected_amount'] = array_sum(array_column($row['payments'], 'amount'));
        }

        return $row;
    }
}
