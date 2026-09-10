<?php
namespace App\Models;

use App\Core\Database;

class Announcement
{
    public const TYPES = [
        'info'          => ['label' => 'Info',             'icon' => 'ℹ️'],
        'nouveaute'     => ['label' => 'Nouveauté',         'icon' => '✨'],
        'maintenance'   => ['label' => 'Maintenance',       'icon' => '🛠️'],
        'avertissement' => ['label' => 'Avertissement',     'icon' => '⚠️'],
    ];

    public static function getPublished(): array
    {
        return Database::fetchAll(
            'SELECT * FROM announcements WHERE published_at IS NOT NULL AND published_at <= NOW() ORDER BY published_at DESC'
        );
    }

    /** Toutes les annonces, brouillons compris — vue admin système. */
    public static function getAll(): array
    {
        return Database::fetchAll('SELECT * FROM announcements ORDER BY created_at DESC');
    }

    public static function getById(int $id): ?array
    {
        return Database::fetch('SELECT * FROM announcements WHERE id=?', [$id]);
    }

    public static function create(string $title, string $content, string $type, bool $publishNow): int
    {
        // published_at est comparé à SQL NOW(), que la connexion force en UTC (voir
        // Database::getInstance()) — gmdate() ici, jamais date() (heure locale du serveur PHP),
        // sous peine de publier une annonce "dans le futur" selon le fuseau du serveur.
        return Database::insert(
            'INSERT INTO announcements (title, content, type, published_at) VALUES (?,?,?,?)',
            [$title, $content, $type, $publishNow ? gmdate('Y-m-d H:i:s') : null]
        );
    }

    public static function update(int $id, string $title, string $content, string $type): void
    {
        Database::execute('UPDATE announcements SET title=?, content=?, type=? WHERE id=?', [$title, $content, $type, $id]);
    }

    public static function publish(int $id): void
    {
        Database::execute('UPDATE announcements SET published_at=NOW() WHERE id=?', [$id]);
    }

    public static function unpublish(int $id): void
    {
        Database::execute('UPDATE announcements SET published_at=NULL WHERE id=?', [$id]);
    }

    public static function delete(int $id): void
    {
        Database::execute('DELETE FROM announcements WHERE id=?', [$id]);
    }

    public static function unreadCountForUser(int $userId): int
    {
        $row = Database::fetch(
            'SELECT COUNT(*) c FROM announcements a
             WHERE a.published_at IS NOT NULL AND a.published_at <= NOW()
             AND NOT EXISTS (SELECT 1 FROM announcement_reads r WHERE r.announcement_id=a.id AND r.user_id=?)',
            [$userId]
        );
        return (int)($row['c'] ?? 0);
    }

    /** Marque toutes les annonces publiées comme lues pour ce membre (appelé à la consultation
     *  de /announcements — même principe que CommLogMessage::markAllRead()). */
    public static function markAllRead(int $userId): void
    {
        Database::execute(
            'INSERT IGNORE INTO announcement_reads (announcement_id, user_id)
             SELECT id, ? FROM announcements WHERE published_at IS NOT NULL AND published_at <= NOW()',
            [$userId]
        );
    }
}
