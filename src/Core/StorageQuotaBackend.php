<?php
namespace App\Core;

/**
 * Abstraction d'un backend de PUSH DE QUOTA uniquement — l'identité (compte LDAP) est
 * toujours portée par le Serveur LDAP Synology (voir LdapService), indépendamment du ou des
 * backends de quota choisis ici par l'admin (storage_quota_backends, CSV) : Synology et/ou
 * Nextcloud peuvent être actifs simultanément (Nextcloud reconnaît le même compte via sa propre
 * configuration LDAP côté NAS, FamilyBoard ne fait que lui pousser le quota en plus).
 */
interface StorageQuotaBackend
{
    /** Identifiant court utilisé dans storage_quota_backends et les rapports admin. */
    public function slug(): string;

    /** Pousse le quota (Go) du membre identifié par son uid LDAP — ou aucune limite si
     *  $unlimited (dans ce cas $quotaGb est ignoré). Retourne false en cas d'échec (le job
     *  sync_quota sera rejoué) — $error est alors renseigné avec le motif exact, pour qu'il
     *  remonte jusqu'au panneau admin sans avoir à consulter les journaux serveur (voir
     *  ProvisioningJob::markFailed). */
    public function syncQuota(string $ldapUsername, int $quotaGb, bool $unlimited, ?string &$error = null): bool;

    /** Retire l'accès/quota d'un membre qui part — best-effort, sans effet si déjà absent. */
    public function deprovisionUser(string $ldapUsername): void;

    public function testConnection(): array;
}
