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

    /** Quota familial illimité — soit par le réglage admin global (storage_family_quota_unlimited,
     *  s'applique à toutes les familles), soit par familles.storage_quota_unlimited propre à UNE
     *  famille (si $familyId fourni) — l'un ou l'autre suffit. familyQuotaGb() reste
     *  affiché/stocké tel quel dans les deux cas, mais n'est plus opposé à personne (voir
     *  FileStore::enforceQuota). Sans $familyId, ne reflète que le réglage global (affichage du
     *  panneau admin général, hors contexte d'une famille précise). */
    public static function familyQuotaUnlimited(?int $familyId = null): bool
    {
        if (AppSetting::get('storage_family_quota_unlimited', '0') === '1') return true;
        if ($familyId === null) return false;
        $row = \App\Core\Database::fetch('SELECT storage_quota_unlimited FROM families WHERE id = ?', [$familyId]);
        return !empty($row['storage_quota_unlimited']);
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

    /** Quota perso illimité pour ce membre (réglage admin par compte, users.storage_quota_unlimited)
     *  — quand actif, remplace totalQuotaGbForMember() pour le provisioning (voir
     *  StorageProvisioningRunner::runSyncQuota), propagé aux backends comme "pas de limite". */
    public static function isMemberUnlimited(int $userId): bool
    {
        $row = \App\Core\Database::fetch('SELECT storage_quota_unlimited FROM users WHERE id = ?', [$userId]);
        return !empty($row['storage_quota_unlimited']);
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
