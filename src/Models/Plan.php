<?php
namespace App\Models;

use App\Core\Database;

class Plan
{
    public static function getAll(bool $activeOnly = false): array
    {
        $sql = 'SELECT * FROM plans' . ($activeOnly ? ' WHERE active=1' : '') . ' ORDER BY sort_order, id';
        return Database::fetchAll($sql);
    }

    public static function getById(int $id): ?array
    {
        return Database::fetch('SELECT * FROM plans WHERE id=?', [$id]);
    }

    public static function getByStripePriceId(string $priceId): ?array
    {
        return Database::fetch('SELECT * FROM plans WHERE stripe_price_id_monthly=? OR stripe_price_id_yearly=?', [$priceId, $priceId]);
    }

    public static function create(array $d): int
    {
        return Database::insert(
            'INSERT INTO plans (code, name, member_limit, price_monthly_cents, price_yearly_cents, stripe_product_id, stripe_price_id_monthly, stripe_price_id_yearly, sort_order, active)
             VALUES (?,?,?,?,?,?,?,?,?,?)',
            [$d['code'], $d['name'], $d['member_limit'], $d['price_monthly_cents'], $d['price_yearly_cents'],
             $d['stripe_product_id'], $d['stripe_price_id_monthly'], $d['stripe_price_id_yearly'], $d['sort_order'], $d['active'] ? 1 : 0]
        );
    }

    public static function update(int $id, array $d): void
    {
        Database::execute(
            'UPDATE plans SET name=?, member_limit=?, price_monthly_cents=?, price_yearly_cents=?, stripe_product_id=?, stripe_price_id_monthly=?, stripe_price_id_yearly=?, sort_order=?, active=? WHERE id=?',
            [$d['name'], $d['member_limit'], $d['price_monthly_cents'], $d['price_yearly_cents'],
             $d['stripe_product_id'], $d['stripe_price_id_monthly'], $d['stripe_price_id_yearly'], $d['sort_order'], $d['active'] ? 1 : 0, $id]
        );
    }

    /** Jamais de suppression physique : des family_subscriptions peuvent encore y pointer
     *  (FK ON DELETE SET NULL) et on veut garder l'historique de facturation lisible — on
     *  désactive simplement le palier pour qu'il disparaisse des nouvelles souscriptions. */
    public static function deactivate(int $id): void
    {
        Database::execute('UPDATE plans SET active=0 WHERE id=?', [$id]);
    }
}
