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

        $wasLapsed = in_array($row['status'], ['past_due', 'canceled'], true);
        $isLapsed  = in_array($status, ['past_due', 'canceled'], true);

        $graceStartedAt = $row['grace_started_at'];
        $graceEndsAt = null;
        if ($isLapsed) {
            $graceDays = (int)(AppSetting::get('sub_grace_days') ?? '30');
            // Ne redémarre pas le compte à rebours si on était déjà en défaut de paiement (ex.
            // Stripe repasse plusieurs fois par 'past_due' pendant ses relances automatiques) —
            // seul le tout premier basculement fixe grace_started_at/grace_ends_at.
            if (!$wasLapsed) {
                $graceStartedAt = (new \DateTime())->format('Y-m-d H:i:s');
                $graceEndsAt = (new \DateTime())->modify("+{$graceDays} days")->format('Y-m-d H:i:s');
            } else {
                $graceEndsAt = $row['grace_ends_at'];
            }
        } else {
            $graceStartedAt = null;
        }

        $trialUsed = $status === 'trialing' ? 1 : (int)$row['trial_used'];

        // Retour à un statut valide (paiement régularisé) : on efface les compteurs de purge et
        // de relances, la famille repart sur une base saine si elle repasse en défaut plus tard.
        $resetPurgeTracking = !$isLapsed;

        Database::execute(
            'UPDATE family_subscriptions SET stripe_subscription_id=?, status=?, plan_id=?, billing_interval=?, trial_ends_at=?, current_period_end=?, grace_started_at=?, grace_ends_at=?, trial_used=?, manual=0'
            . ($resetPurgeTracking ? ', data_purged_at=NULL, reminder_downgrade_sent_at=NULL, reminder_midpoint_sent_at=NULL, reminder_final_sent_at=NULL' : '')
            . ' WHERE stripe_customer_id=?',
            [$stripeSubscriptionId, $status, $planId, $billingInterval, $trialEndsAt, $currentPeriodEnd, $graceStartedAt, $graceEndsAt, $trialUsed, $stripeCustomerId]
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
            'UPDATE family_subscriptions SET plan_id=?, status="active", billing_interval=NULL, current_period_end=?, grace_started_at=NULL, grace_ends_at=NULL,
             data_purged_at=NULL, reminder_downgrade_sent_at=NULL, reminder_midpoint_sent_at=NULL, reminder_final_sent_at=NULL, manual=1 WHERE family_id=?',
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

    /** Bascule IMMÉDIATE en offre Gratuite dès que l'essai se termine sans paiement ou qu'un
     *  paiement échoue (status devient 'past_due'/'canceled', voir syncFromStripe()) — aucun
     *  accès maintenu "le temps de payer". Les données des modules premium, elles, restent en
     *  base jusqu'à grace_ends_at (voir PremiumDataPurge et cron.php) : une famille qui se
     *  réabonne avant cette date retrouve tout tel quel. */
    public static function isEntitled(int $familyId, string $moduleSlug): bool
    {
        if (!self::billingEnabled()) return true;
        if (in_array($moduleSlug, self::freeModules(), true)) return true;

        $sub = self::getByFamily($familyId);
        if (!$sub) return false;

        $now = new \DateTime();
        if ($sub['status'] === 'trialing' && $sub['trial_ends_at'] && new \DateTime($sub['trial_ends_at']) > $now) return true;
        if ($sub['status'] === 'active') return true;

        return false;
    }

    /** Familles actuellement en défaut de paiement (essai non converti ou paiement échoué),
     *  données pas encore purgées — traitées par cron.php pour les relances puis la purge
     *  définitive une fois grace_ends_at dépassé. */
    public static function getLapsed(): array
    {
        return Database::fetchAll(
            "SELECT fs.*, f.name as family_name FROM family_subscriptions fs JOIN families f ON f.id=fs.family_id
             WHERE fs.status IN ('past_due','canceled') AND fs.grace_ends_at IS NOT NULL AND fs.data_purged_at IS NULL"
        );
    }

    public static function markReminderSent(int $familyId, string $stage): void
    {
        $col = match ($stage) {
            'downgrade' => 'reminder_downgrade_sent_at',
            'midpoint'  => 'reminder_midpoint_sent_at',
            'final'     => 'reminder_final_sent_at',
            default     => null,
        };
        if (!$col) return;
        Database::execute("UPDATE family_subscriptions SET `$col`=NOW() WHERE family_id=?", [$familyId]);
    }

    public static function markDataPurged(int $familyId): void
    {
        Database::execute('UPDATE family_subscriptions SET data_purged_at=NOW() WHERE family_id=?', [$familyId]);
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
