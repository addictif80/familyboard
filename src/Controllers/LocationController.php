<?php
namespace App\Controllers;

use App\Core\Session;
use App\Models\LocationCheckin;
use App\Models\Notification;
use App\Models\SavedPlace;

class LocationController extends BaseController
{
    /** Check-ins can be shared for at most this long in one go. */
    private const MAX_DURATION_MINUTES = 24 * 60;

    public function index(array $params): void
    {
        $this->requireAuth();
        $this->requireModule('location');
        $user   = Session::user();
        $places = SavedPlace::getByFamily($user['family_id']);
        $current = LocationCheckin::getCurrentByFamily($user['family_id']);
        require BASE_PATH . '/templates/location/index.php';
    }

    public function apiCurrent(array $params): void
    {
        $this->requireAuth();
        $this->json(function () {
            $user = Session::user();
            return ['current' => LocationCheckin::getCurrentByFamily($user['family_id'])];
        });
    }

    public function createPlace(array $params): void
    {
        $this->requireAuth();
        $this->json(function () {
            $user = Session::user();
            $data = $this->jsonInput();
            $name = trim($data['name'] ?? '');
            $lat  = $data['lat'] ?? null;
            $lng  = $data['lng'] ?? null;
            if (!$name || $lat === null || $lng === null) {
                return ['success' => false, 'error' => 'Champs manquants.'];
            }
            $radius = max(50, min(2000, (int)($data['radius_m'] ?? 150)));
            $id = SavedPlace::create($user['family_id'], $user['id'], $name, (float)$lat, (float)$lng, $radius);
            return ['success' => true, 'place' => SavedPlace::getById($id)];
        });
    }

    public function deletePlace(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user  = Session::user();
            $place = SavedPlace::getById((int)$params['id']);
            if (!$place || $place['family_id'] !== $user['family_id']) {
                return ['success' => false];
            }
            SavedPlace::delete($place['id']);
            return ['success' => true];
        });
    }

    public function checkin(array $params): void
    {
        $this->requireAuth();
        $this->json(function () {
            $user = Session::user();
            $data = $this->jsonInput();
            $lat  = $data['lat'] ?? null;
            $lng  = $data['lng'] ?? null;
            if ($lat === null || $lng === null) {
                return ['success' => false, 'error' => 'Position manquante.'];
            }
            $duration = max(15, min(self::MAX_DURATION_MINUTES, (int)($data['duration_minutes'] ?? 240)));

            $place = SavedPlace::findNearby($user['family_id'], (float)$lat, (float)$lng);
            $note  = trim($data['note'] ?? '');

            $id = LocationCheckin::create(
                $user['id'], $user['family_id'], (float)$lat, (float)$lng,
                $place['id'] ?? null, $note ?: null, $duration
            );

            $where = $place ? ('près de ' . $place['name']) : 'sa position';
            Notification::notifyFamily($user['family_id'], $user['id'], 'location',
                'Position partagée', $user['name'] . ' a partagé ' . $where . '.', BASE_URL . '/location');

            return ['success' => true, 'place' => $place];
        });
    }

    public function clear(array $params): void
    {
        $this->requireAuth();
        $this->json(function () {
            $user = Session::user();
            LocationCheckin::clearForUser($user['id'], $user['family_id']);
            return ['success' => true];
        });
    }

    /** Dernière position connue (pas un historique) — alimente uniquement HomePresence pour les
     *  minuteurs. Silencieusement ignoré si l'utilisateur a désactivé le suivi ("Ne plus me
     *  suivre" dans son profil) : le client ne devrait déjà plus appeler cette route dans ce cas,
     *  mais on ne fait jamais confiance au seul client pour une préférence de vie privée. */
    public function ping(array $params): void
    {
        $this->requireAuth();
        $this->json(function () {
            $user = Session::user();
            if (empty($user['location_tracking_enabled'])) {
                return ['success' => false];
            }
            $data = $this->jsonInput();
            $lat = $data['lat'] ?? null;
            $lng = $data['lng'] ?? null;
            if ($lat === null || $lng === null) {
                return ['success' => false];
            }
            \App\Core\Database::execute(
                'UPDATE users SET last_lat=?, last_lng=?, last_location_at=NOW() WHERE id=?',
                [(float)$lat, (float)$lng, $user['id']]
            );
            return ['success' => true];
        });
    }
}
