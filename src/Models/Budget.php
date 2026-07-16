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

    /**
     * Saisie assistée : suggère une catégorie déjà utilisée pour un titre similaire,
     * en se basant sur les transactions saisies précédemment par la famille.
     */
    public static function suggestCategory(int $familyId, string $title): ?array
    {
        $title = trim($title);
        if ($title === '') return null;
        $row = Database::fetch(
            'SELECT bt.category_id, bc.name, bc.icon, bc.color
             FROM budget_transactions bt
             JOIN budget_categories bc ON bc.id = bt.category_id
             WHERE bt.family_id = ? AND bt.category_id IS NOT NULL AND LOWER(bt.title) = LOWER(?)
             GROUP BY bt.category_id
             ORDER BY COUNT(*) DESC, MAX(bt.created_at) DESC
             LIMIT 1',
            [$familyId, $title]
        );
        return $row ?: null;
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

    // Recurring items
    public static function getRecurring(int $familyId): array
    {
        return Database::fetchAll(
            'SELECT br.*, u.name as user_name, u.color as user_color,
                    bc.name as category_name, bc.icon as category_icon, bc.color as category_color
             FROM budget_recurring br
             JOIN users u ON u.id = br.user_id
             LEFT JOIN budget_categories bc ON bc.id = br.category_id
             WHERE br.family_id = ? ORDER BY br.type DESC, br.day_of_month ASC',
            [$familyId]
        );
    }

    public static function getRecurringItem(int $id): ?array
    {
        return Database::fetch('SELECT * FROM budget_recurring WHERE id=?', [$id]);
    }

    public static function createRecurring(int $familyId, int $userId, array $data): int
    {
        return Database::insert(
            'INSERT INTO budget_recurring (family_id, user_id, title, amount, type, category_id, day_of_month, alert_days_before, is_active, notes)
             VALUES (?,?,?,?,?,?,?,?,?,?)',
            [$familyId, $userId, $data['title'], $data['amount'], $data['type'],
             $data['category_id'] ?? null, (int)$data['day_of_month'], (int)($data['alert_days_before'] ?? 3),
             isset($data['is_active']) ? (int)$data['is_active'] : 1,
             $data['notes'] ?? null]
        );
    }

    public static function updateRecurring(int $id, array $data): void
    {
        Database::execute(
            'UPDATE budget_recurring SET title=?, amount=?, type=?, category_id=?, day_of_month=?, alert_days_before=?, user_id=?, is_active=?, notes=? WHERE id=?',
            [$data['title'], $data['amount'], $data['type'], $data['category_id'] ?? null,
             (int)$data['day_of_month'], (int)($data['alert_days_before'] ?? 3), (int)$data['user_id'],
             isset($data['is_active']) ? (int)$data['is_active'] : 1,
             $data['notes'] ?? null, $id]
        );
    }

    public static function deleteRecurring(int $id): void
    {
        Database::execute('DELETE FROM budget_recurring WHERE id=?', [$id]);
    }

    public static function markAlertSent(int $id): void
    {
        Database::execute('UPDATE budget_recurring SET last_alert_sent=CURDATE() WHERE id=?', [$id]);
    }

    public static function getRecurringSummary(int $familyId): array
    {
        $row = Database::fetch(
            'SELECT SUM(CASE WHEN type="income" THEN amount ELSE 0 END)  as income,
                    SUM(CASE WHEN type="expense" THEN amount ELSE 0 END) as expenses
             FROM budget_recurring WHERE family_id=? AND is_active=1',
            [$familyId]
        );
        return [
            'income'   => (float)($row['income']   ?? 0),
            'expenses' => (float)($row['expenses'] ?? 0),
        ];
    }

    /** Returns chart data: fixed (recurring) and variable (transactions) by category for given month. */
    public static function getChartData(int $familyId, string $month): array
    {
        $fixed = Database::fetchAll(
            'SELECT COALESCE(bc.name,"Sans catégorie") as name,
                    COALESCE(bc.color,"#95a5a6") as color,
                    br.type, SUM(br.amount) as total
             FROM budget_recurring br
             LEFT JOIN budget_categories bc ON bc.id = br.category_id
             WHERE br.family_id=? AND br.is_active=1
             GROUP BY br.category_id, br.type ORDER BY total DESC',
            [$familyId]
        );

        $variable = Database::fetchAll(
            'SELECT COALESCE(bc.name,"Sans catégorie") as name,
                    COALESCE(bc.color,"#95a5a6") as color,
                    bt.type, SUM(bt.amount) as total
             FROM budget_transactions bt
             LEFT JOIN budget_categories bc ON bc.id = bt.category_id
             WHERE bt.family_id=? AND DATE_FORMAT(bt.date,"%Y-%m")=?
             GROUP BY bt.category_id, bt.type ORDER BY total DESC',
            [$familyId, $month]
        );

        return ['fixed' => $fixed, 'variable' => $variable];
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
