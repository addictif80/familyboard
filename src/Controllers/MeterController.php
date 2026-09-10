<?php
namespace App\Controllers;

use App\Core\Session;
use App\Models\Meter;

class MeterController extends BaseController
{
    public function index(array $params): void
    {
        $this->requireAuth();
        $this->requireModule('meters');
        $user = Session::user();
        $familyId = (int)$user['family_id'];
        $meters = Meter::getByFamily($familyId);

        $selectedId = (int)($_GET['id'] ?? 0) ?: ($meters[0]['id'] ?? null);
        $selected = null;
        $series = [];
        if ($selectedId) {
            $selected = Meter::getById((int)$selectedId);
            if (!$selected || (int)$selected['family_id'] !== $familyId) $selected = null;
            if ($selected) $series = Meter::consumptionSeries(Meter::getReadings((int)$selected['id']));
        }

        require BASE_PATH . '/templates/meters/index.php';
    }

    public function create(array $params): void
    {
        $this->requireAuth();
        $this->json(function () {
            $user = Session::user();
            $d = $this->validated($this->jsonInput());
            if (!$d) return ['success' => false, 'error' => 'Le nom est requis.'];
            $id = Meter::create((int)$user['family_id'], (int)$user['id'], $d);
            return ['success' => true, 'id' => $id];
        });
    }

    public function update(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $meter = $this->owned((int)$params['id']);
            if (!$meter) return ['success' => false, 'error' => 'Compteur introuvable.'];
            $d = $this->validated($this->jsonInput());
            if (!$d) return ['success' => false, 'error' => 'Le nom est requis.'];
            Meter::update((int)$params['id'], (int)$user['family_id'], $d);
            return ['success' => true];
        });
    }

    public function delete(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $meter = $this->owned((int)$params['id']);
            if (!$meter) return ['success' => false];
            Meter::delete((int)$params['id'], (int)$user['family_id']);
            return ['success' => true];
        });
    }

    public function addReading(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $meter = $this->owned((int)$params['id']);
            if (!$meter) return ['success' => false, 'error' => 'Compteur introuvable.'];
            $data = $this->jsonInput();
            $date = trim($data['reading_at'] ?? '');
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                return ['success' => false, 'error' => 'Date requise.'];
            }

            if (!empty($meter['has_hp_hc'])) {
                $valueHp = is_numeric($data['value_hp'] ?? null) ? (float)$data['value_hp'] : null;
                $valueHc = is_numeric($data['value_hc'] ?? null) ? (float)$data['value_hc'] : null;
                if ($valueHp === null || $valueHc === null) {
                    return ['success' => false, 'error' => 'Index heures pleines et heures creuses requis.'];
                }
                $value = null;
            } else {
                $value = is_numeric($data['value'] ?? null) ? (float)$data['value'] : null;
                if ($value === null) {
                    return ['success' => false, 'error' => 'Index requis.'];
                }
                $valueHp = $valueHc = null;
            }

            $id = Meter::addReading((int)$meter['id'], (int)$user['id'], [
                'reading_at' => $date,
                'value' => $value,
                'value_hp' => $valueHp,
                'value_hc' => $valueHc,
                'notes' => trim($data['notes'] ?? '') ?: null,
            ]);
            return ['success' => true, 'id' => $id];
        });
    }

    public function deleteReading(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $meter = $this->owned((int)$params['id']);
            if (!$meter) return ['success' => false];
            Meter::deleteReading((int)$params['readingId'], (int)$meter['id']);
            return ['success' => true];
        });
    }

    private function owned(int $id): ?array
    {
        $user = Session::user();
        $meter = Meter::getById($id);
        if (!$meter || (int)$meter['family_id'] !== (int)$user['family_id']) return null;
        return $meter;
    }

    private function validated(array $data): ?array
    {
        $name = trim($data['name'] ?? '');
        if ($name === '') return null;
        $type = array_key_exists($data['meter_type'] ?? '', Meter::TYPES) ? $data['meter_type'] : 'autre';
        $unit = trim($data['unit'] ?? '') ?: Meter::TYPES[$type]['unit'];
        return [
            'name' => $name,
            'meter_type' => $type,
            // Heures pleines/heures creuses n'a de sens que pour l'électricité — ignoré sans
            // avertissement pour tout autre type plutôt que de rejeter la requête, au cas où le
            // client enverrait encore ce champ après un changement de type côté formulaire.
            'has_hp_hc' => ($type === 'electricite' && !empty($data['has_hp_hc'])) ? 1 : 0,
            'unit' => $unit,
            'provider' => trim($data['provider'] ?? '') ?: null,
            'contract_ref' => trim($data['contract_ref'] ?? '') ?: null,
        ];
    }
}
