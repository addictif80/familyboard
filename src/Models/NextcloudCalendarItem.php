<?php
namespace App\Models;

use App\Core\Database;

/** État de réconciliation de la synchronisation calendrier → Nextcloud (voir
 *  App\Core\NextcloudCalendarSync) : quel événement a déjà été poussé pour quel membre, avec
 *  quel contenu — permet de détecter un changement (hash différent) ou une suppression (UID
 *  plus présent dans l'état désiré) sans dépendre d'un hook par écriture. */
class NextcloudCalendarItem
{
    /** @return array<string, string> uid => hash */
    public static function getForUser(int $userId): array
    {
        $rows = Database::fetchAll('SELECT item_uid, content_hash FROM nextcloud_calendar_items WHERE user_id = ?', [$userId]);
        $map = [];
        foreach ($rows as $row) {
            $map[$row['item_uid']] = $row['content_hash'];
        }
        return $map;
    }

    public static function upsert(int $userId, string $uid, string $hash): void
    {
        Database::execute(
            'INSERT INTO nextcloud_calendar_items (user_id, item_uid, content_hash) VALUES (?,?,?)
             ON DUPLICATE KEY UPDATE content_hash = VALUES(content_hash), synced_at = NOW()',
            [$userId, $uid, $hash]
        );
    }

    public static function delete(int $userId, string $uid): void
    {
        Database::execute('DELETE FROM nextcloud_calendar_items WHERE user_id = ? AND item_uid = ?', [$userId, $uid]);
    }
}
