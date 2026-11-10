<?php
namespace App\Core;

class SynologyQuotaBackend implements StorageQuotaBackend
{
    public function slug(): string { return 'synology'; }

    public function syncQuota(string $ldapUsername, int $quotaGb): bool
    {
        return SynologyService::setUserQuota($ldapUsername, $quotaGb);
    }

    public function deprovisionUser(string $ldapUsername): void
    {
        // Rien côté Synology au-delà du compte LDAP lui-même (voir LdapService::deleteUser,
        // appelé indépendamment) — le quota disparaît avec le compte.
    }

    public function testConnection(): array
    {
        return SynologyService::testConnection();
    }
}
