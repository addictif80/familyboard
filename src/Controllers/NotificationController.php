<?php
namespace App\Controllers;

use App\Core\DateHelper;
use App\Core\Session;
use App\Models\Addition;
use App\Models\AlbumPhoto;
use App\Models\Custody;
use App\Models\CommLogMessage;
use App\Models\Document;
use App\Models\Event;
use App\Models\Notification;
use App\Models\Post;
use App\Models\Project;
use App\Models\SystemNotification;
use App\Models\TaskList;
use App\Models\User;
use App\Models\Warranty;

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
     * utilisateur, une notification peut toujours au moins s'afficher elle-même.
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
            $entity = $notif['entity_type'] ? $this->buildEntity($notif['entity_type'], (int)($notif['entity_id'] ?? 0), $familyId, $user) : null;

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

    /** @return array{icon:string, fields:array<array{label:string,value:string}>}|null */
    private function buildEntity(string $type, int $id, int $familyId, array $user): ?array
    {
        if (!$id) return null;
        return match ($type) {
            'event'          => $this->eventEntity($id, $familyId),
            'task'           => $this->taskEntity($id, $familyId),
            'custody_event'  => $this->custodyEventEntity($id, $familyId),
            'custody_schedule' => $this->custodyScheduleEntity($id, $familyId),
            'comm_log'       => $this->commLogEntity($id, $familyId),
            'post'           => $this->postEntity($id, $familyId, $user),
            'document'       => $this->documentEntity($id, $familyId),
            'warranty'       => $this->warrantyEntity($id, $familyId),
            'project'        => $this->projectEntity($id, $familyId),
            'album_photo'    => $this->albumPhotoEntity($id, $familyId),
            'addition'       => $this->additionEntity($id, $familyId),
            'system'         => $this->systemEntity($id),
            default          => null,
        };
    }

    private function eventEntity(int $id, int $familyId): ?array
    {
        $e = Event::getById($id);
        if (!$e || (int)$e['family_id'] !== $familyId) return null;
        $when = $e['is_all_day'] ? DateHelper::format($e['start_datetime'], 'j F Y') : DateHelper::format($e['start_datetime'], 'j F Y à H\hi');
        $fields = [
            ['label' => 'Titre', 'value' => $e['title']],
            ['label' => 'Date', 'value' => $when],
        ];
        if (!empty($e['user_name'])) $fields[] = ['label' => 'Ajouté par', 'value' => $e['user_name']];
        if (!empty($e['location'])) $fields[] = ['label' => 'Lieu', 'value' => $e['location']];
        if (!empty($e['description'])) $fields[] = ['label' => 'Détails', 'value' => $e['description']];
        return ['icon' => '📅', 'fields' => $fields];
    }

    private function taskEntity(int $id, int $familyId): ?array
    {
        $t = TaskList::getTask($id);
        if (!$t) return null;
        $list = TaskList::getById((int)$t['list_id']);
        if (!$list || (int)$list['family_id'] !== $familyId) return null;

        $fields = [
            ['label' => 'Titre', 'value' => $t['title']],
            ['label' => 'Liste', 'value' => $list['name']],
            ['label' => 'Statut', 'value' => $t['is_completed'] ? 'Terminée' : 'En attente'],
        ];
        if (!empty($t['due_date'])) $fields[] = ['label' => 'Échéance', 'value' => DateHelper::format($t['due_date'], 'j F Y')];
        if (!empty($t['assigned_to'])) {
            $assignee = User::findById((int)$t['assigned_to']);
            if ($assignee) $fields[] = ['label' => 'Assignée à', 'value' => $assignee['name']];
        }
        if (!empty($t['notes'])) $fields[] = ['label' => 'Notes', 'value' => $t['notes']];
        return ['icon' => '✅', 'fields' => $fields];
    }

    private function custodyEventEntity(int $id, int $familyId): ?array
    {
        $ev = Custody::getEvent($id);
        if (!$ev || (int)$ev['family_id'] !== $familyId) return null;
        $schedule = Custody::getScheduleById((int)$ev['schedule_id']);
        $dateLabel = $ev['start_date'] === $ev['end_date']
            ? DateHelper::format($ev['start_date'], 'j F Y')
            : DateHelper::format($ev['start_date'], 'j F Y') . ' → ' . DateHelper::format($ev['end_date'], 'j F Y');
        $fields = [
            ['label' => 'Enfant', 'value' => $schedule['child_name'] ?? '—'],
            ['label' => 'Date', 'value' => $dateLabel],
        ];
        if (!empty($ev['notes'])) $fields[] = ['label' => 'Notes', 'value' => $ev['notes']];
        return ['icon' => '👨‍👧', 'fields' => $fields];
    }

    private function custodyScheduleEntity(int $id, int $familyId): ?array
    {
        $s = Custody::getScheduleById($id);
        if (!$s || (int)$s['family_id'] !== $familyId) return null;
        return ['icon' => '👨‍👧', 'fields' => [
            ['label' => 'Enfant', 'value' => $s['child_name']],
        ]];
    }

    private function commLogEntity(int $id, int $familyId): ?array
    {
        $m = CommLogMessage::findById($id);
        if (!$m || (int)$m['family_id'] !== $familyId) return null;
        $author = User::findById((int)$m['user_id']);
        $fields = [
            ['label' => 'De', 'value' => $author['name'] ?? '—'],
            ['label' => 'Le', 'value' => DateHelper::format($m['created_at'], 'j F Y à H\hi')],
        ];
        if (!empty($m['content'])) $fields[] = ['label' => 'Message', 'value' => $m['content']];
        elseif (!empty($m['audio_path'])) $fields[] = ['label' => 'Message', 'value' => '🎤 Message vocal'];
        return ['icon' => '📓', 'fields' => $fields];
    }

    private function postEntity(int $id, int $familyId, array $user): ?array
    {
        $p = Post::getById($id);
        if (!$p || (int)$p['family_id'] !== $familyId) return null;
        // Une publication "en attente de validation" ne doit être prévisualisable que par son
        // auteur ou un admin — même règle de visibilité que sur le mur lui-même.
        if ($p['status'] === 'pending' && (int)$p['user_id'] !== (int)$user['id'] && $user['role'] !== 'admin') return null;

        $author = User::findById((int)$p['user_id']);
        $fields = [
            ['label' => 'Auteur', 'value' => $author['name'] ?? '—'],
            ['label' => 'Contenu', 'value' => strip_tags((string)$p['content'])],
        ];
        $commentCount = count(Post::getComments($id));
        if ($commentCount) $fields[] = ['label' => 'Commentaires', 'value' => (string)$commentCount];
        return ['icon' => '📝', 'fields' => $fields];
    }

    private function documentEntity(int $id, int $familyId): ?array
    {
        $d = Document::findById($id, $familyId);
        if (!$d) return null;
        $fields = [
            ['label' => 'Titre', 'value' => $d['title']],
            ['label' => 'Type', 'value' => $d['doc_type'] ?? '—'],
        ];
        if (!empty($d['expiry_date'])) $fields[] = ['label' => 'Expiration', 'value' => DateHelper::format($d['expiry_date'], 'j F Y')];
        return ['icon' => '📄', 'fields' => $fields];
    }

    private function warrantyEntity(int $id, int $familyId): ?array
    {
        $w = Warranty::findById($id, $familyId);
        if (!$w) return null;
        $fields = [
            ['label' => 'Article', 'value' => $w['title']],
        ];
        if (!empty($w['purchase_date'])) $fields[] = ['label' => "Date d'achat", 'value' => DateHelper::format($w['purchase_date'], 'j F Y')];
        if (!empty($w['warranty_end_date'])) $fields[] = ['label' => 'Fin de garantie', 'value' => DateHelper::format($w['warranty_end_date'], 'j F Y')];
        if (!empty($w['store'])) $fields[] = ['label' => 'Revendeur', 'value' => $w['store']];
        return ['icon' => '🧾', 'fields' => $fields];
    }

    private function projectEntity(int $id, int $familyId): ?array
    {
        $p = Project::getById($id);
        if (!$p || (int)$p['family_id'] !== $familyId) return null;
        $fields = [
            ['label' => 'Nom', 'value' => $p['name']],
            ['label' => 'Statut', 'value' => $p['status'] ?? '—'],
        ];
        if (!empty($p['description'])) $fields[] = ['label' => 'Description', 'value' => $p['description']];
        return ['icon' => '🗂️', 'fields' => $fields];
    }

    private function albumPhotoEntity(int $id, int $familyId): ?array
    {
        $photo = AlbumPhoto::getById($id);
        if (!$photo || (int)$photo['family_id'] !== $familyId) return null;
        $fields = [];
        if (!empty($photo['caption'])) $fields[] = ['label' => 'Légende', 'value' => $photo['caption']];
        $fields[] = ['label' => 'Image', 'value' => '🖼️ ' . BASE_URL . $photo['image_path']];
        return ['icon' => '🖼️', 'fields' => $fields];
    }

    private function additionEntity(int $id, int $familyId): ?array
    {
        $a = Addition::getById($id);
        if (!$a || (int)$a['family_id'] !== $familyId) return null;
        $fields = [
            ['label' => 'Titre', 'value' => $a['title']],
            ['label' => 'Montant total', 'value' => number_format((float)$a['total_amount'], 2, ',', ' ') . ' €'],
            ['label' => 'Créée par', 'value' => $a['creator_name']],
        ];
        return ['icon' => '💶', 'fields' => $fields];
    }

    private function systemEntity(int $id): ?array
    {
        $n = SystemNotification::findById($id);
        if (!$n) return null;
        return ['icon' => '🛡️', 'fields' => [
            ['label' => 'Annonce', 'value' => strip_tags((string)($n['content_html'] ?? $n['short_text'] ?? ''))],
        ]];
    }
}
