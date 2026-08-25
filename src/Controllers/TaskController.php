<?php
namespace App\Controllers;

use App\Core\Session;
use App\Models\Family;
use App\Models\TaskList;
use App\Models\TaskListShare;
use App\Models\User;
use App\Models\Notification;
use App\Models\Event;
use Dompdf\Dompdf;
use Dompdf\Options;

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
        $selectableEvents = Event::getSelectableForFamily($user['family_id']);
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

    public function linkEvent(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $listId = (int)$params['id'];
            $list = TaskList::getById($listId);
            if (!$list || $list['family_id'] !== $user['family_id']) return ['success' => false];
            if ($list['user_id'] !== $user['id'] && $user['role'] !== 'admin') return ['success' => false, 'error' => 'Accès refusé.'];

            $data = $this->jsonInput();
            $eventId = (int)($data['event_id'] ?? 0);
            $event = $eventId ? Event::getById($eventId) : null;
            if (!$event || (int)$event['family_id'] !== $user['family_id']) {
                return ['success' => false, 'error' => 'Événement introuvable.'];
            }

            TaskList::linkToEvent($listId, $eventId);
            return ['success' => true, 'list' => TaskList::getById($listId)];
        });
    }

    public function unlinkEvent(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $listId = (int)$params['id'];
            $list = TaskList::getById($listId);
            if (!$list || $list['family_id'] !== $user['family_id']) return ['success' => false];
            if ($list['user_id'] !== $user['id'] && $user['role'] !== 'admin') return ['success' => false, 'error' => 'Accès refusé.'];

            TaskList::unlinkEvent($listId);
            return ['success' => true];
        });
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

    // ── Export PDF ───────────────────────────────────────────────

    public function pdf(array $params): void
    {
        $this->requireAuth();
        $user = Session::user();
        $list = TaskList::getById((int)$params['id']);
        if (!$list || $list['family_id'] !== $user['family_id']) {
            http_response_code(404);
            return;
        }
        $tasks = TaskList::getTasks((int)$list['id']);
        $family = Family::findById((int)$user['family_id']);

        $h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $rows = '';
        foreach ($tasks as $t) {
            $box = $t['is_completed'] ? '☑' : '☐';
            $meta = [];
            if ($t['assigned_name']) $meta[] = $h($t['assigned_name']);
            if ($t['due_date']) $meta[] = 'échéance ' . date('d/m/Y', strtotime($t['due_date']));
            $metaHtml = $meta ? ' <span class="meta">(' . implode(', ', $meta) . ')</span>' : '';
            $titleStyle = $t['is_completed'] ? ' style="text-decoration:line-through;color:#888"' : '';
            $rows .= "<div class=\"item\"><span class=\"box\">{$box}</span><span{$titleStyle}>" . $h($t['title']) . "</span>{$metaHtml}</div>";
        }
        if (!$tasks) $rows = '<p class="empty">Aucun élément.</p>';

        $html = <<<HTML
<!DOCTYPE html>
<html lang="fr"><head><meta charset="UTF-8"><style>
    body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 11pt; color: #000; }
    h1 { font-size: 16pt; margin-bottom: 2px; }
    .subtitle { color: #666; font-size: 9pt; margin-top: 0; margin-bottom: 18px; }
    .item { padding: 5px 0; border-bottom: 1px solid #eee; }
    .box { display: inline-block; width: 18px; font-size: 13pt; }
    .meta { color: #888; font-size: 9pt; }
    .empty { color: #888; }
</style></head><body>
<h1>{$h($list['name'])}</h1>
<p class="subtitle">{$h($family['name'] ?? 'FamilyBoard')} — généré le {$h(date('d/m/Y à H:i'))}</p>
{$rows}
</body></html>
HTML;

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $list['name']) . '.pdf';
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo $dompdf->output();
    }

    // ── Partage public (lien, sans compte) ───────────────────────

    public function shareList(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $list = TaskList::getById((int)$params['id']);
            if (!$list || $list['family_id'] !== $user['family_id']) return ['success' => false];
            $share = TaskListShare::getOrCreate((int)$list['id'], (int)$user['id']);
            $share['url'] = rtrim($this->originUrl(), '/') . BASE_URL . '/share/list/' . $share['token'];
            return ['success' => true, 'share' => $share];
        });
    }

    public function regenerateShareLink(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $list = TaskList::getById((int)$params['id']);
            if (!$list || $list['family_id'] !== $user['family_id']) return ['success' => false];
            if (!TaskListShare::getByList((int)$list['id'])) return ['success' => false];
            $share = TaskListShare::regenerate((int)$list['id']);
            $share['url'] = rtrim($this->originUrl(), '/') . BASE_URL . '/share/list/' . $share['token'];
            return ['success' => true, 'share' => $share];
        });
    }

    public function revokeShareLink(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $list = TaskList::getById((int)$params['id']);
            if (!$list || $list['family_id'] !== $user['family_id']) return ['success' => false];
            TaskListShare::revoke((int)$list['id']);
            return ['success' => true];
        });
    }

    /** Page publique, sans compte : lecture + coche/décoche, rien d'autre (pas d'ajout ni de
     *  suppression) — voir aussi data()/toggle() ci-dessous, appelés depuis cette page. */
    public function publicView(array $params): void
    {
        $token = $params['token'] ?? '';
        $share = TaskListShare::findValidByToken($token);
        if (!$share) {
            http_response_code(404);
            $list = null;
            require BASE_PATH . '/templates/tasks/public.php';
            return;
        }
        $list = TaskList::getById((int)$share['list_id']);
        require BASE_PATH . '/templates/tasks/public.php';
    }

    public function publicData(array $params): void
    {
        $this->json(function () use ($params) {
            $share = TaskListShare::findValidByToken($params['token'] ?? '');
            if (!$share) return ['success' => false];
            $list = TaskList::getById((int)$share['list_id']);
            if (!$list) return ['success' => false];
            return ['success' => true, 'list' => $list, 'tasks' => TaskList::getTasks((int)$list['id'])];
        });
    }

    public function publicToggle(array $params): void
    {
        $this->json(function () use ($params) {
            $share = TaskListShare::findValidByToken($params['token'] ?? '');
            if (!$share) return ['success' => false];
            $task = TaskList::getTask((int)$params['taskId']);
            if (!$task || (int)$task['list_id'] !== (int)$share['list_id']) return ['success' => false];
            $completed = TaskList::toggleTask((int)$task['id']);
            return ['success' => true, 'completed' => $completed];
        });
    }

    private function originUrl(): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        return $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    }
}
