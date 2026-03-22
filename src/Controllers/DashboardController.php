<?php
namespace App\Controllers;

use App\Core\Session;
use App\Models\Event;
use App\Models\Post;
use App\Models\Message;
use App\Models\Notification;
use App\Models\Custody;

class DashboardController
{
    public function index(array $params): void
    {
        $this->requireAuth();
        $user = Session::user();
        $familyId = $user['family_id'];

        $upcomingEvents = Event::getUpcoming($familyId);
        $recentPosts = Post::getByFamily($familyId, 5);
        $unreadNotifications = Notification::getUnreadCount($user['id']);

        // Custody today
        $today = date('Y-m-d');
        $custodyToday = Custody::getAllEventsForFamily($familyId, $today, $today);

        require BASE_PATH . '/templates/dashboard.php';
    }

    protected function requireAuth(): void
    {
        if (!Session::isLoggedIn()) {
            header('Location: ' . BASE_URL . '/login');
            exit;
        }
        // Refresh user data
        $user = \App\Models\User::findById(Session::userId());
        if (!$user) {
            Session::destroy();
            header('Location: ' . BASE_URL . '/login');
            exit;
        }
        Session::set('user', $user);
    }
}
