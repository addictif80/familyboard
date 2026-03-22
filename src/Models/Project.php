<?php
namespace App\Models;

use App\Core\Database;

class Project
{
    public static function getByFamily(int $familyId): array
    {
        return Database::fetchAll(
            'SELECT p.*, u.name as user_name,
             (SELECT COUNT(*) FROM project_tasks pt WHERE pt.project_id=p.id) as task_count,
             (SELECT COUNT(*) FROM project_tasks pt WHERE pt.project_id=p.id AND pt.status="done") as done_count,
             (SELECT COALESCE(SUM(pe.amount),0) FROM project_expenses pe WHERE pe.project_id=p.id) as spent
             FROM projects p JOIN users u ON u.id=p.user_id
             WHERE p.family_id=? ORDER BY p.status ASC, p.created_at DESC',
            [$familyId]
        );
    }

    public static function getById(int $id): ?array
    {
        return Database::fetch(
            'SELECT p.*, u.name as user_name,
             (SELECT COALESCE(SUM(pe.amount),0) FROM project_expenses pe WHERE pe.project_id=p.id) as spent
             FROM projects p JOIN users u ON u.id=p.user_id WHERE p.id=?',
            [$id]
        );
    }

    public static function create(int $familyId, int $userId, array $data): int
    {
        return Database::insert(
            'INSERT INTO projects (family_id, user_id, name, description, budget, deadline, color) VALUES (?,?,?,?,?,?,?)',
            [$familyId, $userId, $data['name'], $data['description'] ?? null, $data['budget'] ?? null, $data['deadline'] ?? null, $data['color'] ?? '#4A90D9']
        );
    }

    public static function update(int $id, array $data): void
    {
        Database::execute(
            'UPDATE projects SET name=?, description=?, status=?, budget=?, deadline=?, color=? WHERE id=?',
            [$data['name'], $data['description'] ?? null, $data['status'] ?? 'active', $data['budget'] ?? null, $data['deadline'] ?? null, $data['color'] ?? '#4A90D9', $id]
        );
    }

    public static function delete(int $id): void
    {
        Database::execute('DELETE FROM projects WHERE id=?', [$id]);
    }

    // Tasks
    public static function getTasks(int $projectId): array
    {
        return Database::fetchAll(
            'SELECT pt.*, u.name as creator_name, a.name as assigned_name, a.color as assigned_color
             FROM project_tasks pt
             JOIN users u ON u.id=pt.user_id
             LEFT JOIN users a ON a.id=pt.assigned_to
             WHERE pt.project_id=? ORDER BY pt.status, pt.priority DESC, pt.created_at',
            [$projectId]
        );
    }

    public static function createTask(int $projectId, int $userId, array $data): int
    {
        return Database::insert(
            'INSERT INTO project_tasks (project_id, user_id, assigned_to, title, description, status, priority) VALUES (?,?,?,?,?,?,?)',
            [$projectId, $userId, $data['assigned_to'] ?? null, $data['title'], $data['description'] ?? null, $data['status'] ?? 'todo', $data['priority'] ?? 'medium']
        );
    }

    public static function updateTask(int $id, array $data): void
    {
        Database::execute(
            'UPDATE project_tasks SET assigned_to=?, title=?, description=?, status=?, priority=? WHERE id=?',
            [$data['assigned_to'] ?? null, $data['title'], $data['description'] ?? null, $data['status'] ?? 'todo', $data['priority'] ?? 'medium', $id]
        );
    }

    public static function deleteTask(int $id): void
    {
        Database::execute('DELETE FROM project_tasks WHERE id=?', [$id]);
    }

    public static function getTask(int $id): ?array
    {
        return Database::fetch('SELECT pt.*, p.family_id FROM project_tasks pt JOIN projects p ON p.id=pt.project_id WHERE pt.id=?', [$id]);
    }

    // Expenses
    public static function getExpenses(int $projectId): array
    {
        return Database::fetchAll(
            'SELECT pe.*, u.name as user_name FROM project_expenses pe JOIN users u ON u.id=pe.user_id WHERE pe.project_id=? ORDER BY pe.date DESC',
            [$projectId]
        );
    }

    public static function createExpense(int $projectId, int $userId, array $data): int
    {
        return Database::insert(
            'INSERT INTO project_expenses (project_id, user_id, title, amount, date, notes) VALUES (?,?,?,?,?,?)',
            [$projectId, $userId, $data['title'], $data['amount'], $data['date'], $data['notes'] ?? null]
        );
    }

    public static function deleteExpense(int $id): void
    {
        Database::execute('DELETE FROM project_expenses WHERE id=?', [$id]);
    }

    public static function getExpense(int $id): ?array
    {
        return Database::fetch('SELECT pe.*, p.family_id FROM project_expenses pe JOIN projects p ON p.id=pe.project_id WHERE pe.id=?', [$id]);
    }
}
