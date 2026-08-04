<?php
namespace App\Controllers;

use App\Core\Database;
use App\Core\OcrHelper;
use App\Core\Session;
use App\Models\Album;
use App\Models\AlbumPhoto;
use App\Models\Custody;
use App\Models\CustodyActivityLog;
use App\Models\CommLogMessage;
use App\Models\Document;
use App\Models\Event;
use App\Models\Family;
use App\Models\Notification;
use App\Models\PortalLink;
use App\Models\User;
use App\Core\LinkPreview;

/**
 * Vue dédiée pour un accès "garde partagée" restreint : soit un compte
 * entièrement restreint (role=coparent), soit un membre à part entière d'une
 * autre famille qui a en plus reçu un accès (custody_access) à un ou plusieurs
 * plannings de garde de CETTE famille. Dans les deux cas, tout est filtré par
 * les schedule_id auxquels l'utilisateur a explicitement accès — jamais par
 * family_id, puisque les plannings peuvent appartenir à une autre famille.
 */
class CoparentController extends BaseController
{
    private const MAX_VOICE_SIZE = 15 * 1024 * 1024;

    /**
     * Le fuseau horaire global du process (index.php) est fixé sur la famille du COMPTE
     * connecté — correct pour un membre classique, faux pour un accès co-parent qui consulte
     * un planning appartenant à une AUTRE famille (fuseau potentiellement différent). Les
     * calculs de date relatifs ("ce mois-ci" par défaut, etc.) doivent utiliser le fuseau de
     * la famille propriétaire du planning, pas celui du compte du co-parent.
     */
    private function applyScheduleTimezone(array $schedule): void
    {
        date_default_timezone_set(Family::getTimezone((int)$schedule['family_id']));
    }

    public function index(array $params): void
    {
        $this->requireAuth(true);
        $user = Session::user();
        $schedules = Custody::getSchedulesForUser($user['id']);
        if (empty($schedules) && $user['role'] !== 'coparent') {
            header('Location: ' . BASE_URL . '/');
            exit;
        }
        require BASE_PATH . '/templates/coparent/index.php';
    }

    /**
     * Un compte à accès restreint (role=coparent) peut, s'il le souhaite, se
     * créer sa propre famille FamilyBoard complète. On bascule alors son
     * family_id/role — mais on ne touche jamais à custody_access, qui référence
     * les plannings indépendamment de la famille : il garde donc son accès
     * "Garde partagée" via la nouvelle vue /coparent, en plus de son propre
     * tableau de bord complet.
     */
    public function createFamily(array $params): void
    {
        $this->requireAuth(true);
        $this->json(function () {
            $user = Session::user();
            if ($user['role'] !== 'coparent') {
                return ['success' => false, 'error' => 'Vous avez déjà votre propre famille.'];
            }
            $familyName = trim($this->jsonInput()['family_name'] ?? '');
            if (!$familyName) {
                return ['success' => false, 'error' => 'Veuillez donner un nom à votre famille.'];
            }

            $familyId = Family::create($familyName);
            Database::execute('UPDATE users SET family_id=?, role=? WHERE id=?', [$familyId, 'admin', $user['id']]);
            Session::login(User::findById($user['id']));

            return ['success' => true];
        });
    }

    public function apiCustodyEvents(array $params): void
    {
        $this->requireAuth(true);
        $this->json(function () {
            $user = Session::user();
            $scheduleId = (int)($_GET['schedule_id'] ?? 0);
            if (!Custody::userHasAccessToSchedule($user['id'], $scheduleId)) return [];
            $schedule = Custody::getScheduleById($scheduleId);
            if ($schedule) $this->applyScheduleTimezone($schedule);
            $start = $_GET['start'] ?? date('Y-m-01');
            $end = $_GET['end'] ?? date('Y-m-t');
            $events = Custody::getAllEventsForSchedules([$scheduleId], $start, $end);
            return array_map(function ($e) {
                $range = Custody::toDisplayRange($e);
                return [
                    'id'              => 'custody_' . $e['id'],
                    'title'           => $e['child_name'] . ' chez ' . $e['parent_name'],
                    'start'           => $range['start'],
                    'end'             => $range['end'],
                    'allDay'          => false,
                    'color'           => $e['parent_color'],
                    'backgroundColor' => $e['schedule_color'],
                    'borderColor'     => $e['parent_color'],
                    'extendedProps'   => [
                        'parent_name'  => $e['parent_name'],
                        'notes'        => $e['notes'],
                        'is_recurring' => !empty($e['is_recurring']),
                    ],
                ];
            }, $events);
        });
    }

