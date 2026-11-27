<?php
namespace App\Controllers;

use App\Core\Session;
use App\Models\FamilyChild;

/** Registre central "Enfants de la famille", géré depuis les réglages (onglet Famille) et
 *  consommé par les modules Suivi scolaire, Suivi nounou, Garde alternée et Bébé — voir
 *  App\Models\FamilyChild. */
class FamilyChildController extends BaseController
{
    public function create(array $params): void
    {
        $this->requireAuth();
        $this->json(function () {
            $user = Session::user();
            if ($user['role'] === 'coparent') return ['success' => false, 'error' => 'Accès refusé.'];
            $d = $this->validated($this->jsonInput());
            if (!$d) return ['success' => false, 'error' => 'Le nom est requis.'];
            $id = FamilyChild::create((int)$user['family_id'], (int)$user['id'], $d);
            return ['success' => true, 'id' => $id, 'child' => FamilyChild::getById($id)];
        });
    }

    public function update(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $child = $this->owned((int)$params['id']);
            if (!$child || $user['role'] === 'coparent') return ['success' => false, 'error' => 'Enfant introuvable.'];
            $d = $this->validated($this->jsonInput());
            if (!$d) return ['success' => false, 'error' => 'Le nom est requis.'];
            FamilyChild::update((int)$params['id'], (int)$user['family_id'], $d);
            return ['success' => true];
        });
    }

    public function delete(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $child = $this->owned((int)$params['id']);
            if (!$child || $user['role'] === 'coparent') return ['success' => false];
            FamilyChild::delete((int)$params['id'], (int)$user['family_id']);
            return ['success' => true];
        });
    }

    private function owned(int $id): ?array
    {
        $user = Session::user();
        $child = FamilyChild::getById($id);
        if (!$child || (int)$child['family_id'] !== (int)$user['family_id']) return null;
        return $child;
    }

    private function validated(array $data): ?array
    {
        $name = trim($data['name'] ?? '');
        if ($name === '') return null;
        $birthDate = trim($data['birth_date'] ?? '');
        return [
            'name' => $name,
            'color' => $this->safeColor($data['color'] ?? null),
            'birth_date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $birthDate) ? $birthDate : null,
        ];
    }
}
