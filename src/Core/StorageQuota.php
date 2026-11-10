<?php
namespace App\Core;

use App\Models\AppSetting;
use App\Models\MemberStorageSubscription;

/**
 * Calcul du quota de stockage perso (Go) d'un membre : quota gratuit + somme des abonnements
 * additionnels actifs/en grâce (App\Models\MemberStorageSubscription::totalAddonGbForBeneficiary).
 * Le quota familial partagé (storage_family_quota_gb) est séparé et ne dépend d'aucun membre.
 * Tous les réglages viennent de app_settings, rien n'est en dur (voir add_storage_billing.sql).
 */
class StorageQuota
{
    public static function freeQuotaGb(): int
    {
        return (int)AppSetting::get('storage_free_quota_gb', '5');
    }

    public static function familyQuotaGb(): int
    {
        return (int)AppSetting::get('storage_family_quota_gb', '50');
    }

    /** Plafond d'add-ons par membre (Go) — le réglage individuel users.storage_addon_cap_gb
     *  prime s'il est renseigné, sinon le plafond admin global s'applique. */
    public static function memberAddonCapGb(?int $userCap): int
    {
        return $userCap ?? (int)AppSetting::get('storage_member_addon_cap_gb', '1024');
    }

    /** Quota perso total (Go) actuellement dû à un membre : gratuit + add-ons actifs/en grâce. */
    public static function totalQuotaGbForMember(int $userId): int
    {
        return self::freeQuotaGb() + MemberStorageSubscription::totalAddonGbForBeneficiary($userId);
    }

    public static function gracePeriodDays(): int
    {
        return (int)AppSetting::get('storage_grace_days', '30');
    }

    public static function capacityAlertThresholdPct(): int
    {
        return (int)AppSetting::get('storage_capacity_alert_threshold_pct', '70');
    }

    public static function billingEnabled(): bool
    {
        return AppSetting::get('storage_billing_enabled', '0') === '1';
    }
}
