<?php
namespace App\Core;

use App\Models\AppSetting;

/** Fine couche autour du SDK stripe-php : construit un client avec la clé secrète configurée
 *  depuis le panneau admin système (jamais en dur dans le code / les variables d'environnement),
 *  voir AdminController::updateStripeSettings. */
class StripeGateway
{
    public static function isConfigured(): bool
    {
        return (bool)AppSetting::get('stripe_secret_key');
    }

    public static function client(): ?\Stripe\StripeClient
    {
        $key = AppSetting::get('stripe_secret_key');
        if (!$key) return null;
        return new \Stripe\StripeClient($key);
    }

    public static function publishableKey(): string
    {
        return AppSetting::get('stripe_publishable_key') ?? '';
    }

    public static function webhookSecret(): string
    {
        return AppSetting::get('stripe_webhook_secret') ?? '';
    }

    /** Crée le Produit Stripe d'un palier s'il n'en a pas encore, ou renomme celui existant
     *  si le nom du palier a changé. */
    private static function syncProduct(string $name, ?string $productId): string
    {
        $client = self::client();
        if (!$client) {
            throw new \RuntimeException("Stripe n'est pas configuré (clé secrète manquante).");
        }
        try {
            if ($productId) {
                $client->products->update($productId, ['name' => $name]);
                return $productId;
            }
            $product = $client->products->create(['name' => $name]);
            return $product->id;
        } catch (\Stripe\Exception\ApiErrorException $e) {
            throw new \RuntimeException($e->getMessage());
        }
    }

    /** Crée un nouveau Prix récurrent si besoin (pas de Prix existant, ou tarif modifié depuis
     *  le dernier enregistrement — un Prix Stripe est immuable) et archive l'ancien. Retourne
     *  null si le palier n'a pas de tarif pour cette fréquence (ex : palier gratuit). */
    private static function syncRecurringPrice(string $productId, int $amountCents, string $interval, ?string $priceId, ?int $previousAmountCents): ?string
    {
        if ($amountCents <= 0) {
            return null;
        }
        $client = self::client();
        $needsNewPrice = !$priceId || $previousAmountCents === null || $previousAmountCents !== $amountCents;
        if (!$needsNewPrice) {
            return $priceId;
        }
        try {
            if ($priceId) {
                $client->prices->update($priceId, ['active' => false]);
            }
            $price = $client->prices->create([
                'product' => $productId,
                'currency' => 'eur',
                'unit_amount' => $amountCents,
                'recurring' => ['interval' => $interval],
            ]);
            return $price->id;
        } catch (\Stripe\Exception\ApiErrorException $e) {
            throw new \RuntimeException($e->getMessage());
        }
    }

    /** Crée/met à jour automatiquement le Produit et les Prix Stripe (mensuel + annuel) d'un
     *  palier d'abonnement à chaque enregistrement depuis le panneau admin : l'admin n'a plus
     *  à gérer quoi que ce soit côté Stripe lui-même.
     *
     * @return array{stripe_product_id: string, stripe_price_id_monthly: ?string, stripe_price_id_yearly: ?string}
     */
    public static function syncPlanPrices(
        string $name,
        int $monthlyCents,
        int $yearlyCents,
        ?string $productId,
        ?string $priceIdMonthly,
        ?string $priceIdYearly,
        ?int $previousMonthlyCents,
        ?int $previousYearlyCents
    ): array {
        $productId = self::syncProduct($name, $productId);
        return [
            'stripe_product_id' => $productId,
            'stripe_price_id_monthly' => self::syncRecurringPrice($productId, $monthlyCents, 'month', $priceIdMonthly, $previousMonthlyCents),
            'stripe_price_id_yearly' => self::syncRecurringPrice($productId, $yearlyCents, 'year', $priceIdYearly, $previousYearlyCents),
        ];
    }
}
