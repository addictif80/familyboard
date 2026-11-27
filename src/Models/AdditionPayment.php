<?php
namespace App\Models;

use App\Core\Database;

/**
 * Registre de perceptions d'une cagnotte — saisi manuellement par un admin famille, aucun
 * processeur de paiement : simple traçabilité de qui a payé, combien, quand et par quel moyen.
 */
class AdditionPayment
{
    public const METHOD_LABELS = [
        'cash'     => 'Espèces',
        'transfer' => 'Virement',
        'wero'     => 'Wero',
    ];

    public static function getByAddition(int $additionId): array
    {
        return Database::fetchAll(
            'SELECT * FROM addition_payments WHERE addition_id=? ORDER BY paid_at DESC, id DESC',
            [$additionId]
        );
    }

    public static function getTotalCollected(int $additionId): float
    {
        $row = Database::fetch('SELECT SUM(amount) as total FROM addition_payments WHERE addition_id=?', [$additionId]);
        return (float)($row['total'] ?? 0);
    }

    public static function record(int $additionId, ?int $participantId, string $payerName, float $amount, string $method, ?string $note, int $recordedBy, string $paidAt): int
    {
        return Database::insert(
            'INSERT INTO addition_payments (addition_id, participant_id, payer_name, amount, method, note, recorded_by, paid_at) VALUES (?,?,?,?,?,?,?,?)',
            [$additionId, $participantId, trim($payerName), $amount, $method, trim($note ?? '') ?: null, $recordedBy, $paidAt]
        );
    }

    public static function delete(int $id, int $additionId): void
    {
        Database::execute('DELETE FROM addition_payments WHERE id=? AND addition_id=?', [$id, $additionId]);
    }
}
