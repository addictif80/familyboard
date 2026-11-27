<?php
namespace App\Models;

use App\Core\Database;

/** Abonnements entre membres FamilyBoard — au sein d'une même famille, ou entre deux familles
 *  amies (scope "Amis" du mur social) — jamais avec un compte co-parent, ni entre deux familles
 *  qui ne sont pas amies (voir FollowController::validTarget, seul point de contrôle). */
class Follow
{
    public static function status(int $followerId, int $followeeId): ?string
    {
        $row = Database::fetch('SELECT status FROM follows WHERE follower_id=? AND followee_id=?', [$followerId, $followeeId]);
        return $row['status'] ?? null;
    }

    public static function isAccepted(int $followerId, int $followeeId): bool
    {
        return self::status($followerId, $followeeId) === 'accepted';
    }

    /** Vrai si $a et $b se suivent mutuellement (condition requise pour un DM). */
    public static function isMutual(int $a, int $b): bool
    {
        return self::isAccepted($a, $b) && self::isAccepted($b, $a);
    }

    public static function request(int $followerId, int $followeeId): void
    {
        Database::execute(
            'INSERT INTO follows (follower_id, followee_id, status) VALUES (?,?,\'pending\')
             ON DUPLICATE KEY UPDATE status=IF(status=\'accepted\',status,\'pending\')',
            [$followerId, $followeeId]
        );
    }

    public static function accept(int $followerId, int $followeeId): void
    {
        Database::execute(
            "UPDATE follows SET status='accepted', responded_at=NOW() WHERE follower_id=? AND followee_id=?",
            [$followerId, $followeeId]
        );
    }

    /** Retire la relation, que ce soit un refus de demande, un retrait d'abonnement ou un blocage simple. */
    public static function remove(int $followerId, int $followeeId): void
    {
        Database::execute('DELETE FROM follows WHERE follower_id=? AND followee_id=?', [$followerId, $followeeId]);
    }

    public static function getFollowing(int $userId, string $status = 'accepted'): array
    {
        return Database::fetchAll(
            'SELECT f.*, u.name, u.avatar, u.color, u.family_id, fam.name as family_name
             FROM follows f JOIN users u ON u.id=f.followee_id JOIN families fam ON fam.id=u.family_id
             WHERE f.follower_id=? AND f.status=? ORDER BY u.name',
            [$userId, $status]
        );
    }

    public static function getFollowers(int $userId, string $status = 'accepted'): array
    {
        return Database::fetchAll(
            'SELECT f.*, u.name, u.avatar, u.color, u.family_id, fam.name as family_name
             FROM follows f JOIN users u ON u.id=f.follower_id JOIN families fam ON fam.id=u.family_id
             WHERE f.followee_id=? AND f.status=? ORDER BY u.name',
            [$userId, $status]
        );
    }

    /** Demandes de suivi en attente à valider PAR $userId (il est la personne visée). */
    public static function getPendingForApproval(int $userId): array
    {
        return self::getFollowers($userId, 'pending');
    }

    /** Ensemble des followee_id dont les publications personnelles sont visibles par $userId
     *  (lui-même inclus, pour toujours voir ses propres publications). */
    public static function getVisibleAuthorIds(int $userId): array
    {
        $ids = array_map(fn($f) => (int)$f['followee_id'], self::getFollowing($userId));
        $ids[] = $userId;
        return array_values(array_unique($ids));
    }
}
