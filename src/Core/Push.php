<?php
namespace App\Core;

use App\Models\AppSetting;
use App\Models\PushSubscription;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\VAPID;
use Minishlink\WebPush\WebPush;

class Push
{
    public static function vapidPublicKey(): string
    {
        return self::getKeys()['publicKey'];
    }

    private static function getKeys(): array
    {
        $public = AppSetting::get('vapid_public_key');
        $private = AppSetting::get('vapid_private_key');
        if (!$public || !$private) {
            $keys = VAPID::createVapidKeys();
            AppSetting::set('vapid_public_key', $keys['publicKey']);
            AppSetting::set('vapid_private_key', $keys['privateKey']);
            $public = $keys['publicKey'];
            $private = $keys['privateKey'];
        }
        return ['publicKey' => $public, 'privateKey' => $private];
    }

    public static function sendToUser(int $userId, string $title, string $message, ?string $link = null): void
    {
        $subs = PushSubscription::getByUser($userId);
        if (!$subs) return;

        $keys = self::getKeys();
        $webPush = new WebPush([
            'VAPID' => [
                'subject'    => 'mailto:contact@familyboard.app',
                'publicKey'  => $keys['publicKey'],
                'privateKey' => $keys['privateKey'],
            ],
        ]);

        $payload = json_encode([
            'title' => $title,
            'body'  => $message,
            'url'   => $link ?: '/',
        ]);

        foreach ($subs as $sub) {
            $subscription = Subscription::create([
                'endpoint' => $sub['endpoint'],
                'keys' => [
                    'p256dh' => $sub['p256dh'],
                    'auth'   => $sub['auth_key'],
                ],
            ]);
            $webPush->queueNotification($subscription, $payload);
        }

        foreach ($webPush->flush() as $report) {
            if ($report->isSuccess()) continue;
            if ($report->isSubscriptionExpired()) {
                PushSubscription::deleteByEndpoint($report->getEndpoint());
                continue;
            }
            // Échec non lié à l'expiration (timeout, erreur serveur du service de push...) :
            // on ne supprime pas l'abonnement (il peut redevenir valide), mais on trace
            // l'échec pour qu'un problème récurrent (ex. mauvaise config VAPID) soit visible
            // dans les logs plutôt que silencieusement avalé.
            error_log('[Push] Envoi échoué vers ' . $report->getEndpoint() . ' : ' . $report->getReason());
        }
    }
}
