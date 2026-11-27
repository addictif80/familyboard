<?php
namespace App\Models;

use App\Core\Database;

/**
 * Partage d'un événement de calendrier avec une ou plusieurs familles amies. Toujours par
 * occurrence (jamais toute une série récurrente à la fois). Une fois l'invitation acceptée,
 * la famille invitée reçoit sa propre copie ("fork", une ligne `events` normale rattachée à
 * son family_id) qu'elle peut ensuite modifier ou supprimer librement de son côté — le lien
 * avec l'original ne sert plus qu'à propager les changements de la famille source sous forme
 * d'alertes à résoudre (jamais une répercussion automatique).
 */
class EventShare
{
    /** Champs de l'événement source copiés dans le fork initial, et suivis pour les alertes
     *  de modification — délibérément hors custody_schedule_ids/recurrence, propres à la
     *  famille qui les définit, jamais partagés tels quels. */
    private const SYNCED_FIELDS = [
        'title', 'description', 'start_datetime', 'end_datetime',
        'is_all_day', 'color', 'location', 'location_lat', 'location_lng', 'professional_name',
    ];

    /** Invite une ou plusieurs familles amies à participer à un événement déjà créé. */
    public static function invite(int $eventId, int $originFamilyId, array $targetFamilyIds, int $invitedBy): void
    {
        $event = Database::fetch('SELECT * FROM events WHERE id=? AND family_id=?', [$eventId, $originFamilyId]);
        if (!$event) return;

        foreach (array_unique(array_map('intval', $targetFamilyIds)) as $targetFamilyId) {
            if ($targetFamilyId === $originFamilyId) continue;
            if (!FamilyFriend::areFriends($originFamilyId, $targetFamilyId)) continue;

            $already = Database::fetch(
                "SELECT id FROM event_shares WHERE origin_event_id=? AND target_family_id=? AND ended_at IS NULL",
                [$eventId, $targetFamilyId]
            );
            if ($already) continue;

            Database::insert(
                'INSERT INTO event_shares (origin_event_id, origin_family_id, target_family_id, invited_by) VALUES (?,?,?,?)',
                [$eventId, $originFamilyId, $targetFamilyId, $invitedBy]
            );

            Notification::notifyFamily(
                $targetFamilyId, 0, 'event_share',
                'Invitation à un événement',
                'Une famille amie vous invite à participer à « ' . $event['title'] . ' ».',
                BASE_URL . '/calendar#share-invitations'
            );
        }
    }

    /** Invitations reçues, en attente de réponse. */
    public static function getPendingInvitations(int $familyId): array
    {
        return Database::fetchAll(
            "SELECT es.*, e.title, e.start_datetime, e.end_datetime, e.is_all_day, f.name as origin_family_name
             FROM event_shares es
             JOIN events e ON e.id = es.origin_event_id
             JOIN families f ON f.id = es.origin_family_id
             WHERE es.target_family_id=? AND es.status='pending'
             ORDER BY es.created_at DESC",
            [$familyId]
        );
    }

    public static function accept(int $shareId, int $respondingFamilyId, int $respondingUserId): bool
    {
        $share = Database::fetch('SELECT * FROM event_shares WHERE id=?', [$shareId]);
        if (!$share || (int)$share['target_family_id'] !== $respondingFamilyId || $share['status'] !== 'pending') return false;
        $origin = Database::fetch('SELECT * FROM events WHERE id=?', [$share['origin_event_id']]);
        if (!$origin) return false;

        $forkedId = Database::insert(
            'INSERT INTO events (family_id, user_id, title, description, start_datetime, end_datetime, is_all_day, color, location, location_lat, location_lng, professional_name, shared_from_family_id)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [
                $respondingFamilyId, $respondingUserId,
                $origin['title'], $origin['description'], $origin['start_datetime'], $origin['end_datetime'],
                $origin['is_all_day'], $origin['color'], $origin['location'], $origin['location_lat'], $origin['location_lng'],
                $origin['professional_name'], $share['origin_family_id'],
            ]
        );

