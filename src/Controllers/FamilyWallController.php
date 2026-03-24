<?php
namespace App\Controllers;

use App\Core\Session;
use App\Models\Event;
use App\Models\Custody;
use App\Models\TaskList;
use App\Models\Budget;
use App\Models\Family;

class FamilyWallController extends BaseController
{
    public function index(array $params): void
    {
        $this->requireAuth();
        $user     = Session::user();
        $familyId = $user['family_id'];
        $family   = Family::findById($familyId);

        [
            'byDate'        => $byDate,
            'taskLists'     => $taskLists,
            'shoppingLists' => $shoppingLists,
            'budget'        => $budget,
            'goals'         => $goals,
        ] = $this->buildData($familyId);

        require BASE_PATH . '/templates/family-wall/index.php';
    }

    public function apiData(array $params): void
    {
        $this->requireAuth();
        $this->json(function () {
            $familyId = Session::user()['family_id'];
            return ['success' => true] + $this->buildData($familyId);
        });
    }

    private function buildData(int $familyId): array
    {
        $today    = new \DateTime('today');
        $endDate  = (clone $today)->modify('+6 days');
        $todayStr = $today->format('Y-m-d');
        $endStr   = $endDate->format('Y-m-d');

        // Calendar events for next 7 days
        $calEvents = Event::getByFamily(
            $familyId,
            $todayStr . ' 00:00:00',
            $endStr . ' 23:59:59'
        );

        // Custody events
        $custodyEvents = [];
        try {
            $custodyEvents = Custody::getAllEventsForFamily($familyId, $todayStr, $endStr);
        } catch (\Throwable) {}

        // Build 7-day structure indexed by Y-m-d
        $byDate = [];
        for ($i = 0; $i < 7; $i++) {
            $d = (clone $today)->modify("+{$i} days");
            $byDate[$d->format('Y-m-d')] = ['events' => [], 'custody' => []];
        }

        foreach ($calEvents as $e) {
            $date = substr($e['start_datetime'], 0, 10);
            if (isset($byDate[$date])) {
                $byDate[$date]['events'][] = $e;
            }
        }

        // Each custody event may span multiple days — add to each matching day
        foreach ($custodyEvents as $e) {
            $eStart = new \DateTime($e['start_date']);
            $eEnd   = new \DateTime($e['end_date']);
            foreach (array_keys($byDate) as $dateStr) {
                $d = new \DateTime($dateStr);
                if ($d >= $eStart && $d <= $eEnd) {
                    // Deduplicate by parent+child on same day
                    $key = ($e['parent_user_id'] ?? '0') . '_' . ($e['child_name'] ?? '');
                    $byDate[$dateStr]['custody'][$key] = $e;
                }
            }
        }
        foreach ($byDate as &$day) {
            $day['custody'] = array_values($day['custody']);
        }
        unset($day);

        // Task and shopping lists with pending items
        $taskLists = $shoppingLists = [];
        foreach (TaskList::getByFamily($familyId) as $list) {
            $all = TaskList::getTasks($list['id']);
            $list['pending'] = array_values(array_filter($all, fn($t) => !$t['is_completed']));
            if ($list['type'] === 'shopping') {
                $shoppingLists[] = $list;
            } else {
                $taskLists[] = $list;
            }
        }

        // Budget summary + goals
        $budget = ['income' => 0, 'expenses' => 0, 'balance' => 0];
        $goals  = [];
        try {
            $budget = Budget::getSummary($familyId, date('Y-m'));
            $goals  = Budget::getGoals($familyId);
        } catch (\Throwable) {}

        return compact('byDate', 'taskLists', 'shoppingLists', 'budget', 'goals');
    }
}
