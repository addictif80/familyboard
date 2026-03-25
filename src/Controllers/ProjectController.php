<?php
namespace App\Controllers;

use App\Core\Session;
use App\Models\Project;
use App\Models\User;
use App\Models\Notification;

class ProjectController extends BaseController
{
    public function index(array $params): void
    {
        $this->requireAuth();
        $this->requireModule('projects');
        $user = Session::user();
        $projects = Project::getByFamily($user['family_id']);
        require BASE_PATH . '/templates/projects/index.php';
    }

    /** GET /api/projects/:id/calendar — calendrier interne d'un projet */
    public function apiProjectCalendar(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user    = Session::user();
            $id      = (int)$params['id'];
            $project = Project::getById($id);
            if (!$project || $project['family_id'] !== $user['family_id']) {
                return ['success' => false, 'error' => 'Non autorisé'];
            }
            return Project::getCalendarEvents($id, $project);
        });
    }

    /** GET /api/projects/calendar?start=YYYY-MM-DD&end=YYYY-MM-DD */
    public function apiCalendar(array $params): void
    {
        $this->requireAuth();
        $this->json(function () {
            $user  = Session::user();
            $start = $_GET['start'] ?? date('Y-m-01');
            $end   = $_GET['end']   ?? date('Y-m-t');
            $deadlines = Project::getDeadlines($user['family_id'], $start, $end);
            return array_map(fn($p) => [
                'id'     => 'project_' . $p['id'],
                'title'  => $p['name'],
                'start'  => $p['date'],
                'end'    => $p['date'],
                'allDay' => true,
                'color'  => $p['color'],
                'extendedProps' => [
                    'type'       => 'project',
                    'project_id' => (int)$p['id'],
                    'status'     => $p['status'],
                ],
            ], $deadlines);
        });
    }

    public function show(array $params): void
    {
        $this->requireAuth();
        $user = Session::user();
        $id = (int)$params['id'];
        $project = Project::getById($id);
        if (!$project || $project['family_id'] !== $user['family_id']) {
            header('Location: ' . BASE_URL . '/projects');
            exit;
        }
        $tasks     = Project::getTasks($id);
        $expenses  = Project::getExpenses($id);
        $materials = Project::getMaterials($id);
        $members   = User::getByFamily($user['family_id']);
        require BASE_PATH . '/templates/projects/show.php';
    }

    public function create(array $params): void
    {
        $this->requireAuth();
        $this->json(function () {
            $user = Session::user();
            $data = $this->jsonInput();
            $id = Project::create($user['family_id'], $user['id'], $data);
            return ['success' => true, 'id' => $id];
        });
    }

    public function update(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $id = (int)$params['id'];
            $project = Project::getById($id);
            if (!$project || $project['family_id'] !== $user['family_id']) return ['success' => false];
            Project::update($id, $this->jsonInput());
            return ['success' => true];
        });
    }

    public function delete(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $id = (int)$params['id'];
            $project = Project::getById($id);
            if (!$project || $project['family_id'] !== $user['family_id']) return ['success' => false];
            Project::delete($id);
            return ['success' => true];
        });
    }

    public function createTask(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $projectId = (int)$params['id'];
            $project = Project::getById($projectId);
            if (!$project || $project['family_id'] !== $user['family_id']) return ['success' => false];
            $data = $this->jsonInput();
            $taskId = Project::createTask($projectId, $user['id'], $data);
            return ['success' => true, 'task' => Project::getTask($taskId)];
        });
    }

    public function updateTask(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $id = (int)$params['id'];
            $task = Project::getTask($id);
            if (!$task || $task['family_id'] !== $user['family_id']) return ['success' => false];
            Project::updateTask($id, $this->jsonInput());
            return ['success' => true, 'task' => Project::getTask($id)];
        });
    }

    public function deleteTask(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $id = (int)$params['id'];
            $task = Project::getTask($id);
            if (!$task || $task['family_id'] !== $user['family_id']) return ['success' => false];
            Project::deleteTask($id);
            return ['success' => true];
        });
    }

    public function createExpense(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $projectId = (int)$params['id'];
            $project = Project::getById($projectId);
            if (!$project || $project['family_id'] !== $user['family_id']) return ['success' => false];
            $data = $this->jsonInput();
            $id = Project::createExpense($projectId, $user['id'], $data);
            return ['success' => true, 'id' => $id];
        });
    }

    public function deleteExpense(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $id = (int)$params['id'];
            $expense = Project::getExpense($id);
            if (!$expense || $expense['family_id'] !== $user['family_id']) return ['success' => false];
            Project::deleteExpense($id);
            return ['success' => true];
        });
    }

    public function createMaterial(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user      = Session::user();
            $projectId = (int)$params['id'];
            $project   = Project::getById($projectId);
            if (!$project || $project['family_id'] !== $user['family_id']) return ['success' => false];
            $id = Project::createMaterial($projectId, $user['id'], $this->jsonInput());
            return ['success' => true, 'material' => Project::getMaterial($id)];
        });
    }

    public function updateMaterial(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $id   = (int)$params['id'];
            $mat  = Project::getMaterial($id);
            if (!$mat || $mat['family_id'] !== $user['family_id']) return ['success' => false];
            Project::updateMaterial($id, $this->jsonInput());
            return ['success' => true, 'material' => Project::getMaterial($id)];
        });
    }

    public function toggleMaterial(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $id   = (int)$params['id'];
            $mat  = Project::getMaterial($id);
            if (!$mat || $mat['family_id'] !== $user['family_id']) return ['success' => false];
            Project::toggleMaterial($id);
            return ['success' => true];
        });
    }

    public function deleteMaterial(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $id   = (int)$params['id'];
            $mat  = Project::getMaterial($id);
            if (!$mat || $mat['family_id'] !== $user['family_id']) return ['success' => false];
            Project::deleteMaterial($id);
            return ['success' => true];
        });
    }
}
