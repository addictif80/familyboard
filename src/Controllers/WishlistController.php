<?php
namespace App\Controllers;

use App\Core\Session;
use App\Models\User;
use App\Models\WishlistItem;

class WishlistController extends BaseController
{
    public function index(array $params): void
    {
        $this->requireAuth();
        $this->requireModule('wishlist');
        $user = Session::user();

        $members = User::getByFamily($user['family_id']);
        $items = array_map(
            fn($i) => WishlistItem::scrubForViewer($i, (int)$user['id']),
            WishlistItem::getByFamily($user['family_id'])
        );

        require BASE_PATH . '/templates/wishlist/index.php';
    }

    public function create(array $params): void
    {
        $this->requireAuth();
        $this->json(function () {
            $user = Session::user();
            $data = $this->jsonInput();
            if (empty($data['title'])) {
                return ['success' => false, 'error' => 'Titre requis.'];
            }
            $id = WishlistItem::create($user['family_id'], (int)$user['id'], $data);
            return ['success' => true, 'item' => WishlistItem::scrubForViewer(WishlistItem::getById($id), (int)$user['id'])];
        });
    }

    public function update(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $item = WishlistItem::getById((int)$params['id']);
            // Seul le propriétaire peut modifier le contenu de son propre souhait.
            if (!$item || $item['family_id'] !== $user['family_id'] || (int)$item['user_id'] !== (int)$user['id']) {
                return ['success' => false, 'error' => 'Souhait introuvable.'];
            }
            $data = $this->jsonInput();
            if (empty($data['title'])) {
                return ['success' => false, 'error' => 'Titre requis.'];
            }
            WishlistItem::update($item['id'], $data);
            return ['success' => true, 'item' => WishlistItem::scrubForViewer(WishlistItem::getById($item['id']), (int)$user['id'])];
        });
    }

    public function delete(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $item = WishlistItem::getById((int)$params['id']);
            if (!$item || $item['family_id'] !== $user['family_id'] || (int)$item['user_id'] !== (int)$user['id']) {
                return ['success' => false, 'error' => 'Souhait introuvable.'];
            }
            WishlistItem::delete($item['id']);
            return ['success' => true];
        });
    }

    public function reserve(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $item = WishlistItem::getById((int)$params['id']);
            // On ne peut jamais réserver son propre souhait — et la vérification "déjà réservé"
            // est refaite en SQL (reserved_by IS NULL) pour éviter une course entre deux membres
            // qui cliqueraient "réserver" au même instant sur le même item.
            if (!$item || $item['family_id'] !== $user['family_id'] || (int)$item['user_id'] === (int)$user['id']) {
                return ['success' => false, 'error' => 'Action impossible.'];
            }
            WishlistItem::reserve($item['id'], (int)$user['id']);
            return ['success' => true, 'item' => WishlistItem::scrubForViewer(WishlistItem::getById($item['id']), (int)$user['id'])];
        });
    }

    public function unreserve(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $item = WishlistItem::getById((int)$params['id']);
            // Seule la personne qui a réservé peut annuler — pas le propriétaire du souhait
            // (qui n'est pas censé savoir qu'une réservation existait), ni un tiers.
            if (!$item || $item['family_id'] !== $user['family_id'] || (int)($item['reserved_by'] ?? 0) !== (int)$user['id']) {
                return ['success' => false, 'error' => 'Action impossible.'];
            }
            WishlistItem::unreserve($item['id']);
            return ['success' => true];
        });
    }

    public function markPurchased(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $item = WishlistItem::getById((int)$params['id']);
            if (!$item || $item['family_id'] !== $user['family_id'] || (int)($item['reserved_by'] ?? 0) !== (int)$user['id']) {
                return ['success' => false, 'error' => 'Action impossible.'];
            }
            $purchased = !empty($this->jsonInput()['purchased']);
            WishlistItem::markPurchased($item['id'], $purchased);
            return ['success' => true];
        });
    }
}
