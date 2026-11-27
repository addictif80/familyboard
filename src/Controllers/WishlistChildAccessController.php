<?php
namespace App\Controllers;

use App\Models\Family;
use App\Models\FamilyChild;
use App\Models\WishlistChildLink;
use App\Models\WishlistItem;

class WishlistChildAccessController extends BaseController
{
    /** Public, sans compte : liste de cadeaux d'un enfant de la famille, partagée par le lien. */
    public function view(array $params): void
    {
        $token = $params['token'] ?? '';
        $link = WishlistChildLink::findByToken($token);
        $child = $link ? FamilyChild::getById((int)$link['family_child_id']) : null;
        if (!$link || !$child) {
            http_response_code(404);
            require BASE_PATH . '/templates/wishlist/child_view.php';
            return;
        }

        $family = Family::findById((int)$child['family_id']);
        $items = WishlistItem::getByChild((int)$child['id']);

        require BASE_PATH . '/templates/wishlist/child_view.php';
    }

    public function reserve(array $params): void
    {
        $this->json(function () use ($params) {
            $token = $params['token'] ?? '';
            $link = WishlistChildLink::findByToken($token);
            if (!$link) return ['success' => false, 'error' => 'Lien invalide.'];

            $item = WishlistItem::getById((int)$params['itemId']);
            if (!$item || (int)$item['family_child_id'] !== (int)$link['family_child_id']) {
                return ['success' => false, 'error' => 'Article introuvable.'];
            }
            $name = trim($this->jsonInput()['name'] ?? '');
            if ($name === '' || mb_strlen($name) > 150) {
                return ['success' => false, 'error' => 'Votre prénom est requis.'];
            }
            $reservationToken = WishlistItem::reserveAnonymous((int)$item['id'], (int)$link['family_child_id'], $name);
            if (!$reservationToken) {
                return ['success' => false, 'error' => 'Cet article vient d\'être réservé par quelqu\'un d\'autre.'];
            }
            return ['success' => true, 'reservation_token' => $reservationToken];
        });
    }

    public function unreserve(array $params): void
    {
        $this->json(function () use ($params) {
            $token = $params['token'] ?? '';
            $link = WishlistChildLink::findByToken($token);
            if (!$link) return ['success' => false, 'error' => 'Lien invalide.'];

            $reservationToken = trim($this->jsonInput()['reservation_token'] ?? '');
            if ($reservationToken === '') return ['success' => false];

            $ok = WishlistItem::unreserveAnonymous((int)$params['itemId'], (int)$link['family_child_id'], $reservationToken);
            return ['success' => $ok];
        });
    }
}
