<?php
namespace App\Models;

use App\Core\AiProvider;
use App\Core\Database;

/**
 * Digest hebdomadaire généré par l'IA : un texte court et chaleureux sur la semaine à venir,
 * affiché en popup au prochain login/lancement de la PWA (voir DashboardController::index()).
 * Généré une fois par famille et par semaine par cron.php (jamais à la demande dans une requête
 * web — un appel IA est trop lent/coûteux pour bloquer un chargement de page), à partir d'un
 * résumé déterministe du contexte familial (même esprit que AiAssistant::buildContextSummary()).
 */
class AiWeeklyDigest
{
    private const SYSTEM_PROMPT = <<<'TXT'
Tu rédiges le digest hebdomadaire de l'application familiale FamilyBoard, à partir des données
ci-dessous. Écris en français, 4 à 8 lignes maximum, ton chaleureux et concret (pas de formules
creuses type "voici un récapitulatif"). Mets en avant 2-3 points qui méritent vraiment l'attention
de la famille cette semaine (un jour chargé, une échéance, un anniversaire...). Pas de titre, pas
de salutation, va droit au but. N'invente aucune information absente du contexte fourni.
TXT;

    public static function currentWeekStart(): string
    {
        return date('Y-m-d', strtotime('monday this week'));
    }

    public static function getForWeek(int $familyId, string $weekStart): ?array
    {
        return Database::fetch(
            'SELECT * FROM ai_weekly_digests WHERE family_id=? AND week_start=?',
            [$familyId, $weekStart]
        );
    }

    /** Digest de la semaine en cours si l'utilisateur ne l'a pas encore vu, sinon null. */
    public static function getUnseenForUser(int $familyId, int $userId): ?array
    {
        $weekStart = self::currentWeekStart();
        $digest = self::getForWeek($familyId, $weekStart);
        if (!$digest) return null;

        $user = Database::fetch('SELECT last_digest_week_seen FROM users WHERE id=?', [$userId]);
        if ($user && $user['last_digest_week_seen'] === $weekStart) return null;

        return $digest;
    }

    public static function markSeen(int $userId, string $weekStart): void
    {
        Database::execute('UPDATE users SET last_digest_week_seen=? WHERE id=?', [$weekStart, $userId]);
    }

    /** Génère (ou régénère) le digest de la semaine en cours pour une famille. Appelé par cron.php
     *  uniquement — silencieux si l'IA n'est pas configurée ou si l'appel échoue (pas de digest
     *  plutôt qu'une erreur qui bloquerait le reste du cron). */
    public static function generate(int $familyId): bool
    {
        if (!AiProvider::isConfigured()) return false;

        $weekStart = self::currentWeekStart();
        $context = self::buildContext($familyId, $weekStart);

        $result = AiProvider::chat([
            ['role' => 'system', 'content' => self::SYSTEM_PROMPT],
            ['role' => 'user', 'content' => $context],
        ]);
        if (!$result['ok'] || trim($result['content'] ?? '') === '') return false;

        Database::execute(
            'INSERT INTO ai_weekly_digests (family_id, week_start, content, generated_at)
             VALUES (?,?,?,NOW())
             ON DUPLICATE KEY UPDATE content=VALUES(content), generated_at=NOW()',
            [$familyId, $weekStart, trim($result['content'])]
        );
        return true;
    }

    private static function buildContext(int $familyId, string $weekStart): string
    {
        $tz = new \DateTimeZone(Family::getTimezone($familyId));
        $weekEndDt = (new \DateTime($weekStart, $tz))->modify('+6 days');
        $weekEnd = $weekEndDt->format('Y-m-d');

        $lines = ['Semaine du ' . (new \DateTime($weekStart))->format('d/m') . ' au ' . $weekEndDt->format('d/m/Y') . '.'];

        $events = Database::fetchAll(
            'SELECT title, start_datetime, is_all_day FROM events
             WHERE family_id=? AND start_datetime BETWEEN ? AND ? ORDER BY start_datetime LIMIT 20',
            [$familyId, $weekStart . ' 00:00:00', $weekEnd . ' 23:59:59']
        );
        if ($events) {
            $lines[] = 'Événements prévus :';
            foreach ($events as $e) {
                $when = $e['is_all_day'] ? (new \DateTime($e['start_datetime']))->format('D d/m') : (new \DateTime($e['start_datetime']))->format('D d/m H:i');
                $lines[] = '- ' . $when . ' : ' . $e['title'];
            }
        } else {
            $lines[] = 'Aucun événement prévu cette semaine.';
        }

        $pendingTasks = 0;
        foreach (TaskList::getByFamily($familyId) as $list) {
            if ($list['type'] === 'shopping') continue;
            foreach (TaskList::getTasks((int)$list['id']) as $t) {
                if (!$t['is_completed']) $pendingTasks++;
            }
        }
        $lines[] = $pendingTasks . ' tâche(s) en attente (toutes listes confondues, hors courses).';

        try {
            $balance = Budget::getSummary($familyId, date('Y-m'));
            $lines[] = 'Solde budget du mois en cours : ' . number_format((float)($balance['balance'] ?? 0), 2, ',', ' ') . ' €.';
        } catch (\Throwable) {
            // Module budget non activé/disponible — ignorer silencieusement.
        }

        $birthdays = array_filter(Birthday::getUpcoming($familyId, 7), fn($b) => ($b['days_until'] ?? 99) <= 6);
        if ($birthdays) {
            $lines[] = 'Anniversaires à venir : ' . implode(', ', array_map(
                fn($b) => $b['name'] . ' (' . ($b['days_until'] === 0 ? "aujourd'hui" : 'dans ' . $b['days_until'] . ' j') . ')',
                $birthdays
            )) . '.';
        }

        try {
            $family = Family::findById($familyId);
            $zone = $family['school_zone'] ?? null;
            if ($zone) {
                $holidays = SchoolHoliday::getByZone($zone, $weekStart, $weekEnd);
                if ($holidays) {
                    $lines[] = 'Vacances scolaires concernant cette semaine : ' . implode(', ', array_map(
                        fn($h) => $h['description'], $holidays
                    )) . '.';
                }
            }
        } catch (\Throwable) {
            // Idem — fonctionnalité optionnelle, ne doit jamais faire échouer le digest.
        }

        return implode("\n", $lines);
    }
}
