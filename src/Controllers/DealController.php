<?php
namespace App\Controllers;

use App\Core\Session;
use App\Models\Deal;

class DealController extends BaseController
{
    public function index(array $params): void
    {
        $this->requireAuth();
        $this->requireModule('deals');
        $user = Session::user();
        $deals = Deal::getByFamily((int)$user['family_id']);
        require BASE_PATH . '/templates/deals/index.php';
    }

    public function create(array $params): void
    {
        $this->requireAuth();
        $this->json(function () {
            $user = Session::user();
            $d = $this->validated($this->jsonInput());
            if (!$d) return ['success' => false, 'error' => 'Le titre est requis.'];
            $id = Deal::create((int)$user['family_id'], (int)$user['id'], $d);
            return ['success' => true, 'id' => $id];
        });
    }

    public function update(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $deal = $this->owned((int)$params['id']);
            if (!$deal) return ['success' => false, 'error' => 'Bon plan introuvable.'];
            $d = $this->validated($this->jsonInput());
            if (!$d) return ['success' => false, 'error' => 'Le titre est requis.'];
            Deal::update((int)$params['id'], (int)$user['family_id'], $d);
            return ['success' => true];
        });
    }

    public function delete(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $deal = $this->owned((int)$params['id']);
            if (!$deal) return ['success' => false];
            Deal::delete((int)$params['id'], (int)$user['family_id']);
            return ['success' => true];
        });
    }

    private function owned(int $id): ?array
    {
        $user = Session::user();
        $deal = Deal::getById($id);
        if (!$deal || (int)$deal['family_id'] !== (int)$user['family_id']) return null;
        return $deal;
    }

    private function validated(array $data): ?array
    {
        $title = trim($data['title'] ?? '');
        if ($title === '') return null;
        $category = array_key_exists($data['category'] ?? '', Deal::CATEGORIES) ? $data['category'] : 'autre';
        $url = trim($data['url'] ?? '');
        if ($url !== '' && !preg_match('#^https?://#i', $url)) $url = 'https://' . $url;
        $expiresAt = trim($data['expires_at'] ?? '');
        return [
            'title' => $title,
            'description' => trim($data['description'] ?? '') ?: null,
            'url' => $url ?: null,
            'promo_code' => trim($data['promo_code'] ?? '') ?: null,
            'discount' => trim($data['discount'] ?? '') ?: null,
            'category' => $category,
            'expires_at' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $expiresAt) ? $expiresAt : null,
        ];
    }
}
