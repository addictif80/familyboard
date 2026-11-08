<?php
namespace App\Controllers;

use App\Core\RecordPreview;
use App\Core\Session;

/**
 * GET /api/records/:type/:id/preview — prévisualisation lecture seule d'un enregistrement
 * cliqué directement (widget du tableau de bord, ou une page de module), sans passer par une
 * notification. Même moteur que NotificationController::preview() (voir App\Core\RecordPreview)
 * et même modal côté front (openRecordPreview() réutilise notif-preview-modal).
 */
class RecordController extends BaseController
{
    /** Types autorisés en clic direct — une liste explicite plutôt que d'accepter n'importe
     *  quelle chaîne, pour ne jamais laisser une page appeler un type qui n'a pas vocation à
     *  être prévisualisé ainsi (ex. 'system', réservé au flux notifications). */
    private const ALLOWED_TYPES = [
        'event', 'task', 'custody_event', 'custody_schedule', 'comm_log', 'post', 'document',
        'warranty', 'project', 'album_photo', 'addition', 'stock_item', 'user', 'baby', 'contact',
    ];

    public function preview(array $params): void
    {
        $this->requireAuth(true);
        $this->json(function () use ($params) {
            $user = Session::user();
            $type = $params['type'] ?? '';
            $id = (int)($params['id'] ?? 0);
            if (!in_array($type, self::ALLOWED_TYPES, true)) {
                return ['success' => false, 'error' => 'Type inconnu.'];
            }

            $entity = RecordPreview::build($type, $id, (int)$user['family_id'], $user);
            if (!$entity) {
                return ['success' => false, 'error' => 'Enregistrement introuvable.'];
            }

            return ['success' => true, 'icon' => $entity['icon'], 'fields' => $entity['fields']];
        });
    }
}
