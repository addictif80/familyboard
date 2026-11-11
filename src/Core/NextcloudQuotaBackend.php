<?php
namespace App\Core;

class NextcloudQuotaBackend implements StorageQuotaBackend
{
    public function slug(): string { return 'nextcloud'; }

    public function syncQuota(string $ldapUsername, int $quotaGb, ?string &$error = null): bool
    {
        return NextcloudService::setUserQuota($ldapUsername, $quotaGb, $error);
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
