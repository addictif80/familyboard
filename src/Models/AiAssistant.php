<?php
namespace App\Models;

use App\Core\Database;

/**
 * Orchestration de l'assistant IA : définit le jeu d'outils whitelistés (jamais un accès libre
 * à la base — voir la discussion produit : un modèle local peut halluciner, donc pas d'actions
 * destructrices en v1, uniquement de la création sur tâches/calendrier/courses) et exécute les
 * appels d'outils renvoyés par Ollama après validation stricte des arguments.
 */
class AiAssistant
{
    public const TOOLS = [
        [
            'type' => 'function',
            'function' => [
                'name' => 'add_task',
                'description' => "Ajoute une tâche à la liste de tâches de la famille.",
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'title' => ['type' => 'string', 'description' => 'Intitulé court de la tâche.'],
                        'due_date' => ['type' => 'string', 'description' => "Date d'échéance au format AAAA-MM-JJ, si mentionnée. Sinon, ne pas inclure ce champ."],
                        'priority' => ['type' => 'string', 'enum' => ['low', 'medium', 'high'], 'description' => 'Priorité, si mentionnée.'],
                    ],
                    'required' => ['title'],
                ],
            ],
        ],
        [
            'type' => 'function',
            'function' => [
                'name' => 'add_shopping_item',
                'description' => "Ajoute un article à la liste de courses de la famille.",
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'title' => ['type' => 'string', 'description' => "Nom de l'article à acheter."],
                    ],
                    'required' => ['title'],
                ],
            ],
        ],
        [
            'type' => 'function',
            'function' => [
                'name' => 'add_calendar_event',
                'description' => "Ajoute un événement au calendrier familial.",
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'title' => ['type' => 'string', 'description' => "Titre de l'événement."],
                        'start_datetime' => ['type' => 'string', 'description' => "Date et heure de début au format AAAA-MM-JJ HH:MM."],
                        'end_datetime' => ['type' => 'string', 'description' => "Date et heure de fin au format AAAA-MM-JJ HH:MM, si mentionnée. Sinon, une heure après le début."],
                        'description' => ['type' => 'string', 'description' => "Détails supplémentaires, si mentionnés."],
                    ],
                    'required' => ['title', 'start_datetime'],
                ],
            ],
        ],
    ];

    /** Résumé en lecture seule du contexte familial, injecté dans le prompt système pour que
     *  l'assistant puisse répondre à des questions ("qu'est-ce qu'on a prévu ce soir ?") sans
     *  jamais recevoir un accès direct à la base — uniquement ce texte, volontairement compact
     *  (un modèle local a un contexte réduit, pas la peine de le noyer sous du détail). */
    public static function buildContextSummary(int $familyId): string
    {
        $tz = new \DateTimeZone(Family::getTimezone($familyId));
        $now = new \DateTime('now', $tz);
        $in14days = (clone $now)->modify('+14 days');

        $lines = ['Date et heure actuelles : ' . $now->format('l d/m/Y H:i')];

        $events = Database::fetchAll(
            'SELECT title, start_datetime FROM events WHERE family_id=? AND start_datetime BETWEEN ? AND ? ORDER BY start_datetime LIMIT 15',
            [$familyId, $now->format('Y-m-d H:i:s'), $in14days->format('Y-m-d H:i:s')]
        );
        if ($events) {
            $lines[] = "Événements des 14 prochains jours :";
            foreach ($events as $e) {
                $lines[] = '- ' . (new \DateTime($e['start_datetime']))->format('d/m H:i') . ' : ' . $e['title'];
            }
        }

        $tasks = Database::fetchAll(
            "SELECT t.title, t.due_date FROM tasks t JOIN task_lists tl ON tl.id=t.list_id
             WHERE tl.family_id=? AND tl.type='tasks' AND t.is_completed=0 ORDER BY t.due_date IS NULL, t.due_date LIMIT 15",
            [$familyId]
        );
        if ($tasks) {
            $lines[] = "Tâches en cours :";
            foreach ($tasks as $t) {
                $lines[] = '- ' . $t['title'] . ($t['due_date'] ? ' (pour le ' . (new \DateTime($t['due_date']))->format('d/m') . ')' : '');
            }
        }

        $shopping = Database::fetchAll(
            "SELECT t.title FROM tasks t JOIN task_lists tl ON tl.id=t.list_id
             WHERE tl.family_id=? AND tl.type='shopping' AND t.is_completed=0 ORDER BY t.created_at LIMIT 25",
            [$familyId]
        );
        if ($shopping) {
            $lines[] = "Liste de courses actuelle : " . implode(', ', array_column($shopping, 'title'));
        }

        return implode("\n", $lines);
    }

    /**
     * Exécute un appel d'outil après validation stricte des arguments — retourne
     * ['ok'=>bool, 'summary'=>string] où summary est le texte de confirmation déterministe
     * renvoyé à l'utilisateur (jamais généré par le modèle lui-même, pour ne jamais afficher une
     * confirmation qui ne correspond pas à ce qui a réellement été créé en base).
     */
    public static function executeTool(string $name, array $args, int $familyId, int $userId): array
    {
        return match ($name) {
            'add_task' => self::doAddTask($args, $familyId, $userId),
            'add_shopping_item' => self::doAddShoppingItem($args, $familyId, $userId),
            'add_calendar_event' => self::doAddCalendarEvent($args, $familyId, $userId),
            default => ['ok' => false, 'summary' => "Action inconnue : $name."],
        };
    }

    private static function doAddTask(array $args, int $familyId, int $userId): array
    {
        $title = trim((string)($args['title'] ?? ''));
        if ($title === '') return ['ok' => false, 'summary' => "Titre de tâche manquant."];
        $dueDate = $args['due_date'] ?? null;
        $dueDate = (is_string($dueDate) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dueDate)) ? $dueDate : null;
        $priority = in_array($args['priority'] ?? '', ['low', 'medium', 'high'], true) ? $args['priority'] : 'medium';

        $listId = TaskList::findOrCreateDefaultList($familyId, $userId, 'tasks');
        $taskId = TaskList::createTask($listId, $userId, ['title' => $title, 'due_date' => $dueDate, 'priority' => $priority]);
        AiAssistantAction::log($familyId, $userId, 'add_task', "Tâche ajoutée : « $title »" . ($dueDate ? ' (pour le ' . (new \DateTime($dueDate))->format('d/m/Y') . ')' : ''), 'tasks', $taskId);
        return ['ok' => true, 'summary' => "✅ Tâche ajoutée : « $title »" . ($dueDate ? ' pour le ' . (new \DateTime($dueDate))->format('d/m/Y') : '') . '.'];
    }

    private static function doAddShoppingItem(array $args, int $familyId, int $userId): array
    {
        $title = trim((string)($args['title'] ?? ''));
        if ($title === '') return ['ok' => false, 'summary' => "Nom d'article manquant."];

        $listId = TaskList::findOrCreateDefaultList($familyId, $userId, 'shopping');
        $taskId = TaskList::createTask($listId, $userId, ['title' => $title]);
        AiAssistantAction::log($familyId, $userId, 'add_shopping_item', "Article ajouté à la liste de courses : « $title »", 'tasks', $taskId);
        return ['ok' => true, 'summary' => "🛒 « $title » ajouté à la liste de courses."];
    }

    private static function doAddCalendarEvent(array $args, int $familyId, int $userId): array
    {
        $title = trim((string)($args['title'] ?? ''));
        $start = $args['start_datetime'] ?? '';
        if ($title === '' || !is_string($start) || !preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $start)) {
            return ['ok' => false, 'summary' => "Titre ou date/heure de début manquants ou invalides."];
        }
        $startDt = \DateTime::createFromFormat('Y-m-d H:i', $start);
        if (!$startDt) return ['ok' => false, 'summary' => "Date de début invalide."];

        $end = $args['end_datetime'] ?? null;
        $endDt = (is_string($end) && preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $end))
            ? \DateTime::createFromFormat('Y-m-d H:i', $end)
            : (clone $startDt)->modify('+1 hour');

        $eventId = Event::create([
            'family_id' => $familyId,
            'user_id' => $userId,
            'title' => $title,
            'description' => trim((string)($args['description'] ?? '')) ?: null,
            'start_datetime' => $startDt->format('Y-m-d H:i:00'),
            'end_datetime' => $endDt->format('Y-m-d H:i:00'),
        ]);
        AiAssistantAction::log($familyId, $userId, 'add_calendar_event', "Événement ajouté : « $title » le " . $startDt->format('d/m/Y à H:i'), 'events', $eventId);
        return ['ok' => true, 'summary' => "📅 Événement ajouté : « $title » le " . $startDt->format('d/m/Y à H:i') . '.'];
    }
}
