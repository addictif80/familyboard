<?php
namespace App\Core;

class NextcloudQuotaBackend implements StorageQuotaBackend
{
    public function slug(): string { return 'nextcloud'; }

    public function syncQuota(string $ldapUsername, int $quotaGb): bool
    {
        return NextcloudService::setUserQuota($ldapUsername, $quotaGb);
    }

    public function deprovisionUser(string $ldapUsername): void
    {
        NextcloudService::disableUser($ldapUsername);
    }

    public function testConnection(): array
    {
        return NextcloudService::testConnection();
    }
}
