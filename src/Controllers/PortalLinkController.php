<?php
namespace App\Controllers;

use App\Core\LinkPreview;
use App\Core\Session;
use App\Models\Custody;
use App\Models\PortalLink;

class PortalLinkController extends BaseController
{
    public function index(array $params): void
    {
        $this->requireAuth();
        $this->requireModule('links');
        $user = Session::user();
        $isAdmin = $user['role'] === 'admin';

        $links = PortalLink::getByFamily($user['family_id']);
        // Un membre non-admin voit les liens approuvés de toute la famille, et en plus le statut
        // de ses propres propositions en attente/rejetées — jamais celles soumises par d'autres.
        if (!$isAdmin) {
            $links = array_values(array_filter(
                $links,
                fn($l) => $l['status'] === 'approved' || (int)$l['submitted_by'] === (int)$user['id']
            ));
        }
        // Pour le choix "visible pour tel(s) enfant(s)" quand la case co-parent est cochée.
        $schedules = Custody::getSchedules($user['family_id']);

        require BASE_PATH . '/templates/links/index.php';
    }

    /** Ajout direct (admin, auto-approuvé) ou proposition (membre, en attente de validation). */
    public function create(array $params): void
    {
        $this->requireAuth();
        $this->json(function () {
            $user = Session::user();
            $data = $this->jsonInput();
            $title = trim($data['title'] ?? '');
            $url = trim($data['url'] ?? '');
            if (!$url) {
                return ['success' => false, 'error' => 'Le lien est requis.'];
            }

            $preview = LinkPreview::fetch($url);
            if (!$preview['ok']) {
                return ['success' => false, 'error' => $preview['error']];
            }
            if (!$title) $title = $preview['title'];

            $status = $user['role'] === 'admin' ? 'approved' : 'pending';
            $id = PortalLink::create($user['family_id'], (int)$user['id'], [
                'title'                 => $title,
                'url'                   => $url,
                'description'           => trim($data['description'] ?? ''),
                'image_path'            => $preview['image_path'],
                'visible_to_coparent'   => !empty($data['visible_to_coparent']),
                'coparent_schedule_ids' => $data['coparent_schedule_ids'] ?? [],
            ], $status);

            return ['success' => true, 'status' => $status, 'link' => PortalLink::getById($id)];
        });
    }

    public function update(array $params): void
    {
        $this->requireAdmin();
        $this->json(function () use ($params) {
            $user = Session::user();
            $link = PortalLink::getById((int)$params['id']);
            if (!$link || $link['family_id'] !== $user['family_id']) {
                return ['success' => false, 'error' => 'Lien introuvable.'];
            }
            $data = $this->jsonInput();
            if (empty($data['title'])) {
                return ['success' => false, 'error' => 'Titre requis.'];
            }
            PortalLink::update($link['id'], $data);
            return ['success' => true];
        });
    }

    public function approve(array $params): void
    {
        $this->requireAdmin();
        $this->json(function () use ($params) {
            $user = Session::user();
            $link = PortalLink::getById((int)$params['id']);
            if (!$link || $link['family_id'] !== $user['family_id']) {
                return ['success' => false, 'error' => 'Lien introuvable.'];
            }
            PortalLink::approve($link['id'], (int)$user['id']);
            return ['success' => true];
        });
    }

    public function reject(array $params): void
    {
        $this->requireAdmin();
        $this->json(function () use ($params) {
            $user = Session::user();
            $link = PortalLink::getById((int)$params['id']);
            if (!$link || $link['family_id'] !== $user['family_id']) {
                return ['success' => false, 'error' => 'Lien introuvable.'];
            }
            $reason = trim($this->jsonInput()['reason'] ?? '');
            PortalLink::reject($link['id'], (int)$user['id'], $reason);
            return ['success' => true];
        });
    }

    public function delete(array $params): void
    {
        $this->requireAdmin();
        $this->json(function () use ($params) {
            $user = Session::user();
            $link = PortalLink::getById((int)$params['id']);
            if (!$link || $link['family_id'] !== $user['family_id']) {
                return ['success' => false, 'error' => 'Lien introuvable.'];
            }
            if ($link['image_path']) @unlink(BASE_PATH . $link['image_path']);
            PortalLink::delete($link['id']);
            return ['success' => true];
        });
    }

    /** Relance la vérification/aperçu (titre + image) d'un lien déjà ajouté — utile pour les
     *  liens créés avant l'amélioration de la détection d'image, ou dont le site a changé. */
    public function refreshPreview(array $params): void
    {
        $this->requireAdmin();
        $this->json(function () use ($params) {
            $user = Session::user();
            $link = PortalLink::getById((int)$params['id']);
            if (!$link || $link['family_id'] !== $user['family_id']) {
                return ['success' => false, 'error' => 'Lien introuvable.'];
            }
            $preview = LinkPreview::fetch($link['url']);
            if (!$preview['ok']) {
                return ['success' => false, 'error' => $preview['error']];
            }
            if (!$preview['image_path']) {
                return ['success' => false, 'error' => "Aucune image trouvée sur ce site."];
            }
            if ($link['image_path']) @unlink(BASE_PATH . $link['image_path']);
            PortalLink::updatePreviewImage($link['id'], $preview['image_path']);
            return ['success' => true, 'link' => PortalLink::getById($link['id'])];
        });
    }

    /** Clic depuis une carte : comptabilise puis redirige — accessible aux membres de la
     *  famille comme à un accès co-parent dont le planning correspond à cette famille. */
    public function go(array $params): void
    {
        $this->requireAuth(true);
        $user = Session::user();
        $link = PortalLink::getById((int)($params['id'] ?? 0));

        if (!$link || $link['status'] !== 'approved') {
            header('Location: ' . BASE_URL . '/');
            exit;
        }

        $allowed = false;
        if ($link['family_id'] === null) {
            // Lien certifié diffusé par l'administrateur système à toutes les familles —
            // pas de restriction de famille, seule la case "visible co-parent" s'applique
            // encore à un accès restreint.
            $allowed = $user['role'] !== 'coparent' || (bool)$link['visible_to_coparent'];
        } elseif ($user['role'] !== 'coparent' && $link['family_id'] === $user['family_id']) {
            $allowed = true;
        } elseif ($link['visible_to_coparent']) {
            $schedules = Custody::getSchedulesForUser((int)$user['id']);
            $matchingScheduleIds = array_column(
                array_filter($schedules, fn($s) => (int)$s['family_id'] === (int)$link['family_id']),
                'id'
            );
            if ($matchingScheduleIds) {
                $allowed = empty($link['coparent_schedule_ids'])
                    || (bool)array_intersect($matchingScheduleIds, array_map('intval', explode(',', $link['coparent_schedule_ids'])));
            }
        }

        if (!$allowed) {
            header('Location: ' . BASE_URL . '/');
            exit;
        }

        PortalLink::registerClick($link['id']);
        header('Location: ' . $link['url']);
        exit;
    }
}
