<?php
namespace App\Models;

use App\Core\Database;

class TaskList
{
    public static function getByFamily(int $familyId): array
    {
        return Database::fetchAll(
            'SELECT tl.*, u.name as user_name, ev.title as linked_event_title, ev.start_datetime as linked_event_start,
             (SELECT COUNT(*) FROM tasks t WHERE t.list_id=tl.id AND t.is_completed=0) as pending_count,
             (SELECT COUNT(*) FROM tasks t WHERE t.list_id=tl.id) as total_count
             FROM task_lists tl JOIN users u ON u.id=tl.user_id
             LEFT JOIN events ev ON ev.id=tl.linked_event_id
             WHERE tl.family_id=? ORDER BY tl.type, tl.name',
            [$familyId]
        );
    }

    public static function getById(int $id): ?array
    {
        return Database::fetch(
            'SELECT tl.*, ev.title as linked_event_title, ev.start_datetime as linked_event_start
             FROM task_lists tl LEFT JOIN events ev ON ev.id=tl.linked_event_id WHERE tl.id=?',
            [$id]
        );
    }

    /** Rattache la liste à un événement. Si c'est un événement différent de celui déjà
     *  mémorisé, les tâches sont remises à zéro (nouvelle occurrence = checklist vierge). */
    public static function linkToEvent(int $id, int $eventId): void
    {
        $current = Database::fetch('SELECT linked_event_id FROM task_lists WHERE id=?', [$id]);
        if ($current && (int)($current['linked_event_id'] ?? 0) === $eventId) return;

        Database::execute('UPDATE tasks SET is_completed=0, completed_at=NULL WHERE list_id=?', [$id]);
        Database::execute(
            'UPDATE task_lists SET linked_event_id=?, event_reset_at=? WHERE id=?',
            [$eventId, date('Y-m-d H:i:s'), $id]
        );
    }

    public static function unlinkEvent(int $id): void
    {
        Database::execute('UPDATE task_lists SET linked_event_id=NULL, event_reset_at=NULL WHERE id=?', [$id]);
    }

    public static function create(int $familyId, int $userId, string $name, string $type = 'tasks', string $color = '#4A90D9'): int
    {
        return Database::insert(
            'INSERT INTO task_lists (family_id, user_id, name, type, color) VALUES (?,?,?,?,?)',
            [$familyId, $userId, $name, $type, $color]
        );
    }

    /** Liste "Décisions" réutilisée par toute décision actée automatiquement (ex. résultat de
     *  sondage) — évite de créer une nouvelle liste à chaque fois. */
    public static function findOrCreateDecisionsList(int $familyId, int $userId): int
    {
        $existing = Database::fetch(
            "SELECT id FROM task_lists WHERE family_id=? AND type='tasks' AND name=? LIMIT 1",
            [$familyId, 'Décisions']
        );
        if ($existing) return (int)$existing['id'];
        return self::create($familyId, $userId, 'Décisions', 'tasks', '#8E44AD');
    }

    /** Première liste existante du type demandé (peu importe son nom), ou une liste par défaut
     *  créée à la volée — utilisé par l'assistant IA pour ne jamais avoir à faire choisir une
     *  liste précise par le modèle (surface d'erreur inutile pour un modèle local). */
    public static function findOrCreateDefaultList(int $familyId, int $userId, string $type): int
    {
        $existing = Database::fetch(
            'SELECT id FROM task_lists WHERE family_id=? AND type=? ORDER BY id LIMIT 1',
            [$familyId, $type]
        );
        if ($existing) return (int)$existing['id'];
        $name = $type === 'shopping' ? 'Courses' : 'Tâches';
        return self::create($familyId, $userId, $name, $type);
    }

    public static function update(int $id, string $name, string $color): void
    {
        Database::execute('UPDATE task_lists SET name=?, color=? WHERE id=?', [$name, $color, $id]);
    }

    public static function delete(int $id): void
    {
        Database::execute('DELETE FROM task_lists WHERE id=?', [$id]);
    }

    /** Tâches non cochées, toutes listes d'un type confondues (ex. "Courses" combinées) —
     *  utilisé par le widget de la barre des tâches de l'interface bureau. */
    public static function getPendingItemsByType(int $familyId, string $type, int $limit = 30): array
    {
        return Database::fetchAll(
            'SELECT t.id, t.title, t.list_id, tl.name as list_name, tl.color as list_color
             FROM tasks t
             JOIN task_lists tl ON tl.id = t.list_id
             WHERE tl.family_id = ? AND tl.type = ? AND t.is_completed = 0
             ORDER BY t.priority DESC, t.created_at DESC LIMIT ?',
            [$familyId, $type, $limit]
        );
    }

    public static function getTasks(int $listId): array
    {
        return Database::fetchAll(
            'SELECT t.*, u.name as creator_name, a.name as assigned_name, a.color as assigned_color, a.avatar as assigned_avatar
             FROM tasks t
             JOIN users u ON u.id=t.user_id
             LEFT JOIN users a ON a.id=t.assigned_to
             WHERE t.list_id=? ORDER BY t.is_completed ASC, t.priority DESC, t.due_date ASC, t.created_at DESC',
            [$listId]
        );
    }

    public static function createTask(int $listId, int $userId, array $data): int
    {
        return Database::insert(
            'INSERT INTO tasks (list_id, user_id, assigned_to, title, notes, due_date, priority) VALUES (?,?,?,?,?,?,?)',
            [$listId, $userId, $data['assigned_to'] ?? null, $data['title'], $data['notes'] ?? null, $data['due_date'] ?? null, $data['priority'] ?? 'medium']
        );
    }

    public static function updateTask(int $id, array $data): void
    {
        Database::execute(
            'UPDATE tasks SET title=?, notes=?, assigned_to=?, due_date=?, priority=? WHERE id=?',
            [$data['title'], $data['notes'] ?? null, $data['assigned_to'] ?? null, $data['due_date'] ?? null, $data['priority'] ?? 'medium', $id]
        );
    }

    public static function toggleTask(int $id): bool
    {
        $task = Database::fetch('SELECT is_completed FROM tasks WHERE id=?', [$id]);
        if (!$task) return false;
        $completed = !$task['is_completed'];
        Database::execute(
            'UPDATE tasks SET is_completed=?, completed_at=? WHERE id=?',
            // PDOStatement::execute() sérialise un booléen PHP `false` en chaîne vide (pas '0'),
            // rejetée par MySQL en mode strict pour une colonne entière — d'où le cast explicite,
            // sans quoi décocher une tâche échoue silencieusement côté API (cocher fonctionne,
            // `true` devenant '1').
            [(int)$completed, $completed ? date('Y-m-d H:i:s') : null, $id]
        );
        return $completed;
    }

    public static function deleteTask(int $id): void
    {
        Database::execute('DELETE FROM tasks WHERE id=?', [$id]);
    }

    public static function getTask(int $id): ?array
    {
        return Database::fetch('SELECT * FROM tasks WHERE id=?', [$id]);
    }
}
