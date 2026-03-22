<?php
namespace App\Models;

use App\Core\Database;

class Budget
{
    // Categories
    public static function getCategories(int $familyId): array
    {
        return Database::fetchAll('SELECT * FROM budget_categories WHERE family_id=? ORDER BY name', [$familyId]);
    }

    public static function createCategory(int $familyId, string $name, string $color = '#4A90D9', string $icon = '💰'): int
    {
        return Database::insert('INSERT INTO budget_categories (family_id, name, color, icon) VALUES (?,?,?,?)', [$familyId, $name, $color, $icon]);
    }

    public static function deleteCategory(int $id): void
    {
        Database::execute('DELETE FROM budget_categories WHERE id=?', [$id]);
    }

    // Transactions
    public static function getTransactions(int $familyId, ?string $month = null, ?int $categoryId = null): array
    {
        $sql = 'SELECT bt.*, u.name as user_name, bc.name as category_name, bc.color as category_color, bc.icon as category_icon
                FROM budget_transactions bt
                JOIN users u ON u.id=bt.user_id
                LEFT JOIN budget_categories bc ON bc.id=bt.category_id
                WHERE bt.family_id=?';
        $params = [$familyId];

        if ($month) {
            $sql .= ' AND DATE_FORMAT(bt.date, "%Y-%m") = ?';
            $params[] = $month;
        }
        if ($categoryId) {
            $sql .= ' AND bt.category_id = ?';
            $params[] = $categoryId;
        }
        $sql .= ' ORDER BY bt.date DESC, bt.created_at DESC';
        return Database::fetchAll($sql, $params);
    }

    public static function createTransaction(int $familyId, int $userId, array $data): int
    {
        return Database::insert(
            'INSERT INTO budget_transactions (family_id, user_id, category_id, title, amount, type, date, notes) VALUES (?,?,?,?,?,?,?,?)',
            [$familyId, $userId, $data['category_id'] ?? null, $data['title'], $data['amount'], $data['type'], $data['date'], $data['notes'] ?? null]
        );
    }

    public static function updateTransaction(int $id, array $data): void
    {
        Database::execute(
            'UPDATE budget_transactions SET category_id=?, title=?, amount=?, type=?, date=?, notes=? WHERE id=?',
            [$data['category_id'] ?? null, $data['title'], $data['amount'], $data['type'], $data['date'], $data['notes'] ?? null, $id]
        );
    }

    public static function deleteTransaction(int $id): void
    {
        Database::execute('DELETE FROM budget_transactions WHERE id=?', [$id]);
    }

    public static function getTransaction(int $id): ?array
    {
        return Database::fetch('SELECT * FROM budget_transactions WHERE id=?', [$id]);
    }

    public static function getSummary(int $familyId, string $month): array
    {
        $row = Database::fetch(
            'SELECT
               SUM(CASE WHEN type="income" THEN amount ELSE 0 END) as income,
               SUM(CASE WHEN type="expense" THEN amount ELSE 0 END) as expenses
             FROM budget_transactions WHERE family_id=? AND DATE_FORMAT(date, "%Y-%m")=?',
            [$familyId, $month]
        );
        return [
            'income' => (float)($row['income'] ?? 0),
            'expenses' => (float)($row['expenses'] ?? 0),
            'balance' => (float)($row['income'] ?? 0) - (float)($row['expenses'] ?? 0),
        ];
    }

    public static function getCategoryBreakdown(int $familyId, string $month): array
    {
        return Database::fetchAll(
            'SELECT bc.name, bc.color, bc.icon, SUM(bt.amount) as total, bt.type
             FROM budget_transactions bt
             LEFT JOIN budget_categories bc ON bc.id=bt.category_id
             WHERE bt.family_id=? AND DATE_FORMAT(bt.date, "%Y-%m")=?
             GROUP BY bt.category_id, bt.type ORDER BY total DESC',
            [$familyId, $month]
        );
    }

    // Goals
    public static function getGoals(int $familyId): array
    {
        return Database::fetchAll('SELECT * FROM budget_goals WHERE family_id=? ORDER BY created_at DESC', [$familyId]);
    }

    public static function createGoal(int $familyId, array $data): int
    {
        return Database::insert(
            'INSERT INTO budget_goals (family_id, name, target_amount, current_amount, deadline, color) VALUES (?,?,?,?,?,?)',
            [$familyId, $data['name'], $data['target_amount'], $data['current_amount'] ?? 0, $data['deadline'] ?? null, $data['color'] ?? '#4A90D9']
        );
    }

    public static function updateGoal(int $id, array $data): void
    {
        Database::execute(
            'UPDATE budget_goals SET name=?, target_amount=?, current_amount=?, deadline=?, color=? WHERE id=?',
            [$data['name'], $data['target_amount'], $data['current_amount'] ?? 0, $data['deadline'] ?? null, $data['color'] ?? '#4A90D9', $id]
        );
    }

    public static function deleteGoal(int $id): void
    {
        Database::execute('DELETE FROM budget_goals WHERE id=?', [$id]);
    }
}
