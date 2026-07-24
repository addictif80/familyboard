<?php
namespace App\Models;

/**
 * Global SMTP configuration, set once by the system administrator
 * (panneau /admin) and shared by every family — not per-family.
 */
class SmtpSettings
{
    public static function get(): ?array
    {
        $host = AppSetting::get('smtp_host');
        if (!$host) return null;

        return [
            'host'       => $host,
            'port'       => (int)AppSetting::get('smtp_port', '587'),
            'username'   => AppSetting::get('smtp_username', ''),
            'password'   => AppSetting::get('smtp_password', ''),
            'from_email' => AppSetting::get('smtp_from_email', ''),
            'from_name'  => AppSetting::get('smtp_from_name', 'FamilyBoard'),
            'encryption' => AppSetting::get('smtp_encryption', 'tls'),
        ];
    }

    public static function save(array $data): void
    {
        AppSetting::set('smtp_host', $data['host'] ?? '');
        AppSetting::set('smtp_port', (string)($data['port'] ?? 587));
        AppSetting::set('smtp_username', $data['username'] ?? '');
        AppSetting::set('smtp_password', $data['password'] ?? '');
        AppSetting::set('smtp_from_email', $data['from_email'] ?? '');
        AppSetting::set('smtp_from_name', $data['from_name'] ?? '');
        AppSetting::set('smtp_encryption', $data['encryption'] ?? 'tls');
    }
}
