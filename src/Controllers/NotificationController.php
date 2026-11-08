<?php
namespace App\Controllers;

use App\Core\RecordPreview;
use App\Core\Session;
use App\Models\Notification;
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

    /**
     * GET /api/notifications/:id/preview — contenu lecture seule pour la modal ouverte au clic
     * sur une notification, plutôt que de renvoyer directement vers le module où il faut
     * chercher soi-même l'enregistrement. $notif['link'] reste toujours renvoyé comme solution
     * de repli ("Ouvrir la page complète") si l'utilisateur veut agir dessus (la modal est
     * volontairement lecture seule, aucune action n'y est possible).
     *
     * Si l'enregistrement a depuis été supprimé, ou si le type n'a pas (encore) de vue dédiée,
     * on retombe sur le texte déjà stocké dans la notification elle-même — jamais d'erreur côté
     * utilisateur, une notification peut toujours au moins s'afficher elle-même. Les gestionnaires
     * par type vivent dans App\Core\RecordPreview, partagés avec RecordController (clic direct
     * sur un enregistrement depuis le tableau de bord ou une page de module).
     */
    public function preview(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $notif = Notification::getById((int)$params['id']);
            if (!$notif || (int)$notif['user_id'] !== (int)$user['id']) {
                return ['success' => false, 'error' => 'Notification introuvable.'];
            }

            $familyId = (int)$user['family_id'];
            $entity = $notif['entity_type'] ? RecordPreview::build($notif['entity_type'], (int)($notif['entity_id'] ?? 0), $familyId, $user) : null;

            $entity ??= [
                'icon' => '🔔',
                'fields' => [],
            ];

            return [
                'success' => true,
                'title' => $notif['title'],
                'message' => $notif['message'],
                'created_at' => $notif['created_at'],
                'link' => $notif['link'],
                'icon' => $entity['icon'],
                'fields' => $entity['fields'],
            ];
        });
    }
}
