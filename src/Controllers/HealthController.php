<?php
namespace App\Controllers;

use App\Core\Session;
use App\Models\FamilyChild;
use App\Models\Health;

class HealthController extends BaseController
{
    public function index(array $params): void
    {
        $this->requireAuth();
        $this->requireModule('health');
        $user = Session::user();
        $familyId = (int)$user['family_id'];

        $subjects = Health::getSubjects($familyId);
        $entries = Health::getEntries($familyId);
        $doctors = Health::getDoctors($familyId);
        $children = FamilyChild::getByFamily($familyId);
        $selectedChildId = (int)($_GET['child_id'] ?? 0) ?: ($children[0]['id'] ?? null);
        $growth = $selectedChildId ? Health::getGrowth($familyId, (int)$selectedChildId) : [];

        require BASE_PATH . '/templates/health/index.php';
    }

    // ── Carnet de santé ──────────────────────────────────────────

    public function addEntry(array $params): void
    {
        $this->requireAuth();
        $this->json(function () {
            $user = Session::user();
            $d = $this->validatedEntry($this->jsonInput(), (int)$user['family_id']);
            if (!$d) return ['success' => false, 'error' => 'Sujet, titre et type requis.'];
            $id = Health::addEntry((int)$user['family_id'], (int)$user['id'], $d);
            return ['success' => true, 'id' => $id];
        });
    }

    public function deleteEntry(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $entry = Health::getEntryById((int)$params['id']);
            if (!$entry || (int)$entry['family_id'] !== (int)$user['family_id']) return ['success' => false];
            Health::deleteEntry((int)$params['id'], (int)$user['family_id']);
            return ['success' => true];
        });
    }

    // ── Croissance ───────────────────────────────────────────────

    public function addGrowth(array $params): void
    {
        $this->requireAuth();
        $this->json(function () {
            $user = Session::user();
            $familyId = (int)$user['family_id'];
            $data = $this->jsonInput();
            $childId = (int)($data['child_id'] ?? 0);
            $child = $childId ? FamilyChild::getById($childId) : null;
            if (!$child || (int)$child['family_id'] !== $familyId) return ['success' => false, 'error' => 'Enfant invalide.'];
            $date = trim($data['measured_at'] ?? '');
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) return ['success' => false, 'error' => 'Date requise.'];
            $id = Health::addGrowth($familyId, (int)$user['id'], [
                'child_id' => $childId,
                'measured_at' => $date,
                'height_cm' => is_numeric($data['height_cm'] ?? null) ? (float)$data['height_cm'] : null,
                'weight_kg' => is_numeric($data['weight_kg'] ?? null) ? (float)$data['weight_kg'] : null,
                'notes' => trim($data['notes'] ?? '') ?: null,
            ]);
            return ['success' => true, 'id' => $id];
        });
    }

    public function deleteGrowth(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $entry = Health::getGrowthEntryById((int)$params['id']);
            if (!$entry || (int)$entry['family_id'] !== (int)$user['family_id']) return ['success' => false];
            Health::deleteGrowth((int)$params['id'], (int)$user['family_id']);
            return ['success' => true];
        });
    }

    // ── Carnet médical ───────────────────────────────────────────

    public function addDoctor(array $params): void
    {
        $this->requireAuth();
        $this->json(function () {
            $user = Session::user();
            $d = $this->validatedDoctor($this->jsonInput(), (int)$user['family_id']);
            if (!$d) return ['success' => false, 'error' => 'Sujet et nom requis.'];
            $id = Health::addDoctor((int)$user['family_id'], (int)$user['id'], $d);
            return ['success' => true, 'id' => $id];
        });
    }

    public function updateDoctor(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $doctor = Health::getDoctorById((int)$params['id']);
            if (!$doctor || (int)$doctor['family_id'] !== (int)$user['family_id']) return ['success' => false, 'error' => 'Introuvable.'];
            $d = $this->validatedDoctor($this->jsonInput(), (int)$user['family_id']);
            if (!$d) return ['success' => false, 'error' => 'Sujet et nom requis.'];
            Health::updateDoctor((int)$params['id'], (int)$user['family_id'], $d);
            return ['success' => true];
        });
    }

    public function deleteDoctor(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $doctor = Health::getDoctorById((int)$params['id']);
            if (!$doctor || (int)$doctor['family_id'] !== (int)$user['family_id']) return ['success' => false];
            Health::deleteDoctor((int)$params['id'], (int)$user['family_id']);
            return ['success' => true];
        });
    }

    // ── Helpers ──────────────────────────────────────────────────

    private function validSubject(array $data, int $familyId): ?array
    {
        $type = ($data['subject_type'] ?? '') === 'child' ? 'child' : (($data['subject_type'] ?? '') === 'user' ? 'user' : null);
        $id = (int)($data['subject_id'] ?? 0);
        if (!$type || !$id) return null;
        $subject = Health::subjectLabel($familyId, $type, $id);
        return $subject ? ['subject_type' => $type, 'subject_id' => $id] : null;
    }

    private function validatedEntry(array $data, int $familyId): ?array
    {
        $subject = $this->validSubject($data, $familyId);
        $title = trim($data['title'] ?? '');
        if (!$subject || $title === '') return null;
        $entryType = array_key_exists($data['entry_type'] ?? '', Health::ENTRY_TYPES) ? $data['entry_type'] : 'autre';
        $entryDate = trim($data['entry_date'] ?? '');
        $reminderDate = trim($data['reminder_date'] ?? '');
        return $subject + [
            'entry_type' => $entryType,
            'title' => $title,
            'description' => trim($data['description'] ?? '') ?: null,
            'entry_date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $entryDate) ? $entryDate : null,
            'reminder_date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $reminderDate) ? $reminderDate : null,
        ];
    }

    private function validatedDoctor(array $data, int $familyId): ?array
    {
        $subject = $this->validSubject($data, $familyId);
        $name = trim($data['name'] ?? '');
        if (!$subject || $name === '') return null;
        $nextAppt = trim($data['next_appointment'] ?? '');
        $renewal = trim($data['prescription_renewal_date'] ?? '');
        return $subject + [
            'name' => $name,
            'specialty' => trim($data['specialty'] ?? '') ?: null,
            'phone' => trim($data['phone'] ?? '') ?: null,
            'address' => trim($data['address'] ?? '') ?: null,
            'next_appointment' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $nextAppt) ? $nextAppt : null,
            'prescription_renewal_date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $renewal) ? $renewal : null,
            'notes' => trim($data['notes'] ?? '') ?: null,
        ];
    }
}
