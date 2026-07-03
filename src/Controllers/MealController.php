<?php
namespace App\Controllers;

use App\Core\Database;
use App\Core\Session;
use App\Models\MealPlan;
use App\Models\Recipe;
use App\Models\TaskList;

class MealController extends BaseController
{
    public function index(array $params): void
    {
        $this->requireAuth();
        $this->requireModule('meals');
        $user = Session::user();

        $start = $_GET['start'] ?? date('Y-m-d', strtotime('monday this week'));
        $days  = [];
        for ($i = 0; $i < 7; $i++) {
            $days[] = date('Y-m-d', strtotime($start . " +{$i} days"));
        }

        $recipes = Recipe::getByFamily($user['family_id']);
        $week    = MealPlan::getWeek($user['family_id'], $start);
        $prevWeek = date('Y-m-d', strtotime($start . ' -7 days'));
        $nextWeek = date('Y-m-d', strtotime($start . ' +7 days'));

        require BASE_PATH . '/templates/meals/index.php';
    }

    public function createRecipe(array $params): void
    {
        $this->requireAuth();
        $this->json(function () {
            $user = Session::user();
            $data = $this->jsonInput();
            if (empty($data['title']) || empty($data['ingredients'])) {
                return ['success' => false, 'error' => 'Titre et ingrédients requis.'];
            }
            $id = Recipe::create($user['family_id'], $user['id'], $data);
            return ['success' => true, 'recipe' => Recipe::getById($id)];
        });
    }

    public function updateRecipe(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user   = Session::user();
            $recipe = Recipe::getById((int)$params['id']);
            if (!$recipe || $recipe['family_id'] !== $user['family_id']) return ['success' => false];
            Recipe::update($recipe['id'], $this->jsonInput());
            return ['success' => true, 'recipe' => Recipe::getById($recipe['id'])];
        });
    }

    public function deleteRecipe(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user   = Session::user();
            $recipe = Recipe::getById((int)$params['id']);
            if (!$recipe || $recipe['family_id'] !== $user['family_id']) return ['success' => false];
            Recipe::delete($recipe['id']);
            return ['success' => true];
        });
    }

    public function setMeal(array $params): void
    {
        $this->requireAuth();
        $this->json(function () {
            $user = Session::user();
            $data = $this->jsonInput();
            $date = $data['date'] ?? '';
            $type = $data['meal_type'] ?? '';
            if (!$date || !isset(MealPlan::TYPES[$type])) {
                return ['success' => false, 'error' => 'Paramètres invalides.'];
            }
            $recipeId = !empty($data['recipe_id']) ? (int)$data['recipe_id'] : null;
            if ($recipeId) {
                $recipe = Recipe::getById($recipeId);
                if (!$recipe || $recipe['family_id'] !== $user['family_id']) return ['success' => false];
            }
            $customTitle = $recipeId ? null : trim($data['custom_title'] ?? '');
            MealPlan::set($user['family_id'], $user['id'], $date, $type, $recipeId, $customTitle ?: null);
            return ['success' => true];
        });
    }

    public function clearMeal(array $params): void
    {
        $this->requireAuth();
        $this->json(function () {
            $user = Session::user();
            $data = $this->jsonInput();
            $date = $data['date'] ?? '';
            $type = $data['meal_type'] ?? '';
            if (!$date || !isset(MealPlan::TYPES[$type])) return ['success' => false];
            MealPlan::clear($user['family_id'], $date, $type);
            return ['success' => true];
        });
    }

    public function addIngredientsToShoppingList(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user   = Session::user();
            $recipe = Recipe::getById((int)$params['id']);
            if (!$recipe || $recipe['family_id'] !== $user['family_id']) return ['success' => false];

            $listId = $this->getOrCreateShoppingList($user['family_id'], $user['id']);
            $added  = 0;
            foreach (Recipe::ingredientLines($recipe) as $line) {
                TaskList::createTask($listId, $user['id'], ['title' => $line]);
                $added++;
            }
            return ['success' => true, 'added' => $added, 'list_id' => $listId];
        });
    }

    private function getOrCreateShoppingList(int $familyId, int $userId): int
    {
        $existing = Database::fetch(
            "SELECT id FROM task_lists WHERE family_id=? AND type='shopping' ORDER BY id LIMIT 1",
            [$familyId]
        );
        if ($existing) return (int)$existing['id'];
        return TaskList::create($familyId, $userId, 'Courses', 'shopping');
    }
}
