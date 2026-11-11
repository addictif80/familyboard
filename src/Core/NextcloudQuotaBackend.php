<?php
namespace App\Core;

class NextcloudQuotaBackend implements StorageQuotaBackend
{
    public function slug(): string { return 'nextcloud'; }

    public function syncQuota(string $ldapUsername, int $quotaGb, bool $unlimited, ?string &$error = null): bool
    {
        return NextcloudService::setUserQuota($ldapUsername, $quotaGb, $unlimited, $error);
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
