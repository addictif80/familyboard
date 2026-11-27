<?php
namespace App\Models;

use App\Core\Database;

class Poll
{
    /** Sondages de la famille, options + décompte des votes + le choix du spectateur. */
    public static function getByFamily(int $familyId, int $viewerId): array
    {
        $polls = Database::fetchAll(
            'SELECT p.*, u.name as author_name,
                    (SELECT COUNT(*) FROM poll_votes v WHERE v.poll_id=p.id) as vote_count
             FROM polls p JOIN users u ON u.id=p.user_id
             WHERE p.family_id=? ORDER BY p.is_closed ASC, p.created_at DESC',
            [$familyId]
        );
        foreach ($polls as &$poll) {
            $poll['options'] = self::getOptions((int)$poll['id']);
            $myVote = Database::fetch('SELECT option_id FROM poll_votes WHERE poll_id=? AND user_id=?', [$poll['id'], $viewerId]);
            $poll['my_vote'] = $myVote ? (int)$myVote['option_id'] : null;
        }
        return $polls;
    }

    public static function getOptions(int $pollId): array
    {
        return Database::fetchAll(
            'SELECT o.*, (SELECT COUNT(*) FROM poll_votes v WHERE v.option_id=o.id) as votes
             FROM poll_options o WHERE o.poll_id=? ORDER BY o.sort_order, o.id',
            [$pollId]
        );
    }

    public static function getById(int $id): ?array
    {
        return Database::fetch('SELECT * FROM polls WHERE id=?', [$id]);
    }

    public static function create(int $familyId, int $userId, string $question, array $options): int
    {
        $pollId = Database::insert('INSERT INTO polls (family_id, user_id, question) VALUES (?,?,?)', [$familyId, $userId, $question]);
        $order = 0;
        foreach ($options as $label) {
            $label = trim((string)$label);
            if ($label === '') continue;
            Database::insert('INSERT INTO poll_options (poll_id, label, sort_order) VALUES (?,?,?)', [$pollId, $label, $order++]);
        }
        return $pollId;
    }

    public static function vote(int $pollId, int $optionId, int $userId): void
    {
        Database::execute(
            'INSERT INTO poll_votes (poll_id, option_id, user_id) VALUES (?,?,?)
             ON DUPLICATE KEY UPDATE option_id=VALUES(option_id), created_at=NOW()',
            [$pollId, $optionId, $userId]
        );
    }

    public static function close(int $id, bool $closed = true): void
    {
        Database::execute('UPDATE polls SET is_closed=? WHERE id=?', [$closed ? 1 : 0, $id]);
    }

    public static function delete(int $id): void
    {
        Database::execute('DELETE FROM polls WHERE id=?', [$id]);
    }

    public static function optionBelongsToPoll(int $optionId, int $pollId): bool
    {
        return (bool)Database::fetch('SELECT id FROM poll_options WHERE id=? AND poll_id=?', [$optionId, $pollId]);
    }

    /** Option avec le plus de votes (départage par le premier créé) — null si aucun vote. */
    public static function getWinningOption(int $pollId): ?array
    {
        $options = self::getOptions($pollId);
        $withVotes = array_filter($options, fn($o) => (int)$o['votes'] > 0);
        if (!$withVotes) return null;
        usort($withVotes, fn($a, $b) => $b['votes'] <=> $a['votes']);
        return $withVotes[0];
    }

    public static function markDecided(int $id, string $type): void
    {
        Database::execute('UPDATE polls SET decided_as=? WHERE id=?', [$type, $id]);
    }
}
