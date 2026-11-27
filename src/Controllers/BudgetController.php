<?php
namespace App\Controllers;

use App\Core\Session;
use App\Models\Budget;
use App\Models\User;

class BudgetController extends BaseController
{
    public function index(array $params): void
    {
        $this->requireAuth();
        $this->requireModule('budget');
        $user = Session::user();
        $familyId = $user['family_id'];
        $month = $_GET['month'] ?? date('Y-m');
        $categories      = Budget::getCategories($familyId);
        $transactions    = Budget::getTransactions($familyId, $month);
        $summary         = Budget::getSummary($familyId, $month);
        $breakdown       = Budget::getCategoryBreakdown($familyId, $month);
        $goals           = Budget::getGoals($familyId);
        $members         = User::getByFamily($familyId);
        $recurring       = Budget::getRecurring($familyId);
        $recurringSummary = Budget::getRecurringSummary($familyId);
        require BASE_PATH . '/templates/budget/index.php';
    }

    public function createCategory(array $params): void
    {
        $this->requireAuth();
        $this->json(function () {
            $user = Session::user();
            $data = $this->jsonInput();
            $id = Budget::createCategory($user['family_id'], $data['name'], $data['color'] ?? '#4A90D9', $data['icon'] ?? '💰');
            return ['success' => true, 'id' => $id];
        });
    }

    public function deleteCategory(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $id = (int)$params['id'];
            Budget::deleteCategory($id, $user['family_id']);
            return ['success' => true];
        });
    }

    public function createTransaction(array $params): void
    {
        $this->requireAuth();
        $this->json(function () {
            $user = Session::user();
            $data = $this->jsonInput();
            if (!empty($data['category_id']) && !Budget::categoryBelongsToFamily((int)$data['category_id'], $user['family_id'])) {
                $data['category_id'] = null;
            }
            $id = Budget::createTransaction($user['family_id'], $user['id'], $data);
            return ['success' => true, 'id' => $id];
        });
    }

    public function updateTransaction(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $id = (int)$params['id'];
            $tx = Budget::getTransaction($id);
            if (!$tx || $tx['family_id'] !== $user['family_id']) return ['success' => false];
            $data = $this->jsonInput();
            if (!empty($data['category_id']) && !Budget::categoryBelongsToFamily((int)$data['category_id'], $user['family_id'])) {
                $data['category_id'] = null;
            }
            Budget::updateTransaction($id, $data);
            return ['success' => true];
        });
    }

    public function deleteTransaction(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $id = (int)$params['id'];
            $tx = Budget::getTransaction($id);
            if (!$tx || $tx['family_id'] !== $user['family_id']) return ['success' => false];
            Budget::deleteTransaction($id);
            return ['success' => true];
        });
    }

    public function createGoal(array $params): void
    {
        $this->requireAuth();
        $this->json(function () {
            $user = Session::user();
            $data = $this->jsonInput();
            $id = Budget::createGoal($user['family_id'], $data);
            return ['success' => true, 'id' => $id];
        });
    }

    public function updateGoal(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $id = (int)$params['id'];
            Budget::updateGoal($id, $user['family_id'], $this->jsonInput());
            return ['success' => true];
        });
    }

    public function deleteGoal(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $id = (int)$params['id'];
            Budget::deleteGoal($id, $user['family_id']);
            return ['success' => true];
        });
    }

    public function apiData(array $params): void
    {
        $this->requireAuth();
        $this->json(function () {
            $user = Session::user();
            $month = $_GET['month'] ?? date('Y-m');
            return [
                'transactions' => Budget::getTransactions($user['family_id'], $month),
                'summary'      => Budget::getSummary($user['family_id'], $month),
                'breakdown'    => Budget::getCategoryBreakdown($user['family_id'], $month),
            ];
        });
    }

    // ---- Recurring items ----

    public function createRecurring(array $params): void
    {
        $this->requireAuth();
        $this->json(function () {
            $user = Session::user();
            $data = $this->jsonInput();
            $userId = isset($data['user_id']) ? (int)$data['user_id'] : $user['id'];
            if (!User::belongsToFamily($userId, $user['family_id'])) $userId = $user['id'];
            if (!empty($data['category_id']) && !Budget::categoryBelongsToFamily((int)$data['category_id'], $user['family_id'])) {
                $data['category_id'] = null;
            }
            $id = Budget::createRecurring($user['family_id'], $userId, $data);
            return ['success' => true, 'id' => $id];
        });
    }

    public function updateRecurring(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $id   = (int)$params['id'];
            $item = Budget::getRecurringItem($id);
            if (!$item || $item['family_id'] !== $user['family_id']) return ['success' => false];
            $data = $this->jsonInput();
            if (!isset($data['user_id']) || !User::belongsToFamily((int)$data['user_id'], $user['family_id'])) {
                $data['user_id'] = $item['user_id'];
            }
            if (!empty($data['category_id']) && !Budget::categoryBelongsToFamily((int)$data['category_id'], $user['family_id'])) {
                $data['category_id'] = $item['category_id'];
            }
            Budget::updateRecurring($id, $data);
            return ['success' => true];
        });
    }

    public function deleteRecurring(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $id   = (int)$params['id'];
            $item = Budget::getRecurringItem($id);
            if (!$item || $item['family_id'] !== $user['family_id']) return ['success' => false];
            Budget::deleteRecurring($id);
            return ['success' => true];
        });
    }

    public function chartData(array $params): void
    {
        $this->requireAuth();
        $this->json(function () {
            $user  = Session::user();
            $month = $_GET['month'] ?? date('Y-m');
            return Budget::getChartData($user['family_id'], $month);
        });
    }

    // ---- Saisie assistée ----

    public function suggestCategory(array $params): void
    {
        $this->requireAuth();
        $this->json(function () {
            $user  = Session::user();
            $title = $_GET['title'] ?? '';
            return ['suggestion' => Budget::suggestCategory($user['family_id'], $title)];
        });
    }
}
