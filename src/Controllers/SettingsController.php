<?php
namespace App\Controllers;

use App\Core\Session;
use App\Models\User;
use App\Models\Family;
use App\Models\Notification;
use App\Models\EmailLog;
use App\Models\EmailTemplate;
use App\Models\SitterLink;

class SettingsController extends BaseController
{
    public function index(array $params): void
    {
        $this->requireAuth();
        $user = Session::user();
        $family = Family::findById($user['family_id']);
        $members = User::getByFamily($user['family_id']);
        $coparentChildren = \App\Models\Custody::getChildNamesByUserIds(array_column($members, 'id'));
        $emailLogs = ($user['role'] === 'admin') ? EmailLog::getByFamily($user['family_id'], 30) : [];
        $emailTemplates = ($user['role'] === 'admin') ? EmailTemplate::getAll($user['family_id']) : [];
        $sitterLinks = SitterLink::getByFamily($user['family_id']);
        require BASE_PATH . '/templates/settings/index.php';
    }

    public function updateProfile(array $params): void
    {
        $this->requireAuth();
        $user = Session::user();
        $name = trim($_POST['name'] ?? '');
        $color = $_POST['color'] ?? '#4A90D9';
        $avatar = $this->uploadImage('avatar');

        $data = ['name' => $name ?: $user['name'], 'color' => $color];
        if ($avatar) $data['avatar'] = $avatar;
        User::update($user['id'], $data);

        $password = $_POST['password'] ?? '';
        if ($password && strlen($password) >= 6) {
            User::updatePassword($user['id'], $password);
        }

        Session::flash('success', 'Profil mis à jour.');
        header('Location: ' . BASE_URL . '/settings');
        exit;
    }

    public function updateFamily(array $params): void
    {
        $this->requireAdmin();
        $user = Session::user();
        $name = trim($_POST['family_name'] ?? '');
        $tz   = $_POST['timezone'] ?? '';
        if (!$tz || !in_array($tz, \DateTimeZone::listIdentifiers())) $tz = null;
        if ($name) Family::update($user['family_id'], $name, [
            'timezone'             => $tz,
            'weather_city'         => trim($_POST['weather_city']         ?? ''),
            'go2rtc_url'           => trim($_POST['go2rtc_url']           ?? ''),
            'school_zone'          => trim($_POST['school_zone']          ?? ''),
            'caldav_sync_interval' => trim($_POST['caldav_sync_interval'] ?? ''),
        ]);
        Session::flash('success', 'Famille mise à jour.');
        header('Location: ' . BASE_URL . '/settings');
        exit;
    }

    public function regenerateCode(array $params): void
    {
        $this->requireAdmin();
        $user = Session::user();
        $code = Family::regenerateCode($user['family_id']);
        Session::flash('success', 'Nouveau code : ' . $code);
        header('Location: ' . BASE_URL . '/settings');
        exit;
    }

    public function updateModules(array $params): void
    {
        $this->requireAdmin();
        $user    = Session::user();
        $all     = array_keys(\App\Models\Family::MODULES);
        $enabled = $_POST['modules'] ?? [];
        $disabled = array_values(array_diff($all, $enabled));
        Family::setDisabledModules($user['family_id'], $disabled);
        Session::flash('success', 'Modules mis à jour.');
        header('Location: ' . BASE_URL . '/settings');
        exit;
    }

    public function removeMember(array $params): void
    {
        $this->requireAdmin();
        $user = Session::user();
        $id = (int)$params['id'];
        if ($id !== $user['id']) {
            $member = User::findById($id);
            if ($member && $member['family_id'] === $user['family_id']) {
                User::delete($id);
            }
        }
        header('Location: ' . BASE_URL . '/settings');
        exit;
    }

    public function getNotifications(array $params): void
    {
        $this->requireAuth();
        $this->json(function () {
            $user = Session::user();
            $notifications = Notification::getByUser($user['id'], 20);
            $unread = Notification::getUnreadCount($user['id']);
            return ['notifications' => $notifications, 'unread' => $unread];
        });
    }

    public function markNotificationRead(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            Notification::markRead((int)$params['id'], $user['id']);
            return ['success' => true];
        });
    }

    public function markAllNotificationsRead(array $params): void
    {
        $this->requireAuth();
        $this->json(function () {
            $user = Session::user();
            Notification::markAllRead($user['id']);
            return ['success' => true];
        });
    }

    public function saveEmailTemplate(array $params): void
    {
        $this->requireAdmin();
        $this->json(function () {
            $user = Session::user();
            $data = $this->jsonInput();
            $type    = $data['type'] ?? '';
            $subject = trim($data['subject'] ?? '');
            $body    = trim($data['body'] ?? '');
            if (!$type || !$subject || !$body) {
                return ['success' => false, 'error' => 'Champs requis manquants.'];
            }
            EmailTemplate::save($user['family_id'], $type, $subject, $body);
            return ['success' => true];
        });
    }

    public function resetEmailTemplate(array $params): void
    {
        $this->requireAdmin();
        $this->json(function () use ($params) {
            $user = Session::user();
            $type = $params['type'] ?? '';
            if ($type) EmailTemplate::reset($user['family_id'], $type);
            return ['success' => true];
        });
    }
}
