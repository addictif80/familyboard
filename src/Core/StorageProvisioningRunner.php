<?php
namespace App\Core;

use App\Models\ProvisioningJob;
use App\Models\User;

/**
 * Traite la file provisioning_jobs (provision_ldap, sync_quota) — appelé par cron.php toutes
 * les minutes, et déclenchable à la demande depuis /admin
 * (AdminController::runStorageProvisioning), aucun accès SSH requis.
 *
 * provision_ldap : compte/mot de passe sur le Serveur LDAP Synology (toujours, voir
 * StorageProvisionerFactory::ldapEnabled()) — l'identité est indépendante du choix de backend
 * de quota.
 * sync_quota : poussé vers CHAQUE backend de quota actif (Synology et/ou Nextcloud) ; le job
 * n'est marqué réussi que si tous les backends actifs ont réussi, pour rester rejouable
 * simplement (un seul backend en échec → tout le job est retenté).
 *
 * Le job 'deprovision' n'est volontairement pas traité ici : la contrainte
 * ON DELETE CASCADE de provisioning_jobs.user_id supprimerait le job dès que le compte est
 * effacé, avant qu'il ait pu tourner — la désinscription est donc faite en synchrone,
 * best-effort, directement dans AccountDeletion::deleteUser() (même convention que
 * Mailcow::syncFamily()).
 */
class StorageProvisioningRunner
{
    /** @return array{processed:int, succeeded:int, failed:int, skipped_no_backend:bool} */
    public static function processOutstanding(int $limit = 50): array
    {
        $stats = ['processed' => 0, 'succeeded' => 0, 'failed' => 0, 'skipped_no_backend' => false];

        $ldapEnabled = StorageProvisionerFactory::ldapEnabled();
        $quotaBackends = StorageProvisionerFactory::activeQuotaBackends();
        if (!$ldapEnabled && empty($quotaBackends)) {
            $stats['skipped_no_backend'] = true;
            return $stats;
        }

        foreach (ProvisioningJob::claimReady($limit) as $job) {
            $stats['processed']++;
            ProvisioningJob::markRunning((int)$job['id']);
            $attempts = (int)$job['attempts'] + 1;

            try {
                $error = null;
                $ok = match ($job['job_type']) {
                    'provision_ldap' => $ldapEnabled
                        ? self::runProvisionLdap($job, $error)
                        : self::fail($error, "Comptes LDAP désactivés (storage_ldap_enabled)."),
                    'sync_quota'     => self::runSyncQuota($quotaBackends, $job, $error),
                    default          => self::fail($error, 'Type de job inconnu : ' . $job['job_type']),
                };
                if ($ok) {
                    ProvisioningJob::markDone((int)$job['id'], $job['job_type'] === 'provision_ldap');
                    $stats['succeeded']++;
                } else {
                    ProvisioningJob::markFailed((int)$job['id'], $attempts, $error ?? 'Échec du provisioning (motif non renseigné — voir le journal serveur).');
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

    /** Raccourci pour un échec immédiat avec motif, dans l'expression match() ci-dessus. */
    private static function fail(?string &$error, string $message): bool
    {
        $error = $message;
        return false;
    }

    private static function runProvisionLdap(array $job, ?string &$error): bool
    {
        $payload = $job['payload'] ? json_decode($job['payload'], true) : null;
        $encryptedPassword = $payload['password'] ?? null;
        if (!$encryptedPassword) {
            $error = 'Mot de passe chiffré absent du job (payload invalide ou déjà consommé).';
            return false;
        }
        $plainPassword = Crypto::decrypt($encryptedPassword);
        if ($plainPassword === null) {
            $error = 'Mot de passe chiffré illisible (clé applicative changée depuis l\'enfilement du job ?).';
            return false;
        }

        $user = User::findById((int)$job['user_id']);
        if (!$user) return true; // compte supprimé depuis l'enfilement : rien à faire, pas un échec

        // ldap_resolved_username : décision admin pour un conflit de uid détecté lors de la
        // migration des comptes créés avant cette intégration (adoption d'un compte existant ou
        // uid alternatif) — sans elle, createOrUpdateUser dériverait le uid depuis l'e-mail.
        $targetUsername = $user['ldap_username'] ?: ($user['ldap_resolved_username'] ?? null);
        $externalUsername = LdapService::createOrUpdateUser(
            (int)$user['id'], $user['email'], $user['name'], $plainPassword, $targetUsername, $error,
            (int)$user['family_id'], $user['role'] === 'admin'
        );
        if (!$externalUsername) return false;

        if ($externalUsername !== $user['ldap_username']) {
            User::setLdapUsername((int)$user['id'], $externalUsername);
        }

        // Sans cette étape, le membre ne verrait jamais le Group Folder partagé de sa famille
        // apparaître dans son propre espace Nextcloud (un Group Folder ne se monte que pour les
        // comptes membres du groupe assigné, vérifié contre une instance réelle).
        if (FileStore::nextcloudFilesEnabled()) {
            $groupError = null;
            // Voir users.nextcloud_username : rare cas de collision d'uid où Nextcloud a dû
            // renommer en interne son compte (suffixe numérique) — $externalUsername (le vrai
            // uid LDAP) ne correspond alors plus à aucun compte Nextcloud réel.
            $nextcloudUsername = $user['nextcloud_username'] ?: $externalUsername;
            if (!NextcloudGroupFolderService::addMemberToFamilyGroup((int)$user['family_id'], $nextcloudUsername, $groupError)) {
                $error = $groupError ?: "Ajout au groupe Nextcloud de la famille échoué.";
                return false;
            }
        }

        // Le compte vient d'être (re)créé/mis à jour : le quota doit suivre dans la foulée,
        // sans attendre un futur changement d'abonnement.
        ProvisioningJob::enqueue((int)$user['id'], 'sync_quota');

        return true;
    }

    /** @param StorageQuotaBackend[] $backends */
    private static function runSyncQuota(array $backends, array $job, ?string &$error): bool
    {
        if (empty($backends)) {
            $error = 'Aucun backend de quota actif (storage_quota_backends).';
            return false;
        }
        if (!$job['ldap_username']) {
            $error = "Compte LDAP pas encore provisionné pour ce membre — le job provision_ldap doit passer en premier.";
            return false;
        }

        $unlimited = StorageQuota::isMemberUnlimited((int)$job['user_id']);
        $quotaGb = $unlimited ? 0 : StorageQuota::totalQuotaGbForMember((int)$job['user_id']);
        // Nextcloud peut exiger un identifiant différent du uid LDAP réel dans de rares cas de
        // collision d'uid (voir users.nextcloud_username, App\Core\NextcloudService) — Synology,
        // lui, ne connaît que le vrai uid LDAP.
        $allOk = true;
        $backendErrors = [];
        foreach ($backends as $backend) {
            $backendError = null;
            $username = $backend->slug() === 'nextcloud'
                ? ($job['nextcloud_username'] ?: $job['ldap_username'])
                : $job['ldap_username'];
            if (!$backend->syncQuota($username, $quotaGb, $unlimited, $backendError)) {
                $allOk = false;
                $backendErrors[] = $backend->slug() . ' : ' . ($backendError ?? 'erreur inconnue');
            }
        }
        if (!$allOk) $error = implode(' | ', $backendErrors);
        return $allOk;
    }
}
