<?php
namespace App\Controllers;

use App\Core\Session;
use App\Models\TaskList;
use App\Models\User;
use App\Models\Notification;

class TaskController extends BaseController
{
    public function index(array $params): void
    {
        $this->requireAuth();
        $this->requireModule('tasks');
        $user = Session::user();
        $lists = TaskList::getByFamily($user['family_id']);
        $members = User::getByFamily($user['family_id']);
        $selectedListId = (int)($_GET['list'] ?? 0);
        $selectedList = null;
        $tasks = [];
        if ($selectedListId) {
            $selectedList = TaskList::getById($selectedListId);
            if ($selectedList && $selectedList['family_id'] === $user['family_id']) {
                $tasks = TaskList::getTasks($selectedListId);
            }
        } elseif ($lists) {
            $selectedList = $lists[0];
            $selectedListId = $selectedList['id'];
            $tasks = TaskList::getTasks($selectedListId);
        }
        require BASE_PATH . '/templates/tasks/index.php';
    }

    public function createList(array $params): void
    {
        $this->requireAuth();
        $user = Session::user();
        $name = trim($_POST['name'] ?? '');
        $type = in_array($_POST['type'] ?? '', ['tasks', 'shopping']) ? $_POST['type'] : 'tasks';
        $color = $this->safeColor($_POST['color'] ?? null);
        if ($name) {
            TaskList::create($user['family_id'], $user['id'], $name, $type, $color);
        }
        header('Location: ' . BASE_URL . '/tasks');
        exit;
    }

    public function deleteList(array $params): void
    {
        $this->requireAuth();
        $user = Session::user();
        $id = (int)$params['id'];
        $list = TaskList::getById($id);
        if ($list && $list['family_id'] === $user['family_id'] && ($list['user_id'] === $user['id'] || $user['role'] === 'admin')) {
            TaskList::delete($id);
        }
        header('Location: ' . BASE_URL . '/tasks');
        exit;
    }

    public function createTask(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $listId = (int)$params['id'];
            $list = TaskList::getById($listId);
            if (!$list || $list['family_id'] !== $user['family_id']) return ['success' => false];
            $data = $this->jsonInput();
            if (!empty($data['assigned_to']) && !User::belongsToFamily((int)$data['assigned_to'], $user['family_id'])) {
                unset($data['assigned_to']);
            }
            $taskId = TaskList::createTask($listId, $user['id'], $data);
            if (!empty($data['assigned_to']) && (int)$data['assigned_to'] !== $user['id']) {
                Notification::create((int)$data['assigned_to'], 'task', 'Tâche assignée', $user['name'] . ' vous a assigné : ' . $data['title'], BASE_URL . '/tasks?list=' . $listId);
            }
            return ['success' => true, 'task' => TaskList::getTask($taskId)];
        });
    }

    public function toggleTask(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $taskId = (int)$params['id'];
            $task = TaskList::getTask($taskId);
            if (!$task) return ['success' => false];
            $list = TaskList::getById($task['list_id']);
            if (!$list || $list['family_id'] !== $user['family_id']) return ['success' => false];
            $completed = TaskList::toggleTask($taskId);
            return ['success' => true, 'completed' => $completed];
        });
    }

    public function deleteTask(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $taskId = (int)$params['id'];
            $task = TaskList::getTask($taskId);
            if (!$task) return ['success' => false];
            $list = TaskList::getById($task['list_id']);
            if (!$list || $list['family_id'] !== $user['family_id']) return ['success' => false];
            TaskList::deleteTask($taskId);

            if (!empty($task['assigned_to']) && (int)$task['assigned_to'] !== $user['id']) {
                Notification::create((int)$task['assigned_to'], 'task', 'Tâche supprimée',
                    $user['name'] . ' a supprimé : ' . $task['title'], BASE_URL . '/tasks?list=' . $task['list_id']);
            }

            return ['success' => true];
        });
    }

    public function updateTask(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $taskId = (int)$params['id'];
            $task = TaskList::getTask($taskId);
            if (!$task) return ['success' => false];
            $list = TaskList::getById($task['list_id']);
            if (!$list || $list['family_id'] !== $user['family_id']) return ['success' => false];
            $data = $this->jsonInput();
            if (!empty($data['assigned_to']) && !User::belongsToFamily((int)$data['assigned_to'], $user['family_id'])) {
                unset($data['assigned_to']);
            }
            TaskList::updateTask($taskId, $data);

            $newAssignee = (int)($data['assigned_to'] ?? 0);
            if ($newAssignee && $newAssignee !== $user['id']) {
                Notification::create($newAssignee, 'task',
                    (int)$task['assigned_to'] === $newAssignee ? 'Tâche modifiée' : 'Tâche assignée',
                    $user['name'] . ' a modifié : ' . $data['title'], BASE_URL . '/tasks?list=' . $task['list_id']);
            }

            return ['success' => true, 'task' => TaskList::getTask($taskId)];
        });
    }
}
