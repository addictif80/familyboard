<?php
namespace App\Models;

use App\Core\Database;

class AlbumPhoto
{
    public static function getByAlbum(int $albumId): array
    {
        return Database::fetchAll(
            'SELECT p.*, u.name as user_name, u.color as user_color
             FROM album_photos p LEFT JOIN users u ON u.id=p.user_id
             WHERE p.album_id=? ORDER BY p.created_at DESC',
            [$albumId]
        );
    }

    /** Inclut family_id de l'album parent pour les contrôles d'accès. */
    public static function getById(int $id): ?array
    {
        return Database::fetch(
            'SELECT p.*, a.family_id
             FROM album_photos p JOIN albums a ON a.id=p.album_id
             WHERE p.id=?',
            [$id]
        );
    }

    /**
     * $userId est NULL pour un ajout via lien public (visiteur sans compte) —
     * on garde alors $uploaderName (saisi librement) et $shareLinkId pour
     * l'affichage et la traçabilité.
     */
    public static function create(
        int $albumId,
        ?int $userId,
        string $imagePath,
        string $caption = '',
        ?string $uploaderName = null,
        ?int $shareLinkId = null
    ): int {
        return Database::insert(
            'INSERT INTO album_photos (album_id, user_id, image_path, caption, uploader_name, share_link_id) VALUES (?,?,?,?,?,?)',
            [$albumId, $userId, $imagePath, $caption ?: null, $uploaderName ?: null, $shareLinkId]
        );
    }

    public static function delete(int $id): void
    {
        Database::execute('DELETE FROM album_photos WHERE id=?', [$id]);
    }
}
