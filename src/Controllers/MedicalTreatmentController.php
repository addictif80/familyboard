<?php
namespace App\Controllers;

use App\Core\Session;
use App\Models\FamilyChild;
use App\Models\MedicalTreatment;

/** Suivi des traitements en cours — privé à chaque membre, ou géré par un admin au nom d'un
 *  enfant du registre partagé (voir docblock de MedicalTreatment). Jamais de vue "toute la
 *  famille" : chaque appel vérifie explicitement que l'appelant est bien le propriétaire du
 *  traitement (ou un admin, pour un traitement d'enfant) avant de lire/écrire quoi que ce soit. */
class MedicalTreatmentController extends BaseController
{
    public function index(array $params): void
    {
        $this->requireAuth();
        $this->requireModule('medication');
        $user = Session::user();
        $familyId = (int)$user['family_id'];

        $myTreatments = MedicalTreatment::getForUser($familyId, (int)$user['id']);

        $children = [];
        $childTreatments = [];
        if ($user['role'] === 'admin') {
            $children = FamilyChild::getByFamily($familyId);
            foreach ($children as $c) {
                $childTreatments[$c['id']] = MedicalTreatment::getForChild($familyId, (int)$c['id']);
            }
        }

        require BASE_PATH . '/templates/medication/index.php';
    }

    public function create(array $params): void
    {
        $this->requireAuth();
        $this->requireModule('medication');
        $this->json(function () {
            $user = Session::user();
            $data = $_POST ?: $this->jsonInput();
            [$ownerType, $ownerUserId, $ownerChildId, $error] = $this->resolveOwner($user, $data);
            if ($error) return ['success' => false, 'error' => $error];

            $payload = $this->buildPayload($data, $ownerType, $ownerUserId, $ownerChildId);
            if (!$payload) return ['success' => false, 'error' => 'Nom, posologie et date de début requis.'];

            $id = MedicalTreatment::create((int)$user['family_id'], (int)$user['id'], $payload);
            return ['success' => true, 'id' => $id];
        });
    }

    public function update(array $params): void
    {
        $this->requireAuth();
        $this->requireModule('medication');
        $this->json(function () use ($params) {
            $user = Session::user();
            $treatment = $this->authorizedTreatment((int)$params['id'], $user);
            if (!$treatment) return ['success' => false, 'error' => 'Traitement introuvable.'];

            $data = $_POST ?: $this->jsonInput();
            $payload = $this->buildPayload($data, $treatment['owner_type'], $treatment['owner_user_id'], $treatment['owner_child_id']);
            if (!$payload) return ['success' => false, 'error' => 'Nom, posologie et date de début requis.'];
            $payload['active'] = isset($data['active']) ? (bool)$data['active'] : (bool)$treatment['active'];

            MedicalTreatment::update((int)$params['id'], $payload);
            return ['success' => true];
        });
    }

    public function delete(array $params): void
    {
        $this->requireAuth();
        $this->requireModule('medication');
        $this->json(function () use ($params) {
            $user = Session::user();
            $treatment = $this->authorizedTreatment((int)$params['id'], $user);
            if (!$treatment) return ['success' => false, 'error' => 'Traitement introuvable.'];
            MedicalTreatment::delete((int)$params['id']);
            return ['success' => true];
        });
    }

    /** Un membre ne peut agir que sur son propre traitement (owner_type='user') ; un traitement
     *  d'enfant (owner_type='child') n'est accessible qu'à un admin de la même famille. */
    private function authorizedTreatment(int $id, array $user): ?array
    {
        $treatment = MedicalTreatment::getById($id);
        if (!$treatment || (int)$treatment['family_id'] !== (int)$user['family_id']) return null;
        if ($treatment['owner_type'] === 'user') {
            return (int)$treatment['owner_user_id'] === (int)$user['id'] ? $treatment : null;
        }
        return $user['role'] === 'admin' ? $treatment : null;
    }

    /** @return array{0:string,1:?int,2:?int,3:?string} [owner_type, owner_user_id, owner_child_id, error] */
    private function resolveOwner(array $user, array $data): array
    {
        $childId = (int)($data['owner_child_id'] ?? 0);
        if ($childId) {
            if ($user['role'] !== 'admin') return ['', null, null, 'Seul un administrateur peut renseigner le traitement d\'un enfant.'];
            $child = FamilyChild::getById($childId);
            if (!$child || (int)$child['family_id'] !== (int)$user['family_id']) {
                return ['', null, null, 'Enfant introuvable.'];
            }
            return ['child', null, $childId, null];
        }
        return ['user', (int)$user['id'], null, null];
    }

    private function buildPayload(array $data, string $ownerType, ?int $ownerUserId, ?int $ownerChildId): ?array
    {
        $name = trim($data['name'] ?? '');
        $dosage = trim($data['dosage'] ?? '');
        $startDate = trim($data['start_date'] ?? '');
        if (!$name || !$dosage || !$startDate) return null;

        $scheduleType = ($data['schedule_type'] ?? 'fixed') === 'interval' ? 'interval' : 'fixed';
        $fixedTimes = null;
        $intervalHours = null;
        $intervalAnchor = null;

        if ($scheduleType === 'fixed') {
            $raw = $data['fixed_times'] ?? [];
            if (is_string($raw)) $raw = json_decode($raw, true) ?: [];
            $fixedTimes = array_values(array_filter(array_map(function ($t) {
                $t = trim((string)$t);
                return preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $t) ? $t : null;
            }, (array)$raw)));
            if (!$fixedTimes) return null;
        } else {
            $intervalHours = max(1, (int)($data['interval_hours'] ?? 0));
            $intervalAnchor = trim($data['interval_anchor'] ?? '') ?: date('Y-m-d H:i:s');
        }

        return [
            'owner_type'      => $ownerType,
            'owner_user_id'   => $ownerUserId,
            'owner_child_id'  => $ownerChildId,
            'name'            => $name,
            'dosage'          => $dosage,
            'notes'           => trim($data['notes'] ?? ''),
            'start_date'      => $startDate,
            'end_date'        => trim($data['end_date'] ?? '') ?: null,
            'schedule_type'   => $scheduleType,
            'fixed_times'     => $fixedTimes,
            'interval_hours'  => $intervalHours,
            'interval_anchor' => $intervalAnchor,
            'active'          => true,
        ];
    }
}
