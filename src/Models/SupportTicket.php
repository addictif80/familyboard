<?php
namespace App\Models;

use App\Core\Database;

class SupportTicket
{
    // ── Tickets ─────────────────────────────────────────────────

    public static function getAll(): array
    {
        return Database::fetchAll(
            'SELECT t.*, u.name as user_name, f.name as family_name,
             (SELECT COUNT(*) FROM support_messages m WHERE m.ticket_id=t.id) as msg_count
             FROM support_tickets t
             JOIN users    u ON u.id=t.user_id
             JOIN families f ON f.id=t.family_id
             ORDER BY t.status ASC, t.updated_at DESC'
        );
    }

    public static function getByFamily(int $familyId): array
    {
        return Database::fetchAll(
            'SELECT t.*,
             (SELECT COUNT(*) FROM support_messages m WHERE m.ticket_id=t.id) as msg_count
             FROM support_tickets t
             WHERE t.family_id=?
             ORDER BY t.updated_at DESC',
            [$familyId]
        );
    }

    public static function getById(int $id): ?array
    {
        return Database::fetch(
            'SELECT t.*, u.name as user_name, f.name as family_name
             FROM support_tickets t
             JOIN users    u ON u.id=t.user_id
             JOIN families f ON f.id=t.family_id
             WHERE t.id=?',
            [$id]
        );
    }

    public static function create(int $familyId, int $userId, string $subject, string $firstMessage): int
    {
        $id = Database::insert(
            'INSERT INTO support_tickets (family_id, user_id, subject) VALUES (?,?,?)',
            [$familyId, $userId, $subject]
        );
        self::addMessage($id, $userId, false, $firstMessage);
        return $id;
    }

    public static function setStatus(int $id, string $status): void
    {
        Database::execute('UPDATE support_tickets SET status=? WHERE id=?', [$status, $id]);
    }

    /** Dernier ticket (tout statut confondu) pour un utilisateur et une erreur donnée — voir ErrorReporter. */
    public static function findByUserAndHash(int $userId, string $hash): ?array
    {
        return Database::fetch(
            'SELECT * FROM support_tickets WHERE user_id=? AND error_hash=? ORDER BY updated_at DESC LIMIT 1',
            [$userId, $hash]
        );
    }

    public static function markAuto(int $id, string $hash): void
    {
        Database::execute("UPDATE support_tickets SET source='auto', error_hash=? WHERE id=?", [$hash, $id]);
    }

    // ── Messages ────────────────────────────────────────────────

    public static function getMessages(int $ticketId): array
    {
        return Database::fetchAll(
            'SELECT m.*, u.name as user_name, u.color as user_color
             FROM support_messages m
             LEFT JOIN users u ON u.id=m.user_id
             WHERE m.ticket_id=?
             ORDER BY m.created_at ASC',
            [$ticketId]
        );
    }

    public static function addMessage(int $ticketId, ?int $userId, bool $isAdmin, string $message): int
    {
        Database::execute(
            'UPDATE support_tickets SET updated_at=NOW(), status=IF(status="closed","in_progress",status) WHERE id=?',
            [$ticketId]
        );
        return Database::insert(
            'INSERT INTO support_messages (ticket_id, user_id, is_admin, message) VALUES (?,?,?,?)',
            [$ticketId, $userId, $isAdmin ? 1 : 0, $message]
        );
    }

    // ── Stats ────────────────────────────────────────────────────

    public static function countOpen(): int
    {
        $r = Database::fetch("SELECT COUNT(*) c FROM support_tickets WHERE status!='closed'");
        return (int)($r['c'] ?? 0);
    }
}
