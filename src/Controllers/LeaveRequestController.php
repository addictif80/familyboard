<?php
namespace App\Controllers;

use App\Core\Session;
use App\Models\LeaveRequest;
use App\Models\User;

class LeaveRequestController extends BaseController
{
    public function index(array $params): void
    {
        $this->requireAuth();
        $this->requireModule('leave');
        $user = Session::user();
        $familyId = (int)$user['family_id'];
        $leaves = LeaveRequest::getByFamily($familyId);
        $members = array_values(array_filter(User::getByFamily($familyId), fn($m) => $m['role'] !== 'coparent'));
        require BASE_PATH . '/templates/leave/index.php';
    }

    public function create(array $params): void
    {
        $this->requireAuth();
        $this->json(function () {
            $user = Session::user();
            $familyId = (int)$user['family_id'];
            $d = $this->validated($this->jsonInput(), $familyId);
            if (!$d) return ['success' => false, 'error' => 'Membre et dates requis.'];
            $id = LeaveRequest::create($familyId, (int)$user['id'], $d);
            $overlaps = LeaveRequest::getOverlapping($familyId, $d['start_date'], $d['end_date'], $d['user_id'], $id);
            return ['success' => true, 'id' => $id, 'overlaps' => array_map(fn($o) => $o['user_name'], $overlaps)];
        });
    }

    public function delete(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $leave = LeaveRequest::getById((int)$params['id']);
            if (!$leave || (int)$leave['family_id'] !== (int)$user['family_id']) return ['success' => false];
            LeaveRequest::delete((int)$params['id'], (int)$user['family_id']);
            return ['success' => true];
        });
    }

    private function validated(array $data, int $familyId): ?array
    {
        $userId = (int)($data['user_id'] ?? 0);
        $member = $userId ? User::findById($userId) : null;
        if (!$member || (int)$member['family_id'] !== $familyId) return null;
        $start = trim($data['start_date'] ?? '');
        $end = trim($data['end_date'] ?? '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end) || $end < $start) return null;
        $type = array_key_exists($data['leave_type'] ?? '', LeaveRequest::TYPES) ? $data['leave_type'] : 'conges_payes';
        return [
            'user_id' => $userId,
            'leave_type' => $type,
            'title' => trim($data['title'] ?? '') ?: null,
            'start_date' => $start,
            'end_date' => $end,
            'notes' => trim($data['notes'] ?? '') ?: null,
        ];
    }
}
