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
        $user = Session::user();
        $familyId = $user['family_id'];
        $month = $_GET['month'] ?? date('Y-m');
        $categories = Budget::getCategories($familyId);
        $transactions = Budget::getTransactions($familyId, $month);
        $summary = Budget::getSummary($familyId, $month);
        $breakdown = Budget::getCategoryBreakdown($familyId, $month);
        $goals = Budget::getGoals($familyId);
        $members = User::getByFamily($familyId);
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
            Budget::deleteCategory($id);
            return ['success' => true];
        });
    }

    public function createTransaction(array $params): void
    {
        $this->requireAuth();
        $this->json(function () {
            $user = Session::user();
            $data = $this->jsonInput();
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
            Budget::updateTransaction($id, $this->jsonInput());
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
            Budget::updateGoal($id, $this->jsonInput());
            return ['success' => true];
        });
    }

    public function deleteGoal(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $id = (int)$params['id'];
            Budget::deleteGoal($id);
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
                'summary' => Budget::getSummary($user['family_id'], $month),
                'breakdown' => Budget::getCategoryBreakdown($user['family_id'], $month),
            ];
        });
    }
}
