<?php
namespace App\Controllers;

use App\Core\Session;
use App\Models\Pet;

class PetController extends BaseController
{
    public function index(array $params): void
    {
        $this->requireAuth();
        $this->requireModule('pets');
        $user = Session::user();
        $familyId = (int)$user['family_id'];
        $pets = Pet::getByFamily($familyId);

        $selectedId = (int)($_GET['id'] ?? 0) ?: ($pets[0]['id'] ?? null);
        $selected = null;
        $careEntries = [];
        if ($selectedId) {
            $selected = Pet::getById((int)$selectedId);
            if (!$selected || (int)$selected['family_id'] !== $familyId) $selected = null;
            if ($selected) $careEntries = Pet::getCareEntries((int)$selected['id']);
        }

        require BASE_PATH . '/templates/pets/index.php';
    }

    public function create(array $params): void
    {
        $this->requireAuth();
        $this->json(function () {
            $user = Session::user();
            $d = $this->validated($this->jsonInput());
            if (!$d) return ['success' => false, 'error' => 'Le nom est requis.'];
            $id = Pet::create((int)$user['family_id'], (int)$user['id'], $d);
            return ['success' => true, 'id' => $id];
        });
    }

    public function update(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $pet = $this->owned((int)$params['id']);
            if (!$pet) return ['success' => false, 'error' => 'Animal introuvable.'];
            $d = $this->validated($this->jsonInput());
            if (!$d) return ['success' => false, 'error' => 'Le nom est requis.'];
            Pet::update((int)$params['id'], (int)$user['family_id'], $d);
            return ['success' => true];
        });
    }

    public function delete(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $pet = $this->owned((int)$params['id']);
            if (!$pet) return ['success' => false];
            Pet::delete((int)$params['id'], (int)$user['family_id']);
            return ['success' => true];
        });
    }

    public function addCareEntry(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $pet = $this->owned((int)$params['id']);
            if (!$pet) return ['success' => false, 'error' => 'Animal introuvable.'];
            $data = $this->jsonInput();
            $title = trim($data['title'] ?? '');
            $doneAt = trim($data['done_at'] ?? '');
            if ($title === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $doneAt)) {
                return ['success' => false, 'error' => 'Titre et date requis.'];
            }
            $entryType = array_key_exists($data['entry_type'] ?? '', Pet::CARE_TYPES) ? $data['entry_type'] : 'autre';
            $reminder = trim($data['reminder_date'] ?? '');
            $id = Pet::addCareEntry((int)$pet['id'], (int)$user['id'], [
                'entry_type' => $entryType,
                'title' => $title,
                'done_at' => $doneAt,
                'reminder_date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $reminder) ? $reminder : null,
                'notes' => trim($data['notes'] ?? '') ?: null,
            ]);
            return ['success' => true, 'id' => $id];
        });
    }

    public function deleteCareEntry(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $pet = $this->owned((int)$params['id']);
            if (!$pet) return ['success' => false];
            Pet::deleteCareEntry((int)$params['careId'], (int)$pet['id']);
            return ['success' => true];
        });
    }

    private function owned(int $id): ?array
    {
        $user = Session::user();
        $pet = Pet::getById($id);
        if (!$pet || (int)$pet['family_id'] !== (int)$user['family_id']) return null;
        return $pet;
    }

    private function validated(array $data): ?array
    {
        $name = trim($data['name'] ?? '');
        if ($name === '') return null;
        $birthDate = trim($data['birth_date'] ?? '');
        return [
            'name' => $name,
            'species' => trim($data['species'] ?? '') ?: null,
            'breed' => trim($data['breed'] ?? '') ?: null,
            'birth_date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $birthDate) ? $birthDate : null,
            'vet_name' => trim($data['vet_name'] ?? '') ?: null,
            'vet_phone' => trim($data['vet_phone'] ?? '') ?: null,
            'notes' => trim($data['notes'] ?? '') ?: null,
        ];
    }
}