    public function proposeCustody(array $params): void
    {
        $this->requireAuth(true);
        $this->json(function () {
            $user = Session::user();
            $data = $this->jsonInput();
            $scheduleId = (int)($data['schedule_id'] ?? 0);
            if (!Custody::userHasAccessToSchedule($user['id'], $scheduleId)) {
                return ['success' => false, 'error' => 'Accès non autorisé'];
            }
            $schedule = Custody::getScheduleById($scheduleId);
            $days = $data['days'] ?? [];
            if (empty($days)) return ['success' => true, 'created' => 0];

            $created = Custody::applyProposalDays($scheduleId, $days);
            CustodyActivityLog::record($scheduleId, $user['id'], 'proposal_sent', $created . ' bloc(s)');

            Notification::notifyFamily(
                (int)$schedule['family_id'], $user['id'], 'custody',
                'Proposition de garde reçue',
                $user['name'] . ' a proposé des jours de garde pour ' . $schedule['child_name'] . '.',
                BASE_URL . '/custody', $scheduleId
            );

            return ['success' => true, 'created' => $created];
        });
    }

    public function journal(array $params): void
    {
        $this->requireAuth(true);
        $this->json(function () {
            $user = Session::user();
            $scheduleId = (int)($_GET['schedule_id'] ?? 0);
            if (!Custody::userHasAccessToSchedule($user['id'], $scheduleId)) return ['messages' => []];

            $after = (int)($_GET['after'] ?? 0);
            $messages = $after > 0
                ? CommLogMessage::getNewForSchedules([$scheduleId], $after)
                : CommLogMessage::getForSchedules([$scheduleId]);
            return ['messages' => $messages];
        });
    }

    public function journalSend(array $params): void
    {
        $this->requireAuth(true);
        $this->json(function () {
            $user = Session::user();
            $audioFile = $_FILES['audio'] ?? null;
            $data = $audioFile ? $_POST : $this->jsonInput();
            $scheduleId = (int)($data['schedule_id'] ?? 0);
            if (!Custody::userHasAccessToSchedule($user['id'], $scheduleId)) {
                return ['success' => false, 'error' => 'Accès non autorisé'];
            }
            $content = trim($data['content'] ?? '');
            if (!$content && !$audioFile) return ['success' => false];
            if (mb_strlen($content) > 4000) return ['success' => false, 'error' => 'Message trop long'];

            $schedule = Custody::getScheduleById($scheduleId);
            $this->applyScheduleTimezone($schedule);

            $audioPath = $audioOriginal = $audioMime = $duration = null;
            if ($audioFile) {
                try {
                    [$audioPath, $audioOriginal, $audioMime] =
                        OcrHelper::saveUploadedFile($audioFile, 'voice', (int)$schedule['family_id'], OcrHelper::VOICE_MIMES, self::MAX_VOICE_SIZE);
                } catch (\RuntimeException $e) {
                    return ['success' => false, 'error' => $e->getMessage()];
                }
                $duration = isset($data['duration']) ? (int)$data['duration'] : null;
            }

            $id = CommLogMessage::create((int)$schedule['family_id'], $user['id'], $content, $scheduleId, $audioPath, $audioOriginal, $audioMime, $duration);
            CustodyActivityLog::record($scheduleId, $user['id'], 'journal_message_sent', $audioPath ? 'Message vocal' : mb_strimwidth($content, 0, 100, '…'));

            Notification::notifyFamily((int)$schedule['family_id'], $user['id'], 'comm_log',
                'Journal parental', $user['name'] . ' a ajouté ' . ($audioPath ? 'un message vocal' : 'un message') . ' au sujet de ' . $schedule['child_name'] . '.',
                BASE_URL . '/comm-log', $scheduleId);

            return [
                'success' => true,
                'message' => [
                    'id' => $id,
                    'content' => $content,
                    'audio_path' => $audioPath,
                    'audio_mime' => $audioMime,
                    'audio_duration' => $duration,
                    'user_name' => $user['name'],
                    'user_color' => $user['color'],
                    'user_avatar' => $user['avatar'],
                    'created_at' => date('Y-m-d H:i:s'),
                    'user_id' => $user['id'],
                ],
            ];
        });
    }

