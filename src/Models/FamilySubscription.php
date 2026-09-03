<?php
namespace App\Models;

use App\Core\Database;

class FamilySubscription
{
    public static function getByFamily(int $familyId): ?array
    {
        return Database::fetch(
            'SELECT fs.*, p.code as plan_code, p.name as plan_name, p.member_limit
             FROM family_subscriptions fs LEFT JOIN plans p ON p.id=fs.plan_id
             WHERE fs.family_id=?',
            [$familyId]
        );
    }

    public static function getByStripeCustomerId(string $customerId): ?array
    {
        return Database::fetch('SELECT * FROM family_subscriptions WHERE stripe_customer_id=?', [$customerId]);
    }

    public static function getByStripeSubscriptionId(string $subscriptionId): ?array
    {
        return Database::fetch('SELECT * FROM family_subscriptions WHERE stripe_subscription_id=?', [$subscriptionId]);
    }

    /** Crée la ligne au premier contact avec Stripe (checkout démarré) — avant cela une famille
     *  n'a simplement aucune ligne, ce qui équivaut à status='none' (offre gratuite). */
    public static function ensureRow(int $familyId): array
    {
        $existing = self::getByFamily($familyId);
        if ($existing) return $existing;
        Database::execute('INSERT INTO family_subscriptions (family_id) VALUES (?)', [$familyId]);
        return self::getByFamily($familyId);
    }

    public static function setStripeCustomer(int $familyId, string $customerId): void
    {
        self::ensureRow($familyId);
        Database::execute('UPDATE family_subscriptions SET stripe_customer_id=? WHERE family_id=?', [$customerId, $familyId]);
    }

    /** Reflète l'état d'un abonnement Stripe (appelé depuis les webhooks). $periodEnd/$trialEnd
     *  au format DATETIME SQL ou null. */
    public static function syncFromStripe(
        string $stripeCustomerId,
        ?string $stripeSubscriptionId,
        string $status,
        ?int $planId,
        ?string $billingInterval,
        ?string $trialEndsAt,
        ?string $currentPeriodEnd
    ): void {
        $row = self::getByStripeCustomerId($stripeCustomerId);
        if (!$row) return; // client Stripe inconnu (jamais rattaché à une famille) : événement ignoré

        $graceEndsAt = null;
        if (in_array($status, ['past_due', 'canceled'], true)) {
            $graceDays = (int)(AppSetting::get('sub_grace_days') ?? '30');
            $graceEndsAt = (new \DateTime())->modify("+{$graceDays} days")->format('Y-m-d H:i:s');
        }

        $trialUsed = $status === 'trialing' ? 1 : (int)$row['trial_used'];

        Database::execute(
            'UPDATE family_subscriptions SET stripe_subscription_id=?, status=?, plan_id=?, billing_interval=?, trial_ends_at=?, current_period_end=?, grace_ends_at=?, trial_used=?, manual=0 WHERE stripe_customer_id=?',
            [$stripeSubscriptionId, $status, $planId, $billingInterval, $trialEndsAt, $currentPeriodEnd, $graceEndsAt, $trialUsed, $stripeCustomerId]
        );
    }

    public static function hasUsedTrial(int $familyId): bool
    {
        $sub = self::getByFamily($familyId);
        return (bool)($sub['trial_used'] ?? false);
    }

    /** Geste commercial / support : attribue un palier sans passer par Stripe. */
    public static function grantManual(int $familyId, int $planId, ?string $untilDate): void
    {
        self::ensureRow($familyId);
        Database::execute(
            'UPDATE family_subscriptions SET plan_id=?, status="active", billing_interval=NULL, current_period_end=?, grace_ends_at=NULL, manual=1 WHERE family_id=?',
            [$planId, $untilDate, $familyId]
        );
    }

    public static function revokeManual(int $familyId): void
    {
        Database::execute(
            'UPDATE family_subscriptions SET plan_id=NULL, status="none", current_period_end=NULL, manual=0 WHERE family_id=? AND manual=1',
            [$familyId]
        );
    }

    /** slugs des modules toujours accessibles, abonnement ou non. */
    public static function freeModules(): array
    {
        $raw = AppSetting::get('sub_free_modules') ?? '';
        return array_values(array_filter(array_map('trim', explode(',', $raw))));
    }

    public static function billingEnabled(): bool
    {
        return (bool)(int)(AppSetting::get('sub_billing_enabled') ?? '0');
    }

    /** Un module premium est accessible si la facturation est désactivée globalement (aucune
     *  restriction rétroactive au lancement), si le module fait partie du socle gratuit, ou si
     *  la famille a un abonnement en cours de validité (essai, actif, ou dans son délai de
     *  grâce après impayé/résiliation — le temps de se réabonner sans perdre l'accès). */
    public static function isEntitled(int $familyId, string $moduleSlug): bool
    {
        if (!self::billingEnabled()) return true;
        if (in_array($moduleSlug, self::freeModules(), true)) return true;

        $sub = self::getByFamily($familyId);
        if (!$sub) return false;

        $now = new \DateTime();
        if ($sub['status'] === 'trialing' && $sub['trial_ends_at'] && new \DateTime($sub['trial_ends_at']) > $now) return true;
        if ($sub['status'] === 'active') return true;
        if (in_array($sub['status'], ['past_due', 'canceled'], true) && $sub['grace_ends_at'] && new \DateTime($sub['grace_ends_at']) > $now) return true;

        return false;
    }

    public static function status(int $familyId): array
    {
        $sub = self::getByFamily($familyId);
        return [
            'billing_enabled' => self::billingEnabled(),
            'subscription'    => $sub,
        ];
    }
}
