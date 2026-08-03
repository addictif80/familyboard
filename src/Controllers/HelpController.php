<?php
namespace App\Controllers;

use App\Core\Session;
use App\Models\Family;

/** Guide d'utilisation des modules, réservé aux utilisateurs connectés (contrairement à la
 *  FAQ publique, qui reste une présentation générale accessible sans compte). */
class HelpController extends BaseController
{
    public function index(array $params): void
    {
        $this->requireAuth(true);
        $user = Session::user();

        $disabledModules = [];
        if ($user['role'] !== 'coparent') {
            $family = Family::findById($user['family_id']);
            $disabledModules = Family::getDisabledModules($family ?? []);
        }

        require BASE_PATH . '/templates/help/index.php';
    }
}
