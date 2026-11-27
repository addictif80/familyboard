<?php
namespace App\Controllers;

use App\Core\Session;
use App\Models\Vehicle;

class VehicleController extends BaseController
{
    public function index(array $params): void
    {
        $this->requireAuth();
        $this->requireModule('vehicles');
        $user = Session::user();
        $familyId = (int)$user['family_id'];
        $vehicles = Vehicle::getByFamily($familyId);

        $selectedId = (int)($_GET['id'] ?? 0) ?: ($vehicles[0]['id'] ?? null);
        $selected = null;
        $maintenance = [];
        if ($selectedId) {
            $selected = Vehicle::getById((int)$selectedId);
            if (!$selected || (int)$selected['family_id'] !== $familyId) $selected = null;
            if ($selected) $maintenance = Vehicle::getMaintenance((int)$selected['id']);
        }

        require BASE_PATH . '/templates/vehicles/index.php';
    }

    public function create(array $params): void
    {
        $this->requireAuth();
        $this->json(function () {
            $user = Session::user();
            $d = $this->validated($this->jsonInput());
            if (!$d) return ['success' => false, 'error' => 'Le nom est requis.'];
            $id = Vehicle::create((int)$user['family_id'], (int)$user['id'], $d);
            return ['success' => true, 'id' => $id];
        });
    }

    public function update(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $vehicle = $this->owned((int)$params['id']);
            if (!$vehicle) return ['success' => false, 'error' => 'Véhicule introuvable.'];
            $d = $this->validated($this->jsonInput());
            if (!$d) return ['success' => false, 'error' => 'Le nom est requis.'];
            Vehicle::update((int)$params['id'], (int)$user['family_id'], $d);
            return ['success' => true];
        });
    }

    public function delete(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $vehicle = $this->owned((int)$params['id']);
            if (!$vehicle) return ['success' => false];
            Vehicle::delete((int)$params['id'], (int)$user['family_id']);
            return ['success' => true];
        });
    }

    public function addMaintenance(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $vehicle = $this->owned((int)$params['id']);
            if (!$vehicle) return ['success' => false, 'error' => 'Véhicule introuvable.'];
            $data = $this->jsonInput();
            $title = trim($data['title'] ?? '');
            $doneAt = trim($data['done_at'] ?? '');
            if ($title === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $doneAt)) {
                return ['success' => false, 'error' => 'Titre et date requis.'];
            }
            $cost = trim((string)($data['cost'] ?? ''));
            $id = Vehicle::addMaintenance((int)$vehicle['id'], (int)$user['id'], [
                'title' => $title,
                'done_at' => $doneAt,
                'mileage' => is_numeric($data['mileage'] ?? null) ? (int)$data['mileage'] : null,
                'cost_cents' => $cost !== '' && is_numeric(str_replace(',', '.', $cost)) ? (int)round((float)str_replace(',', '.', $cost) * 100) : null,
                'notes' => trim($data['notes'] ?? '') ?: null,
            ]);
            return ['success' => true, 'id' => $id];
        });
    }

    public function deleteMaintenance(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $vehicle = $this->owned((int)$params['id']);
            if (!$vehicle) return ['success' => false];
            Vehicle::deleteMaintenance((int)$params['maintenanceId'], (int)$vehicle['id']);
            return ['success' => true];
        });
    }

    private function owned(int $id): ?array
    {
        $user = Session::user();
        $vehicle = Vehicle::getById($id);
        if (!$vehicle || (int)$vehicle['family_id'] !== (int)$user['family_id']) return null;
        return $vehicle;
    }

    private function validated(array $data): ?array
    {
        $name = trim($data['name'] ?? '');
        if ($name === '') return null;
        $purchase = trim($data['purchase_date'] ?? '');
        $insuranceExpiry = trim($data['insurance_expiry'] ?? '');
        $ctExpiry = trim($data['technical_control_expiry'] ?? '');
        return [
            'name' => $name,
            'brand' => trim($data['brand'] ?? '') ?: null,
            'model' => trim($data['model'] ?? '') ?: null,
            'plate' => trim($data['plate'] ?? '') ?: null,
            'purchase_date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $purchase) ? $purchase : null,
            'mileage' => is_numeric($data['mileage'] ?? null) ? (int)$data['mileage'] : null,
            'insurance_company' => trim($data['insurance_company'] ?? '') ?: null,
            'insurance_expiry' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $insuranceExpiry) ? $insuranceExpiry : null,
            'technical_control_expiry' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $ctExpiry) ? $ctExpiry : null,
            'notes' => trim($data['notes'] ?? '') ?: null,
        ];
    }
}
