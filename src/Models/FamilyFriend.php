<?php
namespace App\Models;

use App\Core\Database;

/**
 * Relation "famille amie" entre deux familles, établie via le code famille (comme une
 * invitation de membre, mais pour lier deux familles plutôt que d'en rejoindre une) — toujours
 * soumise à acceptation par la famille invitée. Une fois amies, la famille A peut sélectionner
 * la famille B directement à la création d'un nouvel événement (voir EventShare).
 */
class FamilyFriend
{
    /** Envoie une demande via le code famille de la famille cible. */
    public static function requestByCode(int $requesterFamilyId, int $requestedBy, string $code): array
    {
        $target = Family::findByInviteCode(strtoupper(trim($code)));
        if (!$target) {
            return ['success' => false, 'error' => 'Code famille introuvable.'];
        }
        if ((int)$target['id'] === $requesterFamilyId) {
            return ['success' => false, 'error' => 'Vous ne pouvez pas vous ajouter vous-même.'];
        }

        $existing = self::findPair($requesterFamilyId, (int)$target['id']);
        if ($existing) {
            if ($existing['status'] === 'accepted') {
                return ['success' => false, 'error' => 'Ces familles sont déjà amies.'];
            }
            if ($existing['status'] === 'pending') {
                return ['success' => false, 'error' => 'Une demande est déjà en attente entre ces familles.'];
            }
            // Précédemment refusée : on relance une nouvelle demande.
            Database::execute(
                'UPDATE family_friends SET requester_family_id=?, target_family_id=?, status=?, requested_by=?, created_at=NOW(), responded_at=NULL WHERE id=?',
                [$requesterFamilyId, $target['id'], 'pending', $requestedBy, $existing['id']]
            );
            $id = (int)$existing['id'];
        } else {
            $id = Database::insert(
                'INSERT INTO family_friends (requester_family_id, target_family_id, requested_by) VALUES (?,?,?)',
                [$requesterFamilyId, $target['id'], $requestedBy]
            );
        }

        Notification::notifyFamily(
            (int)$target['id'], 0, 'family_friend',
            'Demande de mise en relation',
            'Une famille souhaite devenir famille amie avec vous.',
            BASE_URL . '/settings#friends'
        );

        return ['success' => true, 'id' => $id];
    }

    /** Ligne existante entre ces deux familles, quel que soit le sens de la demande. */
    public static function findPair(int $familyIdA, int $familyIdB): ?array
    {
        return Database::fetch(
            'SELECT * FROM family_friends
             WHERE (requester_family_id=? AND target_family_id=?) OR (requester_family_id=? AND target_family_id=?)',
            [$familyIdA, $familyIdB, $familyIdB, $familyIdA]
        );
    }

    public static function areFriends(int $familyIdA, int $familyIdB): bool
    {
        $row = self::findPair($familyIdA, $familyIdB);
        return (bool)($row && $row['status'] === 'accepted');
    }

    /** Familles amies acceptées (pour la case à cocher à la création d'un événement). */
    public static function getAcceptedFor(int $familyId): array
    {
        return Database::fetchAll(
            "SELECT ff.id, f.id as family_id, f.name as family_name
             FROM family_friends ff
             JOIN families f ON f.id = IF(ff.requester_family_id=?, ff.target_family_id, ff.requester_family_id)
             WHERE (ff.requester_family_id=? OR ff.target_family_id=?) AND ff.status='accepted'
             ORDER BY f.name",
            [$familyId, $familyId, $familyId]
        );
    }

    /** Demandes reçues, en attente de réponse. */
    public static function getPendingIncoming(int $familyId): array
    {
        return Database::fetchAll(
            "SELECT ff.*, f.name as requester_family_name
             FROM family_friends ff JOIN families f ON f.id = ff.requester_family_id
             WHERE ff.target_family_id=? AND ff.status='pending'
             ORDER BY ff.created_at DESC",
            [$familyId]
        );
    }

    /** Demandes envoyées, encore en attente. */
    public static function getPendingOutgoing(int $familyId): array
    {
        return Database::fetchAll(
            "SELECT ff.*, f.name as target_family_name
             FROM family_friends ff JOIN families f ON f.id = ff.target_family_id
             WHERE ff.requester_family_id=? AND ff.status='pending'
             ORDER BY ff.created_at DESC",
            [$familyId]
        );
    }

    public static function accept(int $id, int $respondingFamilyId): bool
    {
        $row = Database::fetch('SELECT * FROM family_friends WHERE id=?', [$id]);
        if (!$row || (int)$row['target_family_id'] !== $respondingFamilyId || $row['status'] !== 'pending') return false;
        Database::execute("UPDATE family_friends SET status='accepted', responded_at=NOW() WHERE id=?", [$id]);
        Notification::notifyFamily(
            (int)$row['requester_family_id'], 0, 'family_friend',
            'Demande acceptée',
            'Votre demande de mise en relation a été acceptée.',
            BASE_URL . '/settings#friends'
        );
        return true;
    }

    public static function decline(int $id, int $respondingFamilyId): bool
    {
        $row = Database::fetch('SELECT * FROM family_friends WHERE id=?', [$id]);
        if (!$row || (int)$row['target_family_id'] !== $respondingFamilyId || $row['status'] !== 'pending') return false;
        Database::execute("UPDATE family_friends SET status='declined', responded_at=NOW() WHERE id=?", [$id]);
        return true;
    }

    /** Met fin à une amitié (par l'une ou l'autre famille) — les événements déjà partagés
     *  disparaissent (voir EventShare::removeForFamilyPair()). */
    public static function remove(int $id, int $familyId): bool
    {
        $row = Database::fetch('SELECT * FROM family_friends WHERE id=?', [$id]);
        if (!$row || ((int)$row['requester_family_id'] !== $familyId && (int)$row['target_family_id'] !== $familyId)) {
            return false;
        }
        $otherFamilyId = (int)$row['requester_family_id'] === $familyId ? (int)$row['target_family_id'] : (int)$row['requester_family_id'];
        \App\Models\EventShare::removeForFamilyPair($familyId, $otherFamilyId);
        Database::execute('DELETE FROM family_friends WHERE id=?', [$id]);
        return true;
    }
}
