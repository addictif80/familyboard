<?php
namespace App\Models;

use App\Core\Database;

/**
 * Abonnements de stockage additionnel d'un bénéficiaire — un abonnement = un palier
 * (storage_product) souscrit par un payeur. Plusieurs abonnements actifs d'un même
 * bénéficiaire s'additionnent (ex. +100 Go puis +250 Go = 350 Go de plus), comme
 * ProvisioningService::totalQuotaForGroup côté ABPanel.
 */
class MemberStorageSubscription
{
    /** Statuts comptant dans le quota poussé à Synology (actif ou en grâce : l'accès reste tant
     *  que le délai de grâce n'est pas écoulé — seul 'canceled' retire le volume). */
    private const COUNTS_TOWARD_QUOTA = ['active', 'past_due', 'grace'];

    public static function getForBeneficiary(int $beneficiaryId): array
    {
        return Database::fetchAll(
            'SELECT mss.*, sp.quota_gb, sp.name AS product_name, sp.code AS product_code
             FROM member_storage_subscriptions mss
             JOIN storage_products sp ON sp.id = mss.storage_product_id
             WHERE mss.beneficiary_id = ?
             ORDER BY mss.created_at',
            [$beneficiaryId]
        );
    }

    public static function getForPayer(int $payerId): array
    {
        return Database::fetchAll(
            'SELECT mss.*, sp.quota_gb, sp.name AS product_name, u.name AS beneficiary_name
             FROM member_storage_subscriptions mss
             JOIN storage_products sp ON sp.id = mss.storage_product_id
             JOIN users u ON u.id = mss.beneficiary_id
             WHERE mss.payer_id = ?
             ORDER BY mss.created_at',
            [$payerId]
        );
    }

    /**
     * Quota additionnel total (Go) actuellement dû à un bénéficiaire, toutes souscriptions
     * actives/en grâce confondues — c'est ce volume qui est poussé à Synology, jamais la
     * somme brute incluant les abonnements déjà résiliés.
     */
    public static function totalAddonGbForBeneficiary(int $beneficiaryId): int
    {
        $rows = self::getForBeneficiary($beneficiaryId);
        $total = 0;
        foreach ($rows as $row) {
            if (in_array($row['status'], self::COUNTS_TOWARD_QUOTA, true)) {
                $total += (int)$row['quota_gb'];
            }
        }
        return $total;
    }

    public static function create(int $beneficiaryId, int $payerId, int $storageProductId, ?string $stripeSubscriptionId = null): int
    {
        $id = Database::insert(
            'INSERT INTO member_storage_subscriptions (beneficiary_id, payer_id, storage_product_id, stripe_subscription_id, status) VALUES (?,?,?,?,\'active\')',
            [$beneficiaryId, $payerId, $storageProductId, $stripeSubscriptionId]
        );
        self::enqueueQuotaSync($beneficiaryId);
        return $id;
    }

    public static function updateStatus(int $id, string $status, ?string $graceEndsAt = null): void
    {
        Database::execute(
            'UPDATE member_storage_subscriptions SET status=?, grace_ends_at=? WHERE id=?',
            [$status, $graceEndsAt, $id]
        );
        self::enqueueQuotaSyncForSubscription($id);
    }

    public static function cancel(int $id): void
    {
        Database::execute(
            "UPDATE member_storage_subscriptions SET status='canceled', canceled_at=NOW() WHERE id=?",
            [$id]
        );
        self::enqueueQuotaSyncForSubscription($id);
    }

    /** Le quota perso dû à un membre dépend de la somme de ses abonnements actifs/en grâce
     *  (voir totalAddonGbForBeneficiary) : toute transition de statut (nouvel abonnement,
     *  résiliation, passage en grâce...) doit repousser le quota vers le backend de stockage. */
    private static function enqueueQuotaSync(int $beneficiaryId): void
    {
        if (empty(\App\Core\StorageProvisionerFactory::activeQuotaBackends())) return;
        \App\Models\ProvisioningJob::enqueue($beneficiaryId, 'sync_quota');
    }

    private static function enqueueQuotaSyncForSubscription(int $subscriptionId): void
    {
        $row = Database::fetch('SELECT beneficiary_id FROM member_storage_subscriptions WHERE id=?', [$subscriptionId]);
        if ($row) self::enqueueQuotaSync((int)$row['beneficiary_id']);
    }

    /** Toutes les souscriptions dont un payeur donné règle la facture (pour l'écran « Mon compte → Stockage »). */
    public static function findByStripeSubscriptionId(string $stripeSubscriptionId): ?array
    {
        return Database::fetch('SELECT * FROM member_storage_subscriptions WHERE stripe_subscription_id = ?', [$stripeSubscriptionId]);
    }
}
