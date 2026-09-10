<?php
namespace App\Controllers;

use App\Core\Session;
use App\Models\AdminProcedure;
use App\Models\Health;

class AdminProcedureController extends BaseController
{
    public function index(array $params): void
    {
        $this->requireAuth();
        $this->requireModule('admin_procedures');
        $user = Session::user();
        $familyId = (int)$user['family_id'];
        $procedures = AdminProcedure::getByFamily($familyId);
        $subjects = Health::getSubjects($familyId);
        require BASE_PATH . '/templates/admin_procedures/index.php';
    }

    public function create(array $params): void
    {
        $this->requireAuth();
        $this->json(function () {
            $user = Session::user();
            $d = $this->validated($this->jsonInput(), (int)$user['family_id']);
            if (!$d) return ['success' => false, 'error' => 'Sujet, titre et échéance requis.'];
            $id = AdminProcedure::create((int)$user['family_id'], (int)$user['id'], $d);
            return ['success' => true, 'id' => $id];
        });
    }

    public function toggleDone(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $proc = $this->owned((int)$params['id']);
            if (!$proc) return ['success' => false];
            AdminProcedure::toggleDone((int)$params['id'], (int)$user['family_id'], !empty($this->jsonInput()['done']));
            return ['success' => true];
        });
    }

    public function delete(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $proc = $this->owned((int)$params['id']);
            if (!$proc) return ['success' => false];
            AdminProcedure::delete((int)$params['id'], (int)$user['family_id']);
            return ['success' => true];
        });
    }

    private function owned(int $id): ?array
    {
        $user = Session::user();
        $proc = AdminProcedure::getById($id);
        if (!$proc || (int)$proc['family_id'] !== (int)$user['family_id']) return null;
        return $proc;
    }

    private function validated(array $data, int $familyId): ?array
    {
        $type = ($data['subject_type'] ?? '') === 'child' ? 'child' : (($data['subject_type'] ?? '') === 'user' ? 'user' : null);
        $subjectId = (int)($data['subject_id'] ?? 0);
        $title = trim($data['title'] ?? '');
        $deadline = trim($data['deadline_date'] ?? '');
        if (!$type || !$subjectId || $title === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $deadline)) return null;
        if (!Health::subjectLabel($familyId, $type, $subjectId)) return null;
        $procType = array_key_exists($data['procedure_type'] ?? '', AdminProcedure::TYPES) ? $data['procedure_type'] : 'autre';
        return [
            'subject_type' => $type,
            'subject_id' => $subjectId,
            'procedure_type' => $procType,
            'title' => $title,
            'deadline_date' => $deadline,
            'notes' => trim($data['notes'] ?? '') ?: null,
        ];
    }
}
