<?php
namespace App\Controllers;

use App\Core\Session;
use App\Models\TaskList;

class MigrationController extends BaseController
{
    public function index(array $params): void
    {
        $this->requireAuth();
        $user = Session::user();
        $familyId = (int)$user['family_id'];
        $isAdmin = ($user['role'] ?? '') === 'admin';
        $lists = TaskList::getByFamily($familyId);
        require BASE_PATH . '/templates/migration/index.php';
    }
}
