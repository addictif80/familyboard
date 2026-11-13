<?php
namespace App\Controllers;

use App\Core\ModuleGuide;
use App\Core\Session;
use App\Models\User;

/** Visites guidées "Comment ça marche" (une globale + une par module) — voir App\Core\ModuleGuide
 *  pour le contenu, et templates/partials/tour_widget.php pour l'affichage. */
class TourController extends BaseController
{
    public function markSeen(array $params): void
    {
        $this->requireAuth(true);
        $this->json(function () {
            $user = Session::user();
            $body = json_decode(file_get_contents('php://input'), true) ?: [];
            $key  = (string)($body['key'] ?? '');
            if ($key === '' || ModuleGuide::get($key) === null) {
                http_response_code(400);
                return ['error' => 'invalid'];
            }
            User::markTourSeen((int)$user['id'], $key);
            return ['success' => true];
        });
    }
}