        Database::execute(
            "UPDATE event_shares SET status='accepted', forked_event_id=?, responded_at=NOW() WHERE id=?",
            [$forkedId, $shareId]
        );

        Notification::notifyFamily(
            (int)$share['origin_family_id'], 0, 'event_share',
            'Invitation acceptée',
            'Une famille amie a accepté de participer à « ' . $origin['title'] . ' ».',
            BASE_URL . '/calendar'
        );
        return true;
    }

    public static function decline(int $shareId, int $respondingFamilyId): bool
    {
        $share = Database::fetch('SELECT * FROM event_shares WHERE id=?', [$shareId]);
        if (!$share || (int)$share['target_family_id'] !== $respondingFamilyId || $share['status'] !== 'pending') return false;
        Database::execute("UPDATE event_shares SET status='declined', ended_at=NOW(), responded_at=NOW() WHERE id=?", [$shareId]);
        return true;
    }

    /** Appelé après une modification réussie de l'événement source : propage une alerte de
     *  modification à chaque famille ayant accepté le partage, jamais une répercussion directe. */
    public static function propagateUpdate(int $eventId, array $newValues): void
    {
        $shares = Database::fetchAll(
            "SELECT * FROM event_shares WHERE origin_event_id=? AND status='accepted' AND ended_at IS NULL",
            [$eventId]
        );
        if (empty($shares)) return;

        $payload = array_intersect_key($newValues, array_flip(self::SYNCED_FIELDS));
        $event = Database::fetch('SELECT title FROM events WHERE id=?', [$eventId]);

        foreach ($shares as $share) {
            Database::insert(
                "INSERT INTO event_share_changes (event_share_id, change_type, payload) VALUES (?, 'update', ?)",
                [$share['id'], json_encode($payload)]
            );
            Notification::notifyFamily(
                (int)$share['target_family_id'], 0, 'event_share_change',
                'Événement partagé modifié',
                'La famille organisatrice a modifié « ' . ($event['title'] ?? '') . ' ». Une réponse est attendue de votre part.',
                BASE_URL . '/calendar#share-changes'
            );
        }
    }

    /** Appelé juste avant la suppression réelle de l'événement source. */
    public static function propagateDelete(int $eventId): void
    {
        // Invitations encore en attente : aucune copie n'a jamais été créée, donc rien à
        // notifier via event_share_changes — on les retire simplement pour ne pas laisser une
        // ligne dont origin_event_id pointe vers un événement qui n'existe plus (event_shares
        // n'a plus de contrainte de clé étrangère depuis add_family_sharing_no_fk.sql, donc rien
        // ne le ferait automatiquement).
        Database::execute(
            "UPDATE event_shares SET status='declined', ended_at=NOW() WHERE origin_event_id=? AND status='pending' AND ended_at IS NULL",
            [$eventId]
        );

        $shares = Database::fetchAll(
            "SELECT * FROM event_shares WHERE origin_event_id=? AND status='accepted' AND ended_at IS NULL",
            [$eventId]
        );
        if (empty($shares)) return;

        $event = Database::fetch('SELECT title FROM events WHERE id=?', [$eventId]);

        foreach ($shares as $share) {
            Database::insert(
                "INSERT INTO event_share_changes (event_share_id, change_type, payload) VALUES (?, 'delete', ?)",
                [$share['id'], json_encode(['title' => $event['title'] ?? ''])]
            );
            Database::execute('UPDATE event_shares SET origin_deleted_at=NOW() WHERE id=?', [$share['id']]);
            Notification::notifyFamily(
                (int)$share['target_family_id'], 0, 'event_share_change',
                'Événement partagé supprimé',
                'La famille organisatrice a supprimé « ' . ($event['title'] ?? '') . ' ». Une réponse est attendue de votre part.',
                BASE_URL . '/calendar#share-changes'
            );
        }
    }

    /** Changements en attente de résolution pour la famille invitée. */
    public static function getPendingChanges(int $familyId): array
    {
        return Database::fetchAll(
            "SELECT esc.*, es.forked_event_id, es.origin_family_id, f.name as origin_family_name
             FROM event_share_changes esc
             JOIN event_shares es ON es.id = esc.event_share_id
             JOIN families f ON f.id = es.origin_family_id
             WHERE es.target_family_id=? AND esc.status='pending'
             ORDER BY esc.created_at DESC",
            [$familyId]
        );
    }

    /** $decision : 'accept' | 'decline'. */
    public static function resolveChange(int $changeId, int $respondingFamilyId, string $decision, ?string $reason = null): bool
    {
        $change = Database::fetch(
            "SELECT esc.*, es.target_family_id, es.forked_event_id
             FROM event_share_changes esc JOIN event_shares es ON es.id = esc.event_share_id
             WHERE esc.id=?",
            [$changeId]
        );
        if (!$change || (int)$change['target_family_id'] !== $respondingFamilyId || $change['status'] !== 'pending') {
            return false;
        }

        if ($decision === 'accept') {
            if ($change['change_type'] === 'update') {
                $payload = json_decode($change['payload'] ?? '{}', true) ?: [];
                $fields = array_intersect_key($payload, array_flip(self::SYNCED_FIELDS));
                if ($fields && $change['forked_event_id']) {
                    $set = implode(', ', array_map(fn($f) => "`$f`=?", array_keys($fields)));
                    Database::execute(
                        "UPDATE events SET $set WHERE id=?",
                        [...array_values($fields), $change['forked_event_id']]
                    );
                }
            } elseif ($change['change_type'] === 'delete') {
                if ($change['forked_event_id']) {
                    Database::execute('DELETE FROM events WHERE id=?', [$change['forked_event_id']]);
                }
                Database::execute('UPDATE event_shares SET ended_at=NOW() WHERE id=?', [$change['event_share_id']]);
            }
            Database::execute("UPDATE event_share_changes SET status='accepted', resolved_at=NOW() WHERE id=?", [$changeId]);
        } else {
            if ($change['change_type'] === 'delete') {
                // Suppression refusée : la copie devient indépendante, plus aucune alerte à venir.
                Database::execute('UPDATE event_shares SET ended_at=NOW() WHERE id=?', [$change['event_share_id']]);
            }
            Database::execute(
                "UPDATE event_share_changes SET status='declined', decline_reason=?, resolved_at=NOW() WHERE id=?",
                [$reason ?: null, $changeId]
            );
        }
        return true;
    }

    /** Qui participe à cet événement (vue de la famille source) — invités, acceptés, refusés. */
    public static function getSharesForEvent(int $eventId): array
    {
        return Database::fetchAll(
            "SELECT es.*, f.name as target_family_name
             FROM event_shares es JOIN families f ON f.id = es.target_family_id
             WHERE es.origin_event_id=?
             ORDER BY es.created_at",
            [$eventId]
        );
    }

    /** Fin d'amitié entre deux familles : tous les partages d'événements entre elles
     *  disparaissent (invitations en attente, et copies déjà acceptées). */
    public static function removeForFamilyPair(int $familyIdA, int $familyIdB): void
    {
        $shares = Database::fetchAll(
            "SELECT * FROM event_shares
             WHERE (origin_family_id=? AND target_family_id=?) OR (origin_family_id=? AND target_family_id=?)",
            [$familyIdA, $familyIdB, $familyIdB, $familyIdA]
        );
        foreach ($shares as $share) {
            if (!empty($share['forked_event_id'])) {
                Database::execute('DELETE FROM events WHERE id=?', [$share['forked_event_id']]);
            }
        }
        Database::execute(
            "DELETE FROM event_shares
             WHERE (origin_family_id=? AND target_family_id=?) OR (origin_family_id=? AND target_family_id=?)",
            [$familyIdA, $familyIdB, $familyIdB, $familyIdA]
        );
    }
}
