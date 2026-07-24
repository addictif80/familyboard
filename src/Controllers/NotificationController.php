<?php
namespace App\Controllers;

use App\Models\SystemNotification;

class NotificationController extends BaseController
{
    public function show(array $params): void
    {
        $this->requireAuth(true);
        $notification = SystemNotification::findById((int)$params['id']);
        if (!$notification) {
            header('Location: ' . BASE_URL . '/');
            exit;
        }
        require BASE_PATH . '/templates/notifications/show.php';
    }
}
