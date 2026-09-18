<?php
namespace App\Controllers;

use App\Core\Session;
use App\Models\Event;
use App\Models\Family;
use App\Models\NameDay;
use App\Models\TaskList;

/** Données pour l'interface bureau (PC) : widgets de la barre des tâches (calendrier, courses)
 *  et infos éphéméride/météo. Les fenêtres flottantes elles-mêmes chargent simplement les pages
 *  existantes via iframe (?embed=1) — aucune donnée dédiée n'est nécessaire pour ça. */
class DesktopController extends BaseController
{
    public function widgetCalendar(array $params): void
    {
        $this->requireAuth();
        $this->json(function () {
            $familyId = (int)Session::user()['family_id'];
            $events = Event::getUpcoming($familyId, 30);
            return array_map(fn($e) => [
                'id' => (int)$e['id'],
                'title' => $e['title'],
                'description' => $e['description'],
                'start' => $e['start_datetime'],
                'end' => $e['end_datetime'],
                'is_all_day' => (bool)$e['is_all_day'],
                'color' => $e['color'],
                'location' => $e['location'],
                'user_name' => $e['user_name'],
            ], $events);
        });
    }

    public function widgetShopping(array $params): void
    {
        $this->requireAuth();
        $this->json(function () {
            $familyId = (int)Session::user()['family_id'];
            $items = TaskList::getPendingItemsByType($familyId, 'shopping');
            return array_map(fn($t) => [
                'id' => (int)$t['id'],
                'title' => $t['title'],
                'list_id' => (int)$t['list_id'],
                'list_name' => $t['list_name'],
                'list_color' => $t['list_color'],
            ], $items);
        });
    }

    public function info(array $params): void
    {
        $this->requireAuth();
        $this->json(function () {
            $user = Session::user();
            $family = Family::findById((int)$user['family_id']);
            return [
                'nameday' => NameDay::today(),
                'weather_city' => $family['weather_city'] ?? null,
            ];
        });
    }
}
