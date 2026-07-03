<?php
namespace App\Controllers;

use App\Core\Database;
use App\Core\Session;
use App\Models\Baby;
use App\Models\EmergencyCard;
use App\Models\Event;
use App\Models\Family;
use App\Models\SitterLink;
use App\Models\User;

class SitterController extends BaseController
{
    public function create(array $params): void
    {
        $this->requireAuth();
        $this->requireModule('sitter');
        $this->json(function () {
            $user  = Session::user();
            $data  = $this->jsonInput();
            $label = trim($data['label'] ?? '') ?: 'Accès baby-sitter';
            $hours = max(1, min(24 * 14, (int)($data['hours'] ?? 12)));
            // sitter_links.expires_at is compared against SQL NOW(), which the DB
            // connection forces to UTC — must use gmdate(), not the family's local date().
            $expiresAt = gmdate('Y-m-d H:i:s', time() + $hours * 3600);

            $link = SitterLink::create($user['family_id'], $user['id'], $label, $expiresAt);
            $link['url'] = rtrim($this->originUrl(), '/') . BASE_URL . '/sitter/' . $link['token'];
            return ['success' => true, 'link' => $link];
        });
    }

    public function revoke(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $link = Database::fetch('SELECT * FROM sitter_links WHERE id=?', [(int)$params['id']]);
            if (!$link || $link['family_id'] !== $user['family_id']) return ['success' => false];
            SitterLink::revoke($link['id']);
            return ['success' => true];
        });
    }

    /** Public, unauthenticated, read-only view for the babysitter. */
    public function view(array $params): void
    {
        $token = $params['token'] ?? '';
        $link  = SitterLink::findValidByToken($token);
        if (!$link) {
            http_response_code(404);
            require BASE_PATH . '/templates/sitter/view.php';
            return;
        }

        $familyId = (int)$link['family_id'];
        $family   = Family::findById($familyId);
        $today    = date('Y-m-d');

        // Event::getByFamily expects full datetime bounds (overlap check), not bare dates.
        $events = Event::getByFamily($familyId, $today . ' 00:00:00', $today . ' 23:59:59');

        $tasks = Database::fetchAll(
            "SELECT t.title, t.notes, t.priority, t.due_date, tl.name as list_name
             FROM tasks t JOIN task_lists tl ON tl.id=t.list_id
             WHERE tl.family_id=? AND t.is_completed=0
             ORDER BY t.due_date IS NULL, t.due_date, t.priority='high' DESC",
            [$familyId]
        );

        $cards = [];
        foreach (EmergencyCard::getByFamily($familyId) as $c) {
            $name = $c['subject_type'] === 'baby'
                ? (Baby::findById((int)$c['subject_id'])['name'] ?? '')
                : (User::findById((int)$c['subject_id'])['name'] ?? '');
            $cards[] = array_merge($c, ['subject_name' => $name]);
        }

        require BASE_PATH . '/templates/sitter/view.php';
    }

    private function originUrl(): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        return $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    }
}
