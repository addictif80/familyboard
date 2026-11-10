<?php
namespace App\Core;

/** Implémentation Synology de StorageProvisioner : compte LDAP (voir LdapService) + quota via
 *  l'API DSM (voir SynologyService). */
class SynologyProvisioner implements StorageProvisioner
{
    public function provisionUser(int $userId, string $email, string $displayName, string $plainPassword, ?string $existingExternalUsername): ?string
    {
        return LdapService::createOrUpdateUser($userId, $email, $displayName, $plainPassword, $existingExternalUsername);
    }

    public function syncQuota(string $externalUsername, int $quotaGb): bool
    {
        return SynologyService::setUserQuota($externalUsername, $quotaGb);
    }

    public function deprovisionUser(string $externalUsername): void
    {
        LdapService::deleteUser($externalUsername);
    }

    public function testConnection(): array
    {
        $ldap = LdapService::testConnection();
        if (!$ldap['ok']) return $ldap;
        return SynologyService::testConnection();
    }
}