    public function journalAudio(array $params): void
    {
        $this->requireAuth(true);
        $user = Session::user();
        $msg  = CommLogMessage::findById((int)$params['id']);

        if (!$msg || !$msg['audio_path'] || !$msg['custody_schedule_id']
            || !Custody::userHasAccessToSchedule($user['id'], (int)$msg['custody_schedule_id'])) {
            http_response_code(404); echo 'Introuvable.'; return;
        }

        $path = BASE_PATH . $msg['audio_path'];
        if (!file_exists($path)) { http_response_code(404); echo 'Introuvable.'; return; }

        header('Content-Type: ' . ($msg['audio_mime'] ?: 'audio/webm'));
        header('Content-Length: ' . filesize($path));
        header($this->contentDispositionHeader($msg['audio_original'] ?: basename($path)));
        header('Cache-Control: private, max-age=3600');
        header('Accept-Ranges: bytes');
        readfile($path);
        exit;
    }

    public function activityLog(array $params): void
    {
        $this->requireAuth(true);
        $this->json(function () {
            $user = Session::user();
            $scheduleId = (int)($_GET['schedule_id'] ?? 0);
            if (!Custody::userHasAccessToSchedule($user['id'], $scheduleId)) return ['active' => false, 'entries' => []];

            return [
                'active'  => CustodyActivityLog::isActiveForSchedule($scheduleId),
                'entries' => array_map(fn($e) => [
                    'id'         => $e['id'],
                    'action'     => $e['action'],
                    'label'      => CustodyActivityLog::labelFor($e['action']),
                    'details'    => $e['details'],
                    'ip'         => $e['ip'],
                    'user_name'  => $e['user_name'] ?? 'Compte supprimé',
                    'user_color' => $e['user_color'] ?? '#95A5A6',
                    'created_at' => $e['created_at'],
                ], CustodyActivityLog::getForSchedule($scheduleId)),
            ];
        });
    }

    public function documentsList(array $params): void
    {
        $this->requireAuth(true);
        $this->json(function () {
            $user = Session::user();
            $scheduleId = (int)($_GET['schedule_id'] ?? 0);
            if (!Custody::userHasAccessToSchedule($user['id'], $scheduleId)) return [];
            return Document::getForSchedules([$scheduleId]);
        });
    }

    public function documentsUpload(array $params): void
    {
        $this->requireAuth(true);
        $this->json(function () {
            $user = Session::user();
            $data = $_POST ?: $this->jsonInput();
            $scheduleId = (int)($data['schedule_id'] ?? 0);
            if (!Custody::userHasAccessToSchedule($user['id'], $scheduleId)) {
                return ['success' => false, 'error' => 'Accès non autorisé'];
            }
            $schedule = Custody::getScheduleById($scheduleId);
            $file = $_FILES['file'] ?? null;
            $data['custody_schedule_ids'] = [$scheduleId];
            $id = Document::create((int)$schedule['family_id'], $user['id'], $data, $file);
            CustodyActivityLog::record($scheduleId, $user['id'], 'document_uploaded', $data['title'] ?? null);
            return ['success' => true, 'id' => $id];
        });
    }

    /** Portail de liens : uniquement ceux marqués visibles pour un accès co-parent, sur les
     *  familles dont l'utilisateur a effectivement accès à au moins un planning de garde. */
    public function linksList(array $params): void
    {
        $this->requireAuth(true);
        $this->json(function () {
            $user = Session::user();
            $schedules = Custody::getSchedulesForUser((int)$user['id']);
            $scheduleIds = array_column($schedules, 'id');
            $familyIds = array_values(array_unique(array_map('intval', array_column($schedules, 'family_id'))));
            // Respecte la désactivation du module par une famille même pour cet accès restreint —
            // sans quoi désactiver "Portail de liens" côté famille n'aurait aucun effet ici.
            $familyIds = array_values(array_filter($familyIds, function ($fid) {
                $f = Family::findById($fid);
                return $f && !in_array('links', Family::getDisabledModules($f), true);
            }));
            return ['links' => PortalLink::getCoparentVisibleForFamilies($familyIds, $scheduleIds)];
        });
    }

    /** Un accès co-parent peut proposer un lien pour la famille du planning concerné — la
     *  proposition part toujours en attente de validation par un admin de cette famille, et
     *  est automatiquement marquée visible pour les accès co-parent (sans quoi elle n'aurait
     *  jamais d'intérêt pour la personne qui la propose). */
    public function linksPropose(array $params): void
    {
        $this->requireAuth(true);
        $this->json(function () {
            $user = Session::user();
            $data = $this->jsonInput();
            $scheduleId = (int)($data['schedule_id'] ?? 0);
            if (!Custody::userHasAccessToSchedule((int)$user['id'], $scheduleId)) {
                return ['success' => false, 'error' => 'Accès non autorisé.'];
            }
            $schedule = Custody::getScheduleById($scheduleId);
            $family = Family::findById((int)$schedule['family_id']);
            if (!$family || in_array('links', Family::getDisabledModules($family), true)) {
                return ['success' => false, 'error' => 'Le portail de liens est désactivé pour cette famille.'];
            }
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

            // Restreint au planning depuis lequel c'est proposé — pas d'intérêt à la rendre
            // visible aux accès co-parent d'un autre enfant de la même famille par défaut.
            PortalLink::create((int)$schedule['family_id'], (int)$user['id'], [
                'title'                 => $title,
                'url'                   => $url,
                'description'           => trim($data['description'] ?? ''),
                'image_path'            => $preview['image_path'],
                'visible_to_coparent'   => true,
                'coparent_schedule_ids' => [$scheduleId],
            ], 'pending');

            return ['success' => true];
        });
    }

