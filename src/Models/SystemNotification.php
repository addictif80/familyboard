<?php
namespace App\Models;

use App\Core\Database;

class SystemNotification
{
    public static function create(string $title, string $shortText, string $contentHtml): int
    {
        return Database::insert(
            'INSERT INTO system_notifications (title, short_text, content_html) VALUES (?,?,?)',
            [$title, $shortText, $contentHtml]
        );
    }

    public static function findById(int $id): ?array
    {
        return Database::fetch('SELECT * FROM system_notifications WHERE id=?', [$id]);
    }
}
