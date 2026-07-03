<?php
namespace App\Controllers;

use App\Core\Session;
use App\Models\Baby;
use App\Models\EmergencyCard;
use App\Models\User;

class EmergencyController extends BaseController
{
    private const TYPES = ['user', 'baby'];

    public function index(array $params): void
    {
        $this->requireAuth();
        $this->requireModule('emergency');
        $user    = Session::user();
        $members = User::getByFamily($user['family_id']);
        $babies  = Baby::getAllByFamily($user['family_id']);
        $cards   = [];
        foreach (EmergencyCard::getByFamily($user['family_id']) as $c) {
            $cards[$c['subject_type'] . '_' . $c['subject_id']] = $c;
        }
        require BASE_PATH . '/templates/emergency/index.php';
    }

    public function save(array $params): void
    {
        $this->requireAuth();
        $this->json(function () {
            $user = Session::user();
            $data = $this->jsonInput();
            $type = $data['subject_type'] ?? '';
            $id   = (int)($data['subject_id'] ?? 0);

            if (!in_array($type, self::TYPES, true) || !$id || !$this->subjectBelongsToFamily($type, $id, $user['family_id'])) {
                return ['success' => false, 'error' => 'Sujet invalide.'];
            }

            $card = EmergencyCard::save($user['family_id'], $user['id'], $type, $id, $data);
            return ['success' => true, 'card' => $card];
        });
    }

    /** Public, unauthenticated read-only view — shared via QR code / link. */
    public function publicView(array $params): void
    {
        $token = $params['token'] ?? '';
        $card  = EmergencyCard::getByToken($token);
        if (!$card) {
            http_response_code(404);
            $subjectName = null;
            require BASE_PATH . '/templates/emergency/public.php';
            return;
        }

        $subjectName = $card['subject_type'] === 'baby'
            ? (Baby::findById((int)$card['subject_id'])['name'] ?? '')
            : (User::findById((int)$card['subject_id'])['name'] ?? '');

        require BASE_PATH . '/templates/emergency/public.php';
    }

    private function subjectBelongsToFamily(string $type, int $id, int $familyId): bool
    {
        if ($type === 'user') {
            $u = User::findById($id);
            return (bool)$u && $u['family_id'] === $familyId;
        }
        $b = Baby::findById($id);
        return (bool)$b && $b['family_id'] === $familyId;
    }
}
