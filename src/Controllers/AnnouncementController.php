<?php
namespace App\Controllers;

use App\Core\Session;
use App\Models\Announcement;

class AnnouncementController extends BaseController
{
    public function index(array $params): void
    {
        $this->requireAuth();
        $user = Session::user();
        $announcements = Announcement::getPublished();
        Announcement::markAllRead((int)$user['id']);
        require BASE_PATH . '/templates/announcements/index.php';
    }
}
