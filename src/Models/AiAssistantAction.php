<?php
namespace App\Models;

use App\Core\Database;

/** Journal des créations effectuées par l'assistant — jamais une boîte noire : chaque action
 *  est tracée avec qui a demandé quoi, visible par toute la famille. */
class AiAssistantAction
{
    public static function getByFamily(int $familyId, int $limit = 50): array
    {
        return Database::fetchAll(
            'SELECT a.*, u.name as user_name FROM ai_assistant_actions a JOIN users u ON u.id=a.user_id
             WHERE a.family_id=? ORDER BY a.created_at DESC LIMIT ?',
            [$familyId, $limit]
        );
    }

    public static function log(int $familyId, int $userId, string $actionType, string $summary, string $targetTable, int $targetId): int
    {
        return Database::insert(
            'INSERT INTO ai_assistant_actions (family_id, user_id, action_type, summary, target_table, target_id) VALUES (?,?,?,?,?,?)',
            [$familyId, $userId, $actionType, $summary, $targetTable, $targetId]
        );
    }
}
