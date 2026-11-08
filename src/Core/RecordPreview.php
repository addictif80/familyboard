<?php
namespace App\Core;

use App\Models\Addition;
use App\Models\AlbumPhoto;
use App\Models\Baby;
use App\Models\CommLogMessage;
use App\Models\Contact;
use App\Models\Custody;
use App\Models\Document;
use App\Models\Event;
use App\Models\Post;
use App\Models\Project;
use App\Models\StockItem;
use App\Models\SystemNotification;
use App\Models\TaskList;
use App\Models\User;
use App\Models\Warranty;

/**
 * Point d'entrée unique pour la prévisualisation lecture seule d'un enregistrement, quel que
 * soit l'endroit d'où le clic part : une notification (NotificationController::preview()) ou
 * directement une ligne d'un widget du tableau de bord/une page de module (RecordController,
 * voir openRecordPreview() côté front, qui partage la même modal). Un seul endroit à faire
 * évoluer quand un nouveau type d'enregistrement doit devenir prévisualisable.
 */
class RecordPreview
{
    /** @return array{icon:string, fields:array<array{label:string,value:string}>}|null */
    public static function build(string $type, int $id, int $familyId, array $user): ?array
    {
        if (!$id) return null;
        return match ($type) {
            'event'            => self::eventEntity($id, $familyId),
            'task'             => self::taskEntity($id, $familyId),
            'custody_event'    => self::custodyEventEntity($id, $familyId),
            'custody_schedule' => self::custodyScheduleEntity($id, $familyId),
            'comm_log'         => self::commLogEntity($id, $familyId),
            'post'             => self::postEntity($id, $familyId, $user),
            'document'         => self::documentEntity($id, $familyId),
            'warranty'         => self::warrantyEntity($id, $familyId),
            'project'          => self::projectEntity($id, $familyId),
            'album_photo'      => self::albumPhotoEntity($id, $familyId),
            'addition'         => self::additionEntity($id, $familyId),
            'stock_item'       => self::stockItemEntity($id, $familyId),
            'user'             => self::userEntity($id, $familyId),
            'baby'             => self::babyEntity($id, $familyId),
            'contact'          => self::contactEntity($id, $familyId),
            'system'           => self::systemEntity($id),
            default            => null,
        };
    }

    private static function eventEntity(int $id, int $familyId): ?array
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

    private static function taskEntity(int $id, int $familyId): ?array
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

    private static function custodyEventEntity(int $id, int $familyId): ?array
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

    private static function custodyScheduleEntity(int $id, int $familyId): ?array
    {
        $s = Custody::getScheduleById($id);
        if (!$s || (int)$s['family_id'] !== $familyId) return null;
        return ['icon' => '👨‍👧', 'fields' => [
            ['label' => 'Enfant', 'value' => $s['child_name']],
        ]];
    }

    private static function commLogEntity(int $id, int $familyId): ?array
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

    private static function postEntity(int $id, int $familyId, array $user): ?array
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

    private static function documentEntity(int $id, int $familyId): ?array
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

    private static function warrantyEntity(int $id, int $familyId): ?array
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

    private static function projectEntity(int $id, int $familyId): ?array
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

    private static function albumPhotoEntity(int $id, int $familyId): ?array
    {
        $photo = AlbumPhoto::getById($id);
        if (!$photo || (int)$photo['family_id'] !== $familyId) return null;
        $fields = [];
        if (!empty($photo['caption'])) $fields[] = ['label' => 'Légende', 'value' => $photo['caption']];
        $fields[] = ['label' => 'Image', 'value' => '🖼️ ' . BASE_URL . $photo['image_path']];
        return ['icon' => '🖼️', 'fields' => $fields];
    }

    private static function additionEntity(int $id, int $familyId): ?array
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

    private static function stockItemEntity(int $id, int $familyId): ?array
    {
        $it = StockItem::findById($id, $familyId);
        if (!$it) return null;
        $loc = StockItem::LOCATIONS[$it['location']] ?? null;
        $fields = [
            ['label' => 'Article', 'value' => $it['name']],
            ['label' => 'Emplacement', 'value' => $loc['label'] ?? $it['location']],
            ['label' => 'Quantité', 'value' => $it['quantity'] . ($it['unit'] ? ' ' . $it['unit'] : '')],
        ];
        if (!empty($it['category'])) $fields[] = ['label' => 'Catégorie', 'value' => $it['category']];
        if (!empty($it['expiry_date'])) $fields[] = ['label' => 'Péremption', 'value' => DateHelper::format($it['expiry_date'], 'j F Y')];
        if (!empty($it['notes'])) $fields[] = ['label' => 'Notes', 'value' => $it['notes']];
        return ['icon' => '🧊', 'fields' => $fields];
    }

    private static function userEntity(int $id, int $familyId): ?array
    {
        $u = User::findById($id);
        if (!$u || (int)$u['family_id'] !== $familyId) return null;
        $fields = [['label' => 'Nom', 'value' => $u['name']]];
        if (!empty($u['birthday'])) $fields[] = ['label' => 'Anniversaire', 'value' => DateHelper::format($u['birthday'], 'j F')];
        return ['icon' => '🎂', 'fields' => $fields];
    }

    private static function babyEntity(int $id, int $familyId): ?array
    {
        $b = Baby::findById($id);
        if (!$b || (int)$b['family_id'] !== $familyId) return null;
        $fields = [['label' => 'Prénom', 'value' => $b['name']]];
        if (!empty($b['birth_date'])) $fields[] = ['label' => 'Naissance', 'value' => DateHelper::format($b['birth_date'], 'j F Y')];
        return ['icon' => '🍼', 'fields' => $fields];
    }

    private static function contactEntity(int $id, int $familyId): ?array
    {
        $c = Contact::getById($id);
        if (!$c || (int)$c['family_id'] !== $familyId) return null;
        $fields = [['label' => 'Nom', 'value' => trim($c['first_name'] . ' ' . $c['last_name'])]];
        if (!empty($c['phone'])) $fields[] = ['label' => 'Téléphone', 'value' => $c['phone']];
        if (!empty($c['email'])) $fields[] = ['label' => 'Email', 'value' => $c['email']];
        if (!empty($c['birthday'])) $fields[] = ['label' => 'Anniversaire', 'value' => DateHelper::format($c['birthday'], 'j F')];
        if (!empty($c['notes'])) $fields[] = ['label' => 'Notes', 'value' => $c['notes']];
        return ['icon' => '👤', 'fields' => $fields];
    }

    private static function systemEntity(int $id): ?array
    {
        $n = SystemNotification::findById($id);
        if (!$n) return null;
        return ['icon' => '🛡️', 'fields' => [
            ['label' => 'Annonce', 'value' => strip_tags((string)($n['content_html'] ?? $n['short_text'] ?? ''))],
        ]];
    }
}
