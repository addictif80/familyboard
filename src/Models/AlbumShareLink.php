<?php
namespace App\Models;

use App\Core\Database;

class AlbumShareLink
{
    /** Le lien public actif d'un album (un seul à la fois), ou null. */
    public static function getByAlbum(int $albumId): ?array
    {
        return Database::fetch(
            'SELECT * FROM album_share_links WHERE album_id=? AND revoked_at IS NULL ORDER BY created_at DESC LIMIT 1',
            [$albumId]
        );
    }

    public static function create(int $albumId, int $userId, bool $allowUpload): array
    {
        $token = bin2hex(random_bytes(24));
        $id = Database::insert(
            'INSERT INTO album_share_links (album_id, token, allow_upload, created_by) VALUES (?,?,?,?)',
            [$albumId, $token, $allowUpload ? 1 : 0, $userId]
        );
        return Database::fetch('SELECT * FROM album_share_links WHERE id=?', [$id]);
    }

    public static function setAllowUpload(int $id, bool $allowUpload): void
    {
        Database::execute('UPDATE album_share_links SET allow_upload=? WHERE id=?', [$allowUpload ? 1 : 0, $id]);
    }

    public static function revoke(int $id): void
    {
        Database::execute('UPDATE album_share_links SET revoked_at=NOW() WHERE id=?', [$id]);
    }

    public static function findValidByToken(string $token): ?array
    {
        return Database::fetch('SELECT * FROM album_share_links WHERE token=? AND revoked_at IS NULL', [$token]);
    }
}
