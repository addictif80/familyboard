<?php
namespace App\Models;

use App\Core\Database;

/** Conversation partagée par famille (comme le chat familial) — tous les membres voient le
 *  même fil et les mêmes actions passées, pour que l'assistant ne soit jamais une boîte noire
 *  connue d'un seul membre. */
class AiAssistantMessage
{
    private const HISTORY_LIMIT = 30;

    public static function getByFamily(int $familyId, int $limit = 50): array
    {
        return Database::fetchAll(
            'SELECT m.*, u.name as user_name, u.color as user_color
             FROM ai_assistant_messages m LEFT JOIN users u ON u.id=m.user_id
             WHERE m.family_id=? ORDER BY m.created_at DESC, m.id DESC LIMIT ?',
            [$familyId, $limit]
        );
    }

    /** Historique récent en ordre chronologique, au format attendu par Ollama::chat(). */
    public static function getRecentForPrompt(int $familyId): array
    {
        $rows = Database::fetchAll(
            'SELECT role, content FROM ai_assistant_messages WHERE family_id=? ORDER BY created_at DESC, id DESC LIMIT ?',
            [$familyId, self::HISTORY_LIMIT]
        );
        return array_reverse($rows);
    }

    public static function create(int $familyId, ?int $userId, string $role, string $content): int
    {
        return Database::insert(
            'INSERT INTO ai_assistant_messages (family_id, user_id, role, content) VALUES (?,?,?,?)',
            [$familyId, $userId, $role, $content]
        );
    }

    public static function clearForFamily(int $familyId): void
    {
        Database::execute('DELETE FROM ai_assistant_messages WHERE family_id=?', [$familyId]);
    }
}
