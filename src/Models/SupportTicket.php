<?php
namespace App\Models;

use App\Core\Database;

class SupportTicket
{
    /** RGPD (durée de conservation) : un ticket clos depuis longtemps (diagnostics techniques,
     *  échanges avec l'utilisateur) n'a plus d'utilité opérationnelle passé un délai raisonnable. */
    public static function purgeClosedOlderThan(int $months = 18): int
    {
        return Database::execute(
            "DELETE FROM support_tickets WHERE status='closed' AND updated_at < DATE_SUB(NOW(), INTERVAL ? MONTH)",
            [$months]
        );
    }

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
        self::notifyAdminOfNewTicket($id, $familyId, $userId, $subject, $firstMessage);
        return $id;
    }

    /** Prévient l'administrateur système par email (si une adresse est renseignée) qu'un nouveau ticket a été ouvert. */
    private static function notifyAdminOfNewTicket(int $id, int $familyId, int $userId, string $subject, string $firstMessage): void
    {
        $adminEmail = AppSetting::get('admin_email');
        if (!$adminEmail) return;

        try {
            $user = User::findById($userId);
            $html = '<p>Nouveau ticket de support ouvert par ' . htmlspecialchars($user['name'] ?? 'un utilisateur') . ' :</p>'
                  . '<p><strong>' . htmlspecialchars($subject) . '</strong></p>'
                  . '<p>' . nl2br(htmlspecialchars($firstMessage)) . '</p>'
                  . '<p><a href="' . BASE_URL . '/admin/tickets/' . $id . '">Voir le ticket dans l\'administration</a></p>';
            \App\Core\Mail::send($familyId, $adminEmail, 'Administrateur', '[FamilyBoard] Nouveau ticket : ' . $subject, $html, 'admin_ticket_notification');
        } catch (\Throwable $e) {
            error_log('Admin ticket notification email error: ' . $e->getMessage());
        }
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

    public static function markSource(int $id, string $source, ?string $hash = null): void
    {
        Database::execute('UPDATE support_tickets SET source=?, error_hash=? WHERE id=?', [$source, $hash, $id]);
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
