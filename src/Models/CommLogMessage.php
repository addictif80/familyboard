<?php
namespace App\Models;

use App\Core\Database;

/**
 * Journal de communication parental : messages volontairement immuables —
 * pas de méthode update()/delete() par conception.
 */
class CommLogMessage
{
    public static function create(
        int $familyId, int $userId, string $content, ?int $custodyScheduleId = null,
        ?string $audioPath = null, ?string $audioOriginal = null, ?string $audioMime = null, ?int $audioDuration = null
    ): int {
        return Database::insert(
            'INSERT INTO comm_log_messages (family_id, user_id, content, custody_schedule_id, audio_path, audio_original, audio_mime, audio_duration) VALUES (?,?,?,?,?,?,?,?)',
            [$familyId, $userId, $content, $custodyScheduleId, $audioPath, $audioOriginal, $audioMime, $audioDuration]
        );
    }

    public static function findById(int $id): ?array
    {
        return Database::fetch('SELECT * FROM comm_log_messages WHERE id=?', [$id]);
    }

    /** Messages tagués à l'un des plannings de garde donnés (journal parental du co-parent restreint). */
    public static function getForSchedules(array $scheduleIds, int $limit = 200): array
    {
        $scheduleIds = array_values(array_unique(array_map('intval', $scheduleIds)));
        if (empty($scheduleIds)) return [];
        $ph = implode(',', array_fill(0, count($scheduleIds), '?'));
        return Database::fetchAll(
            "SELECT m.*, COALESCE(u.name, m.former_user_name) as user_name, u.color as user_color, u.avatar as user_avatar
             FROM comm_log_messages m
             LEFT JOIN users u ON u.id = m.user_id
             WHERE m.custody_schedule_id IN ($ph)
             ORDER BY m.created_at ASC
             LIMIT ?",
            [...$scheduleIds, $limit]
        );
    }

    /** Dernier id parmi les messages tagués à ces plannings (pour le polling co-parent). */
    public static function getLastIdForSchedules(array $scheduleIds): int
    {
        $scheduleIds = array_values(array_unique(array_map('intval', $scheduleIds)));
        if (empty($scheduleIds)) return 0;
        $ph = implode(',', array_fill(0, count($scheduleIds), '?'));
        $row = Database::fetch("SELECT MAX(id) as id FROM comm_log_messages WHERE custody_schedule_id IN ($ph)", $scheduleIds);
        return (int)($row['id'] ?? 0);
    }

    /** Nouveaux messages tagués à ces plannings, postérieurs à $afterId (polling co-parent). */
    public static function getNewForSchedules(array $scheduleIds, int $afterId): array
    {
        $scheduleIds = array_values(array_unique(array_map('intval', $scheduleIds)));
        if (empty($scheduleIds)) return [];
        $ph = implode(',', array_fill(0, count($scheduleIds), '?'));
        return Database::fetchAll(
            "SELECT m.*, COALESCE(u.name, m.former_user_name) as user_name, u.color as user_color, u.avatar as user_avatar
             FROM comm_log_messages m
             LEFT JOIN users u ON u.id = m.user_id
             WHERE m.custody_schedule_id IN ($ph) AND m.id > ?
             ORDER BY m.created_at ASC",
            [...$scheduleIds, $afterId]
        );
    }

    public static function getByFamily(int $familyId, int $limit = 200): array
    {
        return Database::fetchAll(
            'SELECT m.*, COALESCE(u.name, m.former_user_name) as user_name, u.color as user_color, u.avatar as user_avatar
             FROM comm_log_messages m
             LEFT JOIN users u ON u.id = m.user_id
             WHERE m.family_id=?
             ORDER BY m.created_at ASC
             LIMIT ?',
            [$familyId, $limit]
        );
    }

    public static function getNew(int $familyId, int $afterId): array
    {
        return Database::fetchAll(
            'SELECT m.*, COALESCE(u.name, m.former_user_name) as user_name, u.color as user_color, u.avatar as user_avatar
             FROM comm_log_messages m
             LEFT JOIN users u ON u.id = m.user_id
             WHERE m.family_id=? AND m.id > ?
             ORDER BY m.created_at ASC',
            [$familyId, $afterId]
        );
    }

    public static function getLastId(int $familyId): int
    {
        $row = Database::fetch('SELECT MAX(id) as id FROM comm_log_messages WHERE family_id=?', [$familyId]);
        return (int)($row['id'] ?? 0);
    }

    /** Mark every message not authored by $userId as read by them. */
    public static function markAllRead(int $familyId, int $userId): void
    {
        Database::execute(
            'INSERT IGNORE INTO comm_log_reads (message_id, user_id)
             SELECT m.id, ? FROM comm_log_messages m WHERE m.family_id=? AND m.user_id != ?',
            [$userId, $familyId, $userId]
        );
    }

    /** [message_id => [ ['user_name'=>..., 'read_at'=>...], ... ]] */
    public static function getReadsByFamily(int $familyId): array
    {
        $rows = Database::fetchAll(
            'SELECT r.message_id, r.read_at, u.name as user_name
             FROM comm_log_reads r
             JOIN comm_log_messages m ON m.id = r.message_id
             JOIN users u ON u.id = r.user_id
             WHERE m.family_id=?',
            [$familyId]
        );
        $byMessage = [];
        foreach ($rows as $row) {
            $byMessage[$row['message_id']][] = ['user_name' => $row['user_name'], 'read_at' => $row['read_at']];
        }
        return $byMessage;
    }
}