    public function eventsList(array $params): void
    {
        $this->requireAuth(true);
        $this->json(function () {
            $user = Session::user();
            $scheduleId = (int)($_GET['schedule_id'] ?? 0);
            if (!Custody::userHasAccessToSchedule($user['id'], $scheduleId)) return [];
            $schedule = Custody::getScheduleById($scheduleId);
            if ($schedule) $this->applyScheduleTimezone($schedule);
            $start = $_GET['start'] ?? date('Y-m-01') . ' 00:00:00';
            $end = $_GET['end'] ?? date('Y-m-t') . ' 23:59:59';
            return Event::getForSchedules([$scheduleId], $start, $end);
        });
    }

    public function eventsCreate(array $params): void
    {
        $this->requireAuth(true);
        $this->json(function () {
            $user = Session::user();
            $data = $this->jsonInput();
            $scheduleId = (int)($data['schedule_id'] ?? 0);
            if (!Custody::userHasAccessToSchedule($user['id'], $scheduleId)) {
                return ['success' => false, 'error' => 'Accès non autorisé'];
            }
            $schedule = Custody::getScheduleById($scheduleId);
            $data['family_id'] = (int)$schedule['family_id'];
            $data['user_id'] = $user['id'];
            $data['custody_schedule_ids'] = [$scheduleId];
            $id = Event::create($data);
            CustodyActivityLog::record($scheduleId, $user['id'], 'event_created', $data['title'] ?? null);
            return ['success' => true, 'id' => $id];
        });
    }

    /**
     * Albums partagés : lecture seule sur les albums, à l'exception de l'ajout de
     * photos, autorisé pour un co-parent ayant accès au planning de garde auquel
     * l'album est rattaché. Création/modification/suppression d'albums ou de
     * photos restent réservées à l'admin de la famille (voir AlbumController).
     */
    public function albumsList(array $params): void
    {
        $this->requireAuth(true);
        $this->json(function () {
            $user = Session::user();
            $scheduleIds = array_column(Custody::getSchedulesForUser($user['id']), 'id');
            return Album::getForSchedules($scheduleIds);
        });
    }

    public function albumShow(array $params): void
    {
        $this->requireAuth(true);
        $this->json(function () use ($params) {
            $user = Session::user();
            $scheduleIds = array_column(Custody::getSchedulesForUser($user['id']), 'id');
            $album = Album::getForSchedulesById((int)$params['id'], $scheduleIds);
            if (!$album) return ['error' => 'Accès non autorisé'];
            return ['album' => $album, 'photos' => AlbumPhoto::getByAlbum($album['id'])];
        });
    }

    public function albumPhotoUpload(array $params): void
    {
        $this->requireAuth(true);
        $this->json(function () use ($params) {
            $user = Session::user();
            $scheduleIds = array_column(Custody::getSchedulesForUser($user['id']), 'id');
            $album = Album::getForSchedulesById((int)$params['id'], $scheduleIds);
            if (!$album) return ['success' => false, 'error' => 'Accès non autorisé'];

            $imagePath = $this->uploadImage('image');
            if (!$imagePath) {
                return ['success' => false, 'error' => "L'image est trop volumineuse (max 20 Mo) ou dans un format non supporté (JPEG, PNG, GIF, WebP uniquement)."];
            }

            $photoId = AlbumPhoto::create($album['id'], $user['id'], $imagePath, trim($_POST['caption'] ?? ''));
            Notification::notifyFamily(
                (int)$album['family_id'], $user['id'], 'albums', 'Nouvelle photo',
                $user['name'] . ' a ajouté une photo à l\'album "' . $album['title'] . '".',
                BASE_URL . '/albums/' . $album['id']
            );

            return ['success' => true, 'id' => $photoId, 'image_path' => $imagePath, 'user_name' => $user['name'], 'user_color' => $user['color']];
        });
    }
}
