<?php
namespace App\Core;

use App\Models\ProvisioningJob;
use App\Models\StoredFile;
use App\Models\User;

/**
 * Traite la file provisioning_jobs (provision_ldap, sync_quota) contre le backend actif (voir
 * StorageProvisionerFactory) — appelé par cron.php toutes les minutes, et déclenchable à la
 * demande depuis /admin (AdminController::runStorageProvisioning), aucun accès SSH requis.
 *
 * Le job 'deprovision' n'est volontairement pas traité ici : la contrainte
 * ON DELETE CASCADE de provisioning_jobs.user_id supprimerait le job dès que le compte est
 * effacé, avant qu'il ait pu tourner — la désinscription LDAP est donc faite en synchrone,
 * best-effort, directement dans AccountDeletion::deleteUser() (même convention que
 * Mailcow::syncFamily()).
 */
class StorageProvisioningRunner
{
    /** @return array{processed:int, succeeded:int, failed:int, skipped_no_backend:bool} */
    public static function processOutstanding(int $limit = 50): array
    {
        $stats = ['processed' => 0, 'succeeded' => 0, 'failed' => 0, 'skipped_no_backend' => false];

        $provisioner = StorageProvisionerFactory::active();
        if (!$provisioner) {
            $stats['skipped_no_backend'] = true;
            return $stats;
        }

        foreach (ProvisioningJob::claimReady($limit) as $job) {
            $stats['processed']++;
            ProvisioningJob::markRunning((int)$job['id']);
            $attempts = (int)$job['attempts'] + 1;

            try {
                $ok = match ($job['job_type']) {
                    'provision_ldap' => self::runProvisionLdap($provisioner, $job),
                    'sync_quota'     => self::runSyncQuota($provisioner, $job),
                    default          => false,
                };
                if ($ok) {
                    ProvisioningJob::markDone((int)$job['id'], $job['job_type'] === 'provision_ldap');
                    $stats['succeeded']++;
                } else {
                    ProvisioningJob::markFailed((int)$job['id'], $attempts, 'Échec du backend de provisioning (voir le journal serveur).');
                    $stats['failed']++;
                }
            } catch (\Throwable $e) {
                error_log('StorageProvisioningRunner job #' . $job['id'] . ' failed: ' . $e->getMessage());
                ProvisioningJob::markFailed((int)$job['id'], $attempts, $e->getMessage());
                $stats['failed']++;
            }
        }

        return $stats;
    }

    private static function runProvisionLdap(StorageProvisioner $provisioner, array $job): bool
    {
        $payload = $job['payload'] ? json_decode($job['payload'], true) : null;
        $encryptedPassword = $payload['password'] ?? null;
        if (!$encryptedPassword) return false;
        $plainPassword = Crypto::decrypt($encryptedPassword);
        if ($plainPassword === null) return false;

        $user = User::findById((int)$job['user_id']);
        if (!$user) return true; // compte supprimé depuis l'enfilement : rien à faire, pas un échec

        $externalUsername = $provisioner->provisionUser(
            (int)$user['id'], $user['email'], $user['name'], $plainPassword, $user['ldap_username']
        );
        if (!$externalUsername) return false;

        if ($externalUsername !== $user['ldap_username']) {
            User::setLdapUsername((int)$user['id'], $externalUsername);
        }

        // Le compte vient d'être (re)créé/mis à jour : le quota doit suivre dans la foulée,
        // sans attendre un futur changement d'abonnement.
        ProvisioningJob::enqueue((int)$user['id'], 'sync_quota');

        return true;
    }

    private static function runSyncQuota(StorageProvisioner $provisioner, array $job): bool
    {
        if (!$job['ldap_username']) return false; // pas encore provisionné — le job provision_ldap doit passer en premier

        $quotaGb = StorageQuota::totalQuotaGbForMember((int)$job['user_id']);
        return $provisioner->syncQuota($job['ldap_username'], $quotaGb);
    }
}
