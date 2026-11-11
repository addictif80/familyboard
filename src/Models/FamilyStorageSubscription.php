<?php
namespace App\Models;

use App\Core\Database;

/**
 * Abonnements de stockage additionnel FAMILIAL — mêmes principes que MemberStorageSubscription
 * (statuts, cumul des volumes actifs/en grâce), mais rattachés à une famille plutôt qu'à un
 * bénéficiaire individuel. Le payeur est toujours un utilisateur (users.stripe_customer_id,
 * typiquement l'admin de la famille).
 */
class FamilyStorageSubscription
{
    private const COUNTS_TOWARD_QUOTA = ['active', 'past_due', 'grace'];

    public static function getForFamily(int $familyId): array
    {
        return Database::fetchAll(
            'SELECT fss.*, sp.quota_gb, sp.name AS product_name, sp.code AS product_code
             FROM family_storage_subscriptions fss
             JOIN storage_products sp ON sp.id = fss.storage_product_id
             WHERE fss.family_id = ?
             ORDER BY fss.created_at',
            [$familyId]
        );
    }

    /** Quota additionnel familial total (Go) actuellement dû, abonnements actifs/en grâce
     *  uniquement — poussé en plus de storage_family_quota_gb (voir StorageQuota::familyQuotaGb). */
    public static function totalAddonGbForFamily(int $familyId): int
    {
        $total = 0;
        foreach (self::getForFamily($familyId) as $row) {
            if (in_array($row['status'], self::COUNTS_TOWARD_QUOTA, true)) {
                $total += (int)$row['quota_gb'];
            }
        }
        return $total;
    }

    public static function create(int $familyId, int $payerId, int $storageProductId, ?string $stripeSubscriptionId = null): int
    {
        $id = Database::insert(
            'INSERT INTO family_storage_subscriptions (family_id, payer_id, storage_product_id, stripe_subscription_id, status) VALUES (?,?,?,?,\'active\')',
            [$familyId, $payerId, $storageProductId, $stripeSubscriptionId]
        );
        self::syncQuota($familyId);
        return $id;
    }

    public static function updateStatus(int $id, string $status, ?string $graceEndsAt = null): void
    {
        Database::execute(
            'UPDATE family_storage_subscriptions SET status=?, grace_ends_at=? WHERE id=?',
            [$status, $graceEndsAt, $id]
        );
        self::syncQuotaForSubscription($id);
    }

    public static function cancel(int $id): void
    {
        Database::execute(
            "UPDATE family_storage_subscriptions SET status='canceled', canceled_at=NOW() WHERE id=?",
            [$id]
        );
        self::syncQuotaForSubscription($id);
    }

    public static function findByStripeSubscriptionId(string $stripeSubscriptionId): ?array
    {
        return Database::fetch('SELECT * FROM family_storage_subscriptions WHERE stripe_subscription_id = ?', [$stripeSubscriptionId]);
    }

    /** Répercute le nouveau total vers le quota natif du Group Folder Nextcloud de la famille,
     *  s'il est déjà provisionné — best-effort, jamais bloquant (contrairement au quota perso,
     *  il n'existe pas de file de jobs pour le quota familial, l'appel est direct). */
    private static function syncQuota(int $familyId): void
    {
        if (!\App\Models\FamilyNextcloudFolder::findByFamily($familyId)) return;
        try {
            \App\Core\NextcloudGroupFolderService::applyFamilyQuota($familyId);
        } catch (\Throwable $e) {
            error_log('FamilyStorageSubscription: Nextcloud quota sync failed for family ' . $familyId . ': ' . $e->getMessage());
        }
    }

    private static function syncQuotaForSubscription(int $subscriptionId): void
    {
        $row = Database::fetch('SELECT family_id FROM family_storage_subscriptions WHERE id=?', [$subscriptionId]);
        if ($row) self::syncQuota((int)$row['family_id']);
    }
}
