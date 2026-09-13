<?php
namespace App\Controllers;

use App\Models\BirthList;
use App\Models\Family;

class BirthListAccessController extends BaseController
{
    /** Public, sans compte : liste de naissance partagée par la famille. */
    public function view(array $params): void
    {
        $token = $params['token'] ?? '';
        $list = BirthList::findByToken($token);
        if (!$list) {
            http_response_code(404);
            require BASE_PATH . '/templates/birth_list/view.php';
            return;
        }

        $family = Family::findById((int)$list['family_id']);
        $items = BirthList::getItems((int)$list['id']);

        require BASE_PATH . '/templates/birth_list/view.php';
    }

    public function reserve(array $params): void
    {
        $this->json(function () use ($params) {
            $token = $params['token'] ?? '';
            $list = BirthList::findByToken($token);
            if (!$list) return ['success' => false, 'error' => 'Lien invalide.'];

            $item = BirthList::getItemById((int)$params['itemId']);
            if (!$item || (int)$item['birth_list_id'] !== (int)$list['id']) {
                return ['success' => false, 'error' => 'Article introuvable.'];
            }
            $name = trim($this->jsonInput()['name'] ?? '');
            if ($name === '' || mb_strlen($name) > 150) {
                return ['success' => false, 'error' => 'Votre prénom est requis.'];
            }
            $reservationToken = BirthList::reserve((int)$item['id'], (int)$list['id'], $name);
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
            $list = BirthList::findByToken($token);
            if (!$list) return ['success' => false, 'error' => 'Lien invalide.'];

            $reservationToken = trim($this->jsonInput()['reservation_token'] ?? '');
            if ($reservationToken === '') return ['success' => false];

            $ok = BirthList::unreserve((int)$params['itemId'], (int)$list['id'], $reservationToken);
            return ['success' => $ok];
        });
    }
}
