<?php
namespace App\Models;

use App\Core\Database;

/**
 * Palier de stockage additionnel payant (code, volume, prix) — géré depuis le panneau admin
 * système, synchronisé vers Stripe (un Produit par palier) comme les paliers d'abonnement
 * familial (voir App\Models\Plan). Jamais visible côté membre tant que storage_billing_enabled=0.
 */
class StorageProduct
{
    public static function getAll(bool $activeOnly = false): array
    {
        $sql = 'SELECT * FROM storage_products';
        if ($activeOnly) $sql .= ' WHERE active = 1';
        $sql .= ' ORDER BY sort_order, id';
        return Database::fetchAll($sql);
    }

    public static function findById(int $id): ?array
    {
        return Database::fetch('SELECT * FROM storage_products WHERE id = ?', [$id]);
    }

    public static function save(array $data): int
    {
        $id = (int)($data['id'] ?? 0);
        $scope = in_array($data['scope'] ?? 'member', ['member', 'family'], true) ? $data['scope'] : 'member';
        if ($id > 0) {
            Database::execute(
                'UPDATE storage_products SET code=?, name=?, quota_gb=?, scope=?, price_monthly_cents=?, sort_order=?, active=? WHERE id=?',
                [$data['code'], $data['name'], (int)$data['quota_gb'], $scope, (int)$data['price_monthly_cents'], (int)($data['sort_order'] ?? 0), (int)($data['active'] ?? 1), $id]
            );
            return $id;
        }
        return Database::insert(
            'INSERT INTO storage_products (code, name, quota_gb, scope, price_monthly_cents, sort_order, active) VALUES (?,?,?,?,?,?,?)',
            [$data['code'], $data['name'], (int)$data['quota_gb'], $scope, (int)$data['price_monthly_cents'], (int)($data['sort_order'] ?? 0), (int)($data['active'] ?? 1)]
        );
    }

    public static function setStripeIds(int $id, ?string $productId, ?string $priceId): void
    {
        Database::execute(
            'UPDATE storage_products SET stripe_product_id=?, stripe_price_id_monthly=? WHERE id=?',
            [$productId, $priceId, $id]
        );
    }
}
