<?php
namespace App\Controllers;

use App\Core\Session;
use App\Models\LoyaltyCard;

class LoyaltyCardController extends BaseController
{
    public function index(array $params): void
    {
        $this->requireAuth();
        $this->requireModule('loyalty_cards');
        $user = Session::user();
        $familyId = (int)$user['family_id'];
        $cards = LoyaltyCard::getByFamily($familyId);

        $selectedId = (int)($_GET['id'] ?? 0) ?: ($cards[0]['id'] ?? null);
        $selected = null;
        if ($selectedId) {
            $selected = LoyaltyCard::getById((int)$selectedId);
            if (!$selected || (int)$selected['family_id'] !== $familyId) $selected = null;
        }

        require BASE_PATH . '/templates/loyalty_cards/index.php';
    }

    public function create(array $params): void
    {
        $this->requireAuth();
        $this->json(function () {
            $user = Session::user();
            $d = $this->validated($this->jsonInput());
            if (!$d) return ['success' => false, 'error' => "Le nom du magasin et le code sont requis."];
            $id = LoyaltyCard::create((int)$user['family_id'], (int)$user['id'], $d);
            return ['success' => true, 'id' => $id];
        });
    }

    public function update(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $card = $this->owned((int)$params['id']);
            if (!$card) return ['success' => false, 'error' => 'Carte introuvable.'];
            $d = $this->validated($this->jsonInput());
            if (!$d) return ['success' => false, 'error' => "Le nom du magasin et le code sont requis."];
            LoyaltyCard::update((int)$params['id'], (int)$user['family_id'], $d);
            return ['success' => true];
        });
    }

    public function delete(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $card = $this->owned((int)$params['id']);
            if (!$card) return ['success' => false];
            LoyaltyCard::delete((int)$params['id'], (int)$user['family_id']);
            return ['success' => true];
        });
    }

    private function owned(int $id): ?array
    {
        $user = Session::user();
        $card = LoyaltyCard::getById($id);
        if (!$card || (int)$card['family_id'] !== (int)$user['family_id']) return null;
        return $card;
    }

    private function validated(array $data): ?array
    {
        $storeName = trim($data['store_name'] ?? '');
        $barcode   = trim($data['barcode_value'] ?? '');
        if ($storeName === '' || $barcode === '') return null;
        $format = array_key_exists($data['barcode_format'] ?? '', LoyaltyCard::FORMATS) ? $data['barcode_format'] : 'code128';
        $color  = trim($data['card_color'] ?? '');
        return [
            'store_name'     => $storeName,
            'card_color'     => preg_match('/^#[0-9a-fA-F]{6}$/', $color) ? $color : '#7C5CFF',
            'barcode_value'  => $barcode,
            'barcode_format' => $format,
            'notes'          => trim($data['notes'] ?? '') ?: null,
        ];
    }
}
