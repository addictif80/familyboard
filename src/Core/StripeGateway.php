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
}
