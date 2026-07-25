<?php
namespace App\Models;

use App\Core\Database;

class TaskList
{
    public static function getByFamily(int $familyId): array
    {
        return Database::fetchAll(
            'SELECT tl.*, u.name as user_name,
             (SELECT COUNT(*) FROM tasks t WHERE t.list_id=tl.id AND t.is_completed=0) as pending_count,
             (SELECT COUNT(*) FROM tasks t WHERE t.list_id=tl.id) as total_count
             FROM task_lists tl JOIN users u ON u.id=tl.user_id
             WHERE tl.family_id=? ORDER BY tl.type, tl.name',
            [$familyId]
        );
    }

    public static function getById(int $id): ?array
    {
        return Database::fetch('SELECT * FROM task_lists WHERE id=?', [$id]);
    }

    public static function create(int $familyId, int $userId, string $name, string $type = 'tasks', string $color = '#4A90D9'): int
    {
        return Database::insert(
            'INSERT INTO task_lists (family_id, user_id, name, type, color) VALUES (?,?,?,?,?)',
            [$familyId, $userId, $name, $type, $color]
        );
    }

    public static function update(int $id, string $name, string $color): void
    {
        Database::execute('UPDATE task_lists SET name=?, color=? WHERE id=?', [$name, $color, $id]);
    }

    public static function delete(int $id): void
    {
        Database::execute('DELETE FROM task_lists WHERE id=?', [$id]);
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
            [$completed, $completed ? date('Y-m-d H:i:s') : null, $id]
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
