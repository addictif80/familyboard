<?php
namespace App\Controllers;

use App\Core\Session;
use App\Models\StockItem;

class StockController extends BaseController
{
    public function index(array $params): void
    {
        $this->requireAuth();
        $this->requireModule('stock');
        $user = Session::user();
        $familyId = $user['family_id'];
        $location = in_array($_GET['location'] ?? '', ['fridge', 'freezer'], true) ? $_GET['location'] : '';
        $search = trim($_GET['q'] ?? '');
        $items = StockItem::getByFamily($familyId, $location, $search);
        require BASE_PATH . '/templates/stock/index.php';
    }

    public function create(array $params): void
    {
        $this->requireAuth();
        $this->json(function () {
            $user = Session::user();
            $data = $this->jsonInput();
            if (trim($data['name'] ?? '') === '') return ['success' => false, 'error' => 'Le nom est requis.'];
            $id = StockItem::create($user['family_id'], $user['id'], $data);
            return ['success' => true, 'item' => StockItem::findById($id, $user['family_id'])];
        });
    }

    public function update(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $id = (int)$params['id'];
            if (!StockItem::findById($id, $user['family_id'])) return ['success' => false, 'error' => 'Introuvable.'];
            $data = $this->jsonInput();
            if (trim($data['name'] ?? '') === '') return ['success' => false, 'error' => 'Le nom est requis.'];
            StockItem::update($id, $user['family_id'], $data);
            return ['success' => true, 'item' => StockItem::findById($id, $user['family_id'])];
        });
    }

    public function delete(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            StockItem::delete((int)$params['id'], $user['family_id']);
            return ['success' => true];
        });
    }

    public function adjust(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $delta = (int)($this->jsonInput()['delta'] ?? 0);
            $delta = max(-1, min(1, $delta));
            $newQty = StockItem::adjustQuantity((int)$params['id'], $user['family_id'], $delta);
            if ($newQty === null) return ['success' => false, 'error' => 'Introuvable.'];
            return ['success' => true, 'quantity' => $newQty];
        });
    }
}
