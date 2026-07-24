<?php
namespace App\Controllers;

use App\Core\Push;
use App\Core\Session;
use App\Models\PushSubscription;

class PushController extends BaseController
{
    public function vapidPublicKey(array $params): void
    {
        $this->requireAuth();
        $this->json(fn () => ['publicKey' => Push::vapidPublicKey()]);
    }

    public function subscribe(array $params): void
    {
        $this->requireAuth();
        $this->json(function () {
            $user = Session::user();
            $data = $this->jsonInput();
            $endpoint = $data['endpoint'] ?? '';
            $keys = $data['keys'] ?? [];
            $p256dh = $keys['p256dh'] ?? '';
            $auth = $keys['auth'] ?? '';
            if (!$endpoint || !$p256dh || !$auth) {
                return ['success' => false, 'error' => 'Abonnement invalide.'];
            }
            PushSubscription::save($user['id'], $endpoint, $p256dh, $auth);
            return ['success' => true];
        });
    }

    public function unsubscribe(array $params): void
    {
        $this->requireAuth();
        $this->json(function () {
            $user = Session::user();
            $data = $this->jsonInput();
            $endpoint = $data['endpoint'] ?? '';
            if ($endpoint) PushSubscription::deleteForUser($user['id'], $endpoint);
            return ['success' => true];
        });
    }
}
