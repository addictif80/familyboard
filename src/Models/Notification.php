<?php
namespace App\Models;

use App\Core\Database;

class Notification
{
    public static function create(int $userId, string $type, string $title, string $message, ?string $link = null): int
    {
        $id = Database::insert(
            'INSERT INTO notifications (user_id, type, title, message, link) VALUES (?,?,?,?,?)',
            [$userId, $type, $title, $message, $link]
        );
        try {
            \App\Core\Push::sendToUser($userId, $title, $message, $link);
        } catch (\Throwable $e) {
            error_log('Push notification error: ' . $e->getMessage());
        }
        return $id;
    }

    public static function getByUser(int $userId, int $limit = 20): array
    {
        return Database::fetchAll(
            'SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT ?',
            [$userId, $limit]
        );
    }

    public static function markRead(int $id, int $userId): void
    {
        Database::execute('UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?', [$id, $userId]);
    }

    public static function markAllRead(int $userId): void
    {
        Database::execute('UPDATE notifications SET is_read=1 WHERE user_id=?', [$userId]);
    }

    public static function getUnreadCount(int $userId): int
    {
        $row = Database::fetch('SELECT COUNT(*) as cnt FROM notifications WHERE user_id=? AND is_read=0', [$userId]);
        return (int)($row['cnt'] ?? 0);
    }

    /**
     * $custodyScheduleId : quand l'évènement concerne un enfant en garde partagée précis (garde,
     * proposition, journal parental…), passer l'ID du planning concerné pour que les comptes à
     * accès restreint (role=coparent) ne reçoivent que les évènements pertinents pour eux — ils
     * ne doivent jamais être notifiés d'un évènement familial générique (mur, chat, document,
     * calendrier...) sans rapport avec la garde.
     */
    public static function notifyFamily(int $familyId, int $excludeUserId, string $type, string $title, string $message, ?string $link = null, ?int $custodyScheduleId = null): void
    {
        $users = User::getByFamily($familyId);
        foreach ($users as $user) {
            if ($user['id'] === $excludeUserId) continue;
            if ($user['role'] === 'coparent') {
                if ($custodyScheduleId === null || !Custody::userHasAccessToSchedule((int)$user['id'], $custodyScheduleId)) {
                    continue;
                }
            }
            self::create((int)$user['id'], $type, $title, $message, $link);
        }
    }

    /**
     * Notification envoyée à ma famille ET à toutes mes familles amies (scope "familles amies"
     * du mur social) — réutilise notifyFamily() pour ma propre famille (mêmes règles co-parent),
     * puis notifie tous les membres non co-parent de chaque famille amie.
     */
    public static function notifyNetwork(int $familyId, int $excludeUserId, string $type, string $title, string $message, ?string $link = null): void
    {
        self::notifyFamily($familyId, $excludeUserId, $type, $title, $message, $link);
        foreach (\App\Models\FamilyFriend::getAcceptedFor($familyId) as $friendFamily) {
            foreach (User::getByFamily((int)$friendFamily['family_id']) as $member) {
                if ($member['role'] === 'coparent') continue;
                self::create((int)$member['id'], $type, $title, $message, $link);
            }
        }
    }

    /**
     * Notification système envoyée par un administrateur à tous les utilisateurs, toutes
     * familles confondues. Le contenu long (WYSIWYG) est stocké une seule fois dans
     * system_notifications ; chaque destinataire reçoit une notification pointant vers sa page
     * dédiée (/notifications/{id}), affichée au clic depuis la cloche du site ou le push navigateur.
     */
    public static function broadcastToAll(string $title, string $shortText, string $contentHtml): int
    {
        $userIds = array_map(fn($row) => (int)$row['id'], Database::fetchAll('SELECT id FROM users'));
        return self::sendSystemNotification($userIds, $title, $shortText, $contentHtml);
    }

    /**
     * Variante ciblée de broadcastToAll() : même mécanique (contenu stocké une seule fois dans
     * system_notifications, page dédiée au clic) mais réservée aux comptes listés dans
     * $userIds — pour un administrateur système qui veut prévenir un ou quelques utilisateurs
     * précis plutôt que tout FamilyBoard. Les ids invalides (utilisateur supprimé entre-temps,
     * id qui n'existe pas) sont silencieusement ignorés plutôt que de faire échouer l'envoi aux
     * destinataires valides.
     */
    public static function sendToUsers(array $userIds, string $title, string $shortText, string $contentHtml): int
    {
        $userIds = array_values(array_unique(array_map('intval', $userIds)));
        if (!$userIds) return 0;
        $ph = implode(',', array_fill(0, count($userIds), '?'));
        $validIds = array_map(fn($row) => (int)$row['id'], Database::fetchAll("SELECT id FROM users WHERE id IN ($ph)", $userIds));
        return self::sendSystemNotification($validIds, $title, $shortText, $contentHtml);
    }

    private static function sendSystemNotification(array $userIds, string $title, string $shortText, string $contentHtml): int
    {
        if (!$userIds) return 0;
        $sysId = SystemNotification::create($title, $shortText, $contentHtml);
        $link  = BASE_URL . '/notifications/' . $sysId;

        foreach ($userIds as $id) {
            self::create($id, 'system', $title, $shortText, $link);
        }
        return count($userIds);
    }
}
