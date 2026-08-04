<?php
namespace App\Models;

use App\Core\Database;

class Post
{
    /**
     * Fil visible par $viewerId : les publications "famille" publiées, plus les publications
     * personnelles publiées de tout auteur que $viewerId suit (ou lui-même). La jointure sur
     * une liste d'ids passée en paramètre (jamais construite par concaténation de valeurs
     * utilisateur) reste une requête préparée standard.
     */
    public static function getVisibleForUser(int $familyId, int $viewerId, array $visibleAuthorIds, int $limit = 20, int $offset = 0): array
    {
        $authorIds = array_values(array_unique(array_map('intval', $visibleAuthorIds)));
        $ph = $authorIds ? implode(',', array_fill(0, count($authorIds), '?')) : '0';
        return Database::fetchAll(
            "SELECT p.*, u.name as user_name, u.avatar as user_avatar, u.color as user_color,
             (SELECT COUNT(*) FROM post_reactions pr WHERE pr.post_id=p.id) as reaction_count,
             (SELECT COUNT(*) FROM post_comments pc WHERE pc.post_id=p.id) as comment_count
             FROM posts p JOIN users u ON u.id=p.user_id
             WHERE p.family_id=? AND p.status='published'
               AND (p.post_type='family' OR (p.post_type='personal' AND p.user_id IN ($ph)))
             ORDER BY p.created_at DESC LIMIT ? OFFSET ?",
            [$familyId, ...$authorIds, $limit, $offset]
        );
    }

    /** Comme getVisibleForUser(), avec un filtre texte en plus — pour la recherche globale.
     *  Réutilise exactement les mêmes conditions de visibilité, jamais une variante allégée. */
    public static function searchVisible(int $familyId, int $viewerId, array $visibleAuthorIds, string $query, int $limit = 5): array
    {
        $authorIds = array_values(array_unique(array_map('intval', $visibleAuthorIds)));
        $ph = $authorIds ? implode(',', array_fill(0, count($authorIds), '?')) : '0';
        return Database::fetchAll(
            "SELECT p.*, u.name as user_name
             FROM posts p JOIN users u ON u.id=p.user_id
             WHERE p.family_id=? AND p.status='published'
               AND (p.post_type='family' OR (p.post_type='personal' AND p.user_id IN ($ph)))
               AND p.content LIKE ?
             ORDER BY p.created_at DESC LIMIT ?",
            [$familyId, ...$authorIds, $query, $limit]
        );
    }

    /** File d'attente des publications "famille" proposées par un membre, à valider par l'admin. */
    public static function getPendingByFamily(int $familyId): array
    {
        return Database::fetchAll(
            "SELECT p.*, u.name as user_name, u.avatar as user_avatar, u.color as user_color
             FROM posts p JOIN users u ON u.id=p.user_id
             WHERE p.family_id=? AND p.post_type='family' AND p.status='pending'
             ORDER BY p.created_at ASC",
            [$familyId]
        );
    }

    public static function getById(int $id): ?array
    {
        return Database::fetch(
            'SELECT p.*, u.name as user_name, u.avatar as user_avatar, u.color as user_color FROM posts p JOIN users u ON u.id=p.user_id WHERE p.id=?',
            [$id]
        );
    }

    /** True si $viewerId peut voir cette publication (déjà chargée) selon les règles de visibilité. */
    public static function isVisibleTo(array $post, int $viewerId, array $visibleAuthorIds): bool
    {
        if ($post['status'] !== 'published') return (int)$post['user_id'] === $viewerId;
        if ($post['post_type'] === 'family') return true;
        return in_array((int)$post['user_id'], $visibleAuthorIds, true);
    }

    public static function create(int $familyId, int $userId, string $content, ?string $imagePath, string $postType, string $status): int
    {
        return Database::insert(
            'INSERT INTO posts (family_id, user_id, content, image_path, post_type, status) VALUES (?,?,?,?,?,?)',
            [$familyId, $userId, $content, $imagePath, $postType, $status]
        );
    }

    public static function approve(int $id, int $reviewerId): void
    {
        Database::execute("UPDATE posts SET status='published', reviewed_by=?, reviewed_at=NOW() WHERE id=?", [$reviewerId, $id]);
    }

    public static function reject(int $id, int $reviewerId): void
    {
        Database::execute("UPDATE posts SET status='rejected', reviewed_by=?, reviewed_at=NOW() WHERE id=?", [$reviewerId, $id]);
    }

    public static function update(int $id, string $content): void
    {
        Database::execute('UPDATE posts SET content=? WHERE id=?', [$content, $id]);
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
