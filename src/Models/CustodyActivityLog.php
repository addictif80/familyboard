<?php
namespace App\Models;

use App\Core\Database;

/**
 * Journal d'activité par planning de garde. Volontairement inactif tant
 * qu'aucun co-parent invité ne s'est connecté au moins une fois (voir
 * activate()) — une fois actif pour un planning, toute action des deux
 * côtés (parent utilisateur ET co-parent) y est journalisée avec horodatage
 * et IP. Immuable : pas de update()/delete().
 */
class CustodyActivityLog
{
    public const LABELS = [
        'logging_activated'     => "Journalisation activée (première connexion du co-parent)",
        'schedule_updated'      => 'Planning modifié',
        'schedule_deleted'      => 'Planning supprimé',
        'custody_event_created' => 'Période de garde ajoutée',
        'custody_event_updated' => 'Période de garde modifiée',
        'custody_event_deleted' => 'Période de garde supprimée',
        'proposal_sent'         => 'Proposition de garde envoyée',
        'proposal_applied'      => 'Proposition de garde appliquée',
        'vacation_created'      => 'Période de vacances ajoutée',
        'vacation_updated'      => 'Période de vacances modifiée',
        'vacation_deleted'      => 'Période de vacances supprimée',
        'journal_message_sent'  => 'Message envoyé dans le journal parental',
        'document_uploaded'     => 'Document ajouté',
        'document_updated'      => 'Document modifié',
        'event_created'         => 'Évènement de calendrier ajouté',
        'event_updated'         => 'Évènement de calendrier modifié',
        'notification_sent'     => 'Notification envoyée au co-parent',
    ];

    public static function isActiveForSchedule(int $scheduleId): bool
    {
        return (bool)Database::fetch(
            'SELECT 1 FROM custody_access WHERE schedule_id=? AND first_login_at IS NOT NULL LIMIT 1',
            [$scheduleId]
        );
    }

    /**
     * Marque la première connexion d'un co-parent pour les plannings donnés et,
     * la toute première fois, journalise l'activation elle-même.
     */
    public static function activate(int $userId, array $scheduleIds): void
    {
        foreach (array_unique(array_map('intval', $scheduleIds)) as $scheduleId) {
            $row = Database::fetch(
                'SELECT first_login_at FROM custody_access WHERE user_id=? AND schedule_id=?',
                [$userId, $scheduleId]
            );
            if (!$row || $row['first_login_at'] !== null) continue;

            Database::execute(
                'UPDATE custody_access SET first_login_at=NOW() WHERE user_id=? AND schedule_id=?',
                [$userId, $scheduleId]
            );
            self::record($scheduleId, $userId, 'logging_activated');
        }
    }

    /**
     * Journalise une action. Capture au vol family_id/child_name depuis le planning
     * (doit donc être appelé AVANT une éventuelle suppression du planning) pour que
     * l'entrée reste lisible même si le planning est supprimé par la suite — le
     * journal n'a volontairement aucune clé étrangère vers custody_schedules.
     */
    public static function record(int $scheduleId, ?int $userId, string $action, ?string $details = null): void
    {
        // 'logging_activated' est écrit par activate() juste après avoir posé le
        // flag : il doit donc contourner ce garde-fou (qui serait sinon faux pour
        // cette toute première écriture).
        if ($action !== 'logging_activated' && !self::isActiveForSchedule($scheduleId)) return;

        $schedule = Custody::getScheduleById($scheduleId);

        Database::insert(
            'INSERT INTO custody_activity_log (schedule_id, family_id, child_name, user_id, action, details, ip) VALUES (?,?,?,?,?,?,?)',
            [
                $scheduleId,
                $schedule['family_id'] ?? 0,
                $schedule['child_name'] ?? null,
                $userId, $action, $details,
                $_SERVER['REMOTE_ADDR'] ?? null,
            ]
        );
    }

    public static function getForSchedule(int $scheduleId, int $limit = 300): array
    {
        return Database::fetchAll(
            'SELECT l.*, u.name as user_name, u.color as user_color
             FROM custody_activity_log l
             LEFT JOIN users u ON u.id = l.user_id
             WHERE l.schedule_id=?
             ORDER BY l.created_at DESC
             LIMIT ?',
            [$scheduleId, $limit]
        );
    }

    public static function labelFor(string $action): string
    {
        return self::LABELS[$action] ?? $action;
    }
}
