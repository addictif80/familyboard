<?php
namespace App\Models;

use App\Core\Database;

class Post
{
    public static function getByFamily(int $familyId, int $limit = 20, int $offset = 0): array
    {
        $posts = Database::fetchAll(
            'SELECT p.*, u.name as user_name, u.avatar as user_avatar, u.color as user_color,
             (SELECT COUNT(*) FROM post_reactions pr WHERE pr.post_id=p.id) as reaction_count,
             (SELECT COUNT(*) FROM post_comments pc WHERE pc.post_id=p.id) as comment_count
             FROM posts p JOIN users u ON u.id=p.user_id
             WHERE p.family_id=? ORDER BY p.created_at DESC LIMIT ? OFFSET ?',
            [$familyId, $limit, $offset]
        );
        return $posts;
    }

    public static function getById(int $id): ?array
    {
        return Database::fetch(
            'SELECT p.*, u.name as user_name, u.avatar as user_avatar, u.color as user_color FROM posts p JOIN users u ON u.id=p.user_id WHERE p.id=?',
            [$id]
        );
    }

    public static function create(int $familyId, int $userId, string $content = '', ?string $imagePath = null): int
    {
        return Database::insert(
            'INSERT INTO posts (family_id, user_id, content, image_path) VALUES (?,?,?,?)',
            [$familyId, $userId, $content, $imagePath]
        );
    }

    public static function delete(int $id): void
    {
        Database::execute('DELETE FROM posts WHERE id=?', [$id]);
    }

    public static function getComments(int $postId): array
    {
        return Database::fetchAll(
            'SELECT c.*, u.name as user_name, u.avatar as user_avatar, u.color as user_color FROM post_comments c JOIN users u ON u.id=c.user_id WHERE c.post_id=? ORDER BY c.created_at',
            [$postId]
        );
    }

    public static function addComment(int $postId, int $userId, string $content): int
    {
        return Database::insert('INSERT INTO post_comments (post_id, user_id, content) VALUES (?,?,?)', [$postId, $userId, $content]);
    }

    public static function deleteComment(int $id): void
    {
        Database::execute('DELETE FROM post_comments WHERE id=?', [$id]);
    }

    public static function getReactions(int $postId): array
    {
        return Database::fetchAll(
            'SELECT r.*, u.name as user_name FROM post_reactions r JOIN users u ON u.id=r.user_id WHERE r.post_id=?',
            [$postId]
        );
    }

    public static function toggleReaction(int $postId, int $userId, string $emoji = '❤️'): string
    {
        $existing = Database::fetch('SELECT id FROM post_reactions WHERE post_id=? AND user_id=?', [$postId, $userId]);
        if ($existing) {
            Database::execute('DELETE FROM post_reactions WHERE post_id=? AND user_id=?', [$postId, $userId]);
            return 'removed';
        }
        Database::insert('INSERT INTO post_reactions (post_id, user_id, emoji) VALUES (?,?,?)', [$postId, $userId, $emoji]);
        return 'added';
    }

    public static function getUserReaction(int $postId, int $userId): ?array
    {
        return Database::fetch('SELECT * FROM post_reactions WHERE post_id=? AND user_id=?', [$postId, $userId]);
    }
}
