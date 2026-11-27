<?php
namespace App\Controllers;

use App\Core\Session;
use App\Models\AnnualRecap;

class AnnualRecapController extends BaseController
{
    public function index(array $params): void
    {
        $this->requireAuth();
        $user = Session::user();
        $familyId = (int)$user['family_id'];
        $firstYear = AnnualRecap::firstAvailableYear($familyId);
        $currentYear = (int)date('Y');
        $years = range($currentYear, $firstYear);
        require BASE_PATH . '/templates/annual_recap/index.php';
    }

    public function data(array $params): void
    {
        $this->requireAuth();
        $this->json(function () {
            $user = Session::user();
            $familyId = (int)$user['family_id'];
            $year = (int)($_GET['year'] ?? date('Y'));
            if ($year < 2000 || $year > (int)date('Y')) $year = (int)date('Y');
            return ['success' => true, 'stats' => AnnualRecap::getStats($familyId, $year)];
        });
    }
}
