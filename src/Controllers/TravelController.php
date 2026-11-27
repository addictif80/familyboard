<?php
namespace App\Controllers;

use App\Core\Session;
use App\Models\Travel;

class TravelController extends BaseController
{
    public function index(array $params): void
    {
        $this->requireAuth();
        $this->requireModule('travels');
        $user = Session::user();
        $familyId = (int)$user['family_id'];
        $travels = Travel::getByFamily($familyId);

        $selectedId = (int)($_GET['id'] ?? 0) ?: ($travels[0]['id'] ?? null);
        $selected = null;
        $reservations = [];
        if ($selectedId) {
            $selected = Travel::getById((int)$selectedId);
            if (!$selected || (int)$selected['family_id'] !== $familyId) $selected = null;
            if ($selected) $reservations = Travel::getReservations((int)$selected['id']);
        }

        require BASE_PATH . '/templates/travels/index.php';
    }

    public function create(array $params): void
    {
        $this->requireAuth();
        $this->json(function () {
            $user = Session::user();
            $d = $this->validated($this->jsonInput());
            if (!$d) return ['success' => false, 'error' => 'Le titre est requis.'];
            $id = Travel::create((int)$user['family_id'], (int)$user['id'], $d);
            return ['success' => true, 'id' => $id];
        });
    }

    public function update(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $travel = $this->owned((int)$params['id']);
            if (!$travel) return ['success' => false, 'error' => 'Voyage introuvable.'];
            $d = $this->validated($this->jsonInput());
            if (!$d) return ['success' => false, 'error' => 'Le titre est requis.'];
            Travel::update((int)$params['id'], (int)$user['family_id'], $d);
            return ['success' => true];
        });
    }

    public function delete(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $travel = $this->owned((int)$params['id']);
            if (!$travel) return ['success' => false];
            Travel::delete((int)$params['id'], (int)$user['family_id']);
            return ['success' => true];
        });
    }

    public function addReservation(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $travel = $this->owned((int)$params['id']);
            if (!$travel) return ['success' => false, 'error' => 'Voyage introuvable.'];
            $data = $this->jsonInput();
            $title = trim($data['title'] ?? '');
            if ($title === '') return ['success' => false, 'error' => 'Titre requis.'];
            $type = array_key_exists($data['type'] ?? '', Travel::RESERVATION_TYPES) ? $data['type'] : 'autre';
            $cost = trim((string)($data['cost'] ?? ''));
            $resDate = trim($data['reservation_date'] ?? '');
            $id = Travel::addReservation((int)$travel['id'], (int)$user['id'], [
                'type' => $type,
                'title' => $title,
                'confirmation_number' => trim($data['confirmation_number'] ?? '') ?: null,
                'cost_cents' => $cost !== '' && is_numeric(str_replace(',', '.', $cost)) ? (int)round((float)str_replace(',', '.', $cost) * 100) : null,
                'reservation_date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $resDate) ? $resDate : null,
                'notes' => trim($data['notes'] ?? '') ?: null,
            ]);
            return ['success' => true, 'id' => $id];
        });
    }

    public function deleteReservation(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $travel = $this->owned((int)$params['id']);
            if (!$travel) return ['success' => false];
            Travel::deleteReservation((int)$params['reservationId'], (int)$travel['id']);
            return ['success' => true];
        });
    }

    private function owned(int $id): ?array
    {
        $user = Session::user();
        $travel = Travel::getById($id);
        if (!$travel || (int)$travel['family_id'] !== (int)$user['family_id']) return null;
        return $travel;
    }

    private function validated(array $data): ?array
    {
        $title = trim($data['title'] ?? '');
        if ($title === '') return null;
        $start = trim($data['start_date'] ?? '');
        $end = trim($data['end_date'] ?? '');
        $budget = trim((string)($data['budget'] ?? ''));
        return [
            'title' => $title,
            'destination' => trim($data['destination'] ?? '') ?: null,
            'start_date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) ? $start : null,
            'end_date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $end) ? $end : null,
            'budget_cents' => $budget !== '' && is_numeric(str_replace(',', '.', $budget)) ? (int)round((float)str_replace(',', '.', $budget) * 100) : null,
            'notes' => trim($data['notes'] ?? '') ?: null,
        ];
    }
}
