<?php
namespace App\Models;

use App\Core\Database;

class AlbumPhoto
{
    public static function getByAlbum(int $albumId): array
    {
        return Database::fetchAll(
            'SELECT p.*, u.name as user_name, u.color as user_color
             FROM album_photos p JOIN users u ON u.id=p.user_id
             WHERE p.album_id=? ORDER BY p.created_at DESC',
            [$albumId]
        );
    }

    /** Inclut family_id/custody_schedule_id de l'album parent pour les contrôles d'accès. */
    public static function getById(int $id): ?array
    {
        return Database::fetch(
            'SELECT p.*, a.family_id, a.custody_schedule_id
             FROM album_photos p JOIN albums a ON a.id=p.album_id
             WHERE p.id=?',
            [$id]
        );
    }

    public static function create(int $albumId, int $userId, string $imagePath, string $caption = ''): int
    {
        return Database::insert(
            'INSERT INTO album_photos (album_id, user_id, image_path, caption) VALUES (?,?,?,?)',
            [$albumId, $userId, $imagePath, $caption ?: null]
        );
    }

    public static function delete(int $id): void
    {
        Database::execute('DELETE FROM album_photos WHERE id=?', [$id]);
    }
}
