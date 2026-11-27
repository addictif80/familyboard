<?php
namespace App\Models;

use App\Core\Database;

/**
 * Demande d'achat de stockage additionnel pour un bénéficiaire mineur (ou dont la date de
 * naissance est absente — traité comme mineur, voir App\Core\Majority), en attente de
 * validation + paiement par un adulte de la famille. Un bénéficiaire majeur confirmé n'a pas
 * besoin de cette étape : il paie directement (Checkout Stripe, Phase 2).
 */
class StorageRequest
{
    public static function findById(int $id): ?array
    {
        return Database::fetch('SELECT * FROM storage_requests WHERE id = ?', [$id]);
    }

    public static function create(int $beneficiaryId, int $storageProductId): int
    {
        return Database::insert(
            'INSERT INTO storage_requests (beneficiary_id, storage_product_id, status) VALUES (?,?,\'pending\')',
            [$beneficiaryId, $storageProductId]
        );
    }

    /** Demandes en attente pour les membres d'une famille (à afficher aux adultes). */
    public static function getPendingForFamily(int $familyId): array
    {
        return Database::fetchAll(
            'SELECT sr.*, u.name AS beneficiary_name, sp.name AS product_name, sp.quota_gb, sp.price_monthly_cents
             FROM storage_requests sr
             JOIN users u ON u.id = sr.beneficiary_id
             JOIN storage_products sp ON sp.id = sr.storage_product_id
             WHERE sr.status = \'pending\' AND u.family_id = ?
             ORDER BY sr.created_at',
            [$familyId]
        );
    }

    public static function getForBeneficiary(int $beneficiaryId): array
    {
        return Database::fetchAll(
            'SELECT sr.*, sp.name AS product_name, sp.quota_gb
             FROM storage_requests sr
             JOIN storage_products sp ON sp.id = sr.storage_product_id
             WHERE sr.beneficiary_id = ?
             ORDER BY sr.created_at DESC',
            [$beneficiaryId]
        );
    }

    public static function markApproved(int $id, int $payerId, int $decidedBy): void
    {
        Database::execute(
            "UPDATE storage_requests SET status='approved', payer_id=?, decided_by=?, decided_at=NOW() WHERE id=?",
            [$payerId, $decidedBy, $id]
        );
    }

    public static function markRejected(int $id, int $decidedBy): void
    {
        Database::execute(
            "UPDATE storage_requests SET status='rejected', decided_by=?, decided_at=NOW() WHERE id=?",
            [$decidedBy, $id]
        );
    }

    public static function markCanceled(int $id): void
    {
        Database::execute("UPDATE storage_requests SET status='canceled' WHERE id=?", [$id]);
    }

    /** Limite anti-abus : nombre de demandes créées par un bénéficiaire sur les dernières 24h. */
    public static function countRecentForBeneficiary(int $beneficiaryId, int $hours = 24): int
    {
        $row = Database::fetch(
            'SELECT COUNT(*) AS c FROM storage_requests WHERE beneficiary_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)',
            [$beneficiaryId, $hours]
        );
        return (int)($row['c'] ?? 0);
    }
}
