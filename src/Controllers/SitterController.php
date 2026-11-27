<?php
namespace App\Controllers;

use App\Core\Database;
use App\Core\Session;
use App\Models\Baby;
use App\Models\Contact;
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
            $instructions = trim($data['instructions'] ?? '') ?: null;
            // sitter_links.expires_at is compared against SQL NOW(), which the DB
            // connection forces to UTC — must use gmdate(), not the family's local date().
            $expiresAt = gmdate('Y-m-d H:i:s', time() + $hours * 3600);

            $link = SitterLink::create($user['family_id'], $user['id'], $label, $expiresAt, $instructions);
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

        // Contenu volontairement restreint (pas le planning ni les tâches de toute la famille) :
        // ce que ça prend concrètement pour garder les enfants — contacts des parents, secours,
        // consignes laissées à la création de l'accès, et l'état du suivi bébé en temps réel.
        $parents = array_values(array_filter(
            User::getByFamily($familyId),
            fn($u) => $u['role'] !== 'coparent'
        ));

        $emergencyContacts = array_values(array_filter(
            Contact::getByFamily($familyId),
            fn($c) => !empty($c['is_system'])
        ));

        $babies = [];
        foreach (Baby::getAllByFamily($familyId) as $baby) {
            $lastEvents = Baby::getLastEventTimes((int)$baby['id']);
            $activeSleep = Baby::getActiveSleep((int)$baby['id']);
            $lastSleep = null;
            if (!$activeSleep) {
                $recent = Baby::getRecentSleeps((int)$baby['id'], 3);
                $lastSleep = $recent[0] ?? null;
            }
            $babies[] = [
                'baby'        => $baby,
                'last_events' => $lastEvents,
                'active_sleep'=> $activeSleep,
                'last_sleep'  => $lastSleep,
            ];
        }

        require BASE_PATH . '/templates/sitter/view.php';
    }

    private function originUrl(): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        return $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    }
}
