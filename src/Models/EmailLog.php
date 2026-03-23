<?php
namespace App\Models;

use App\Core\Database;

class EmailLog
{
    public static function add(int $familyId, ?int $sentBy, string $toEmail, string $toName, string $subject, string $body, string $type, bool $success, string $error = ''): void
    {
        Database::insert(
            'INSERT INTO email_logs (family_id, sent_by, to_email, to_name, subject, body, type, status, error_message)
             VALUES (?,?,?,?,?,?,?,?,?)',
            [$familyId, $sentBy, $toEmail, $toName, $subject, $body, $type,
             $success ? 'sent' : 'failed', $error ?: null]
        );
    }

    public static function getByFamily(int $familyId, int $limit = 50): array
    {
        return Database::fetchAll(
            'SELECT el.*, u.name as sender_name FROM email_logs el
             LEFT JOIN users u ON u.id = el.sent_by
             WHERE el.family_id = ?
             ORDER BY el.created_at DESC LIMIT ?',
            [$familyId, $limit]
        );
    }

    public static function getLastSent(int $familyId, string $type, string $reference): ?array
    {
        return Database::fetch(
            'SELECT * FROM email_logs WHERE family_id = ? AND type = ? AND to_email = ? ORDER BY created_at DESC LIMIT 1',
            [$familyId, $type, $reference]
        );
    }
}
