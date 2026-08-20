<?php
namespace App\Models;

use App\Core\Database;

/**
 * Minuteurs prédéfinis par l'admin de famille (ex. "Machine à laver" / 40 min), démarrables
 * d'un bouton sur l'écran mural ou le kiosque. Le "temps écoulé" (alarme) n'est jamais un statut
 * écrit en base : chaque affichage le déduit lui-même de ends_at, ce qui évite d'avoir besoin
 * d'un cron pour faire la transition et garde plusieurs écrans synchronisés sans effort.
 */
class FamilyTimer
{
    public static function getByFamily(int $familyId): array
    {
        return Database::fetchAll(
            'SELECT * FROM family_timers WHERE family_id=? ORDER BY created_at ASC',
            [$familyId]
        );
    }

    /** Minuteurs cochés "afficher sur l'écran mural", chacun avec son run actif s'il y en a un. */
    public static function getForWall(int $familyId): array
    {
        return Database::fetchAll(
            'SELECT t.*, r.id as run_id, r.ends_at, r.started_at
             FROM family_timers t
             LEFT JOIN family_timer_runs r ON r.timer_id = t.id AND r.status = "running"
             WHERE t.family_id=? AND t.show_on_wall=1
             ORDER BY t.created_at ASC',
            [$familyId]
        );
    }

    public static function create(int $familyId, string $label, int $durationMinutes, bool $showOnWall, int $userId): int
    {
        return Database::insert(
            'INSERT INTO family_timers (family_id, label, duration_minutes, show_on_wall, created_by) VALUES (?,?,?,?,?)',
            [$familyId, $label, $durationMinutes, $showOnWall ? 1 : 0, $userId]
        );
    }

    public static function delete(int $id, int $familyId): void
    {
        Database::execute('DELETE FROM family_timers WHERE id=? AND family_id=?', [$id, $familyId]);
    }

    /** Démarre un minuteur — refusé s'il tourne déjà (pas de double lancement concurrent). */
    public static function start(int $timerId, int $familyId, int $userId): ?int
    {
        $timer = Database::fetch('SELECT * FROM family_timers WHERE id=? AND family_id=?', [$timerId, $familyId]);
        if (!$timer) return null;

        $running = Database::fetch(
            "SELECT id FROM family_timer_runs WHERE timer_id=? AND status='running'",
            [$timerId]
        );
        if ($running) return null;

        return Database::insert(
            'INSERT INTO family_timer_runs (timer_id, family_id, started_by, ends_at)
             VALUES (?,?,?, DATE_ADD(NOW(), INTERVAL ? MINUTE))',
            [$timerId, $familyId, $userId, (int)$timer['duration_minutes']]
        );
    }

    /** Arrête le run actif d'un minuteur (identifié par le minuteur, pas par l'id du run — plus
     *  simple côté appelant, qui n'a besoin de connaître que l'id du minuteur affiché). */
    public static function stop(int $timerId, int $familyId): void
    {
        Database::execute(
            "UPDATE family_timer_runs SET status='stopped', stopped_at=NOW() WHERE timer_id=? AND family_id=? AND status='running'",
            [$timerId, $familyId]
        );
    }
}
