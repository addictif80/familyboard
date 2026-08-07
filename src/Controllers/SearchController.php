<?php
namespace App\Controllers;

use App\Core\Database;
use App\Core\Session;
use App\Models\Family;
use App\Models\Follow;
use App\Models\Post;

/**
 * Recherche globale dans le contenu réel accessible à l'utilisateur (tâches, événements,
 * contacts, documents...), en plus de la recherche dans les pages/modules déjà gérée
 * côté client. Réservée aux membres/admins connectés — un accès co-parent n'a de toute
 * façon pas cette interface (voir templates/layout.php).
 */
class SearchController extends BaseController
{
    public function search(array $params): void
    {
        $this->requireAuth();
        $this->json(function () {
            $user = Session::user();
            $q = trim((string)($_GET['q'] ?? ''));
            if (mb_strlen($q) < 2) return ['results' => []];
            $q = mb_substr($q, 0, 80);

            $familyId = (int)$user['family_id'];
            $like = '%' . strtr($q, ['%' => '\%', '_' => '\_']) . '%';

            $disabled = Family::getDisabledModules(Family::findById($familyId) ?? []);
            $enabled = fn(string $slug) => !in_array($slug, $disabled, true);

            $results = [];

            if ($enabled('tasks')) {
                foreach (Database::fetchAll(
                    'SELECT t.id, t.title FROM tasks t JOIN task_lists tl ON tl.id = t.list_id
                     WHERE tl.family_id = ? AND t.title LIKE ? ORDER BY t.created_at DESC LIMIT 5',
                    [$familyId, $like]
                ) as $r) {
                    $results[] = ['group' => 'Tâches', 'icon' => '✅', 'label' => $r['title'], 'url' => '/tasks'];
                }
            }

            if ($enabled('calendar')) {
                foreach (Database::fetchAll(
                    'SELECT id, title FROM events WHERE family_id = ? AND title LIKE ? ORDER BY start_datetime DESC LIMIT 5',
                    [$familyId, $like]
                ) as $r) {
                    $results[] = ['group' => 'Calendrier', 'icon' => '📅', 'label' => $r['title'], 'url' => '/calendar'];
                }
            }

            if ($enabled('contacts')) {
                foreach (Database::fetchAll(
                    "SELECT id, first_name, last_name FROM contacts
                     WHERE family_id = ? AND (first_name LIKE ? OR last_name LIKE ? OR email LIKE ?)
                     ORDER BY first_name LIMIT 5",
                    [$familyId, $like, $like, $like]
                ) as $r) {
                    $results[] = ['group' => 'Répertoire', 'icon' => '📒', 'label' => trim($r['first_name'] . ' ' . $r['last_name']), 'url' => '/contacts'];
                }
            }

            if ($enabled('documents')) {
                foreach (Database::fetchAll(
                    'SELECT id, title FROM documents WHERE family_id = ? AND (title LIKE ? OR tags LIKE ?) ORDER BY created_at DESC LIMIT 5',
                    [$familyId, $like, $like]
                ) as $r) {
                    $results[] = ['group' => 'Documents', 'icon' => '🗂️', 'label' => $r['title'], 'url' => '/documents'];
                }
            }

            if ($enabled('warranties')) {
                foreach (Database::fetchAll(
                    'SELECT id, title FROM warranties WHERE family_id = ? AND (title LIKE ? OR brand LIKE ? OR model LIKE ?) ORDER BY created_at DESC LIMIT 5',
                    [$familyId, $like, $like, $like]
                ) as $r) {
                    $results[] = ['group' => 'Garanties', 'icon' => '🛡️', 'label' => $r['title'], 'url' => '/warranties'];
                }
            }

            if ($enabled('budget')) {
                foreach (Database::fetchAll(
                    'SELECT id, title FROM budget_transactions WHERE family_id = ? AND title LIKE ? ORDER BY date DESC LIMIT 5',
                    [$familyId, $like]
                ) as $r) {
                    $results[] = ['group' => 'Budget', 'icon' => '💰', 'label' => $r['title'], 'url' => '/budget'];
                }
            }

            if ($enabled('projects')) {
                foreach (Database::fetchAll(
                    'SELECT id, name FROM projects WHERE family_id = ? AND name LIKE ? ORDER BY created_at DESC LIMIT 5',
                    [$familyId, $like]
                ) as $r) {
                    $results[] = ['group' => 'Projets', 'icon' => '📋', 'label' => $r['name'], 'url' => '/projects/' . (int)$r['id']];
                }
            }

            if ($enabled('wishlist')) {
                foreach (Database::fetchAll(
                    'SELECT id, title FROM wishlist_items WHERE family_id = ? AND title LIKE ? ORDER BY created_at DESC LIMIT 5',
                    [$familyId, $like]
                ) as $r) {
                    $results[] = ['group' => 'Liste de cadeaux', 'icon' => '🎁', 'label' => $r['title'], 'url' => '/wishlist'];
                }
            }

            if ($enabled('polls')) {
                foreach (Database::fetchAll(
                    'SELECT id, question FROM polls WHERE family_id = ? AND question LIKE ? ORDER BY created_at DESC LIMIT 5',
                    [$familyId, $like]
                ) as $r) {
                    $results[] = ['group' => 'Sondages', 'icon' => '🗳️', 'label' => $r['question'], 'url' => '/polls'];
                }
            }

            if ($enabled('links')) {
                foreach (Database::fetchAll(
                    "SELECT id, title FROM portal_links
                     WHERE (family_id = ? OR family_id IS NULL) AND status = 'approved' AND title LIKE ?
                     ORDER BY created_at DESC LIMIT 5",
                    [$familyId, $like]
                ) as $r) {
                    $results[] = ['group' => 'Portail de liens', 'icon' => '🔗', 'label' => $r['title'], 'url' => '/links'];
                }
            }

            if ($enabled('meals')) {
                foreach (Database::fetchAll(
                    'SELECT id, title FROM recipes WHERE family_id = ? AND title LIKE ? ORDER BY created_at DESC LIMIT 5',
                    [$familyId, $like]
                ) as $r) {
                    $results[] = ['group' => 'Repas', 'icon' => '🍽️', 'label' => $r['title'], 'url' => '/meals'];
                }
            }

            if ($enabled('wall')) {
                $visibleAuthorIds = Follow::getVisibleAuthorIds((int)$user['id']);
                $friendFamilyIds = \App\Models\FamilyFriend::getAcceptedFamilyIds($familyId);
                foreach (Post::searchVisible($familyId, (int)$user['id'], $visibleAuthorIds, $friendFamilyIds, $like, 5) as $r) {
                    $excerpt = mb_strimwidth(trim(strip_tags((string)$r['content'])), 0, 80, '…');
                    if ($excerpt === '') continue;
                    $results[] = ['group' => 'Mur familial', 'icon' => '📸', 'label' => $excerpt, 'url' => '/wall'];
                }
            }

            if ($enabled('additions')) {
                foreach (Database::fetchAll(
                    'SELECT id, title, is_cagnotte FROM additions WHERE family_id = ? AND (title LIKE ? OR description LIKE ?) ORDER BY created_at DESC LIMIT 5',
                    [$familyId, $like, $like]
                ) as $r) {
                    $results[] = [
                        'group' => $r['is_cagnotte'] ? 'Cagnotte' : 'Additions',
                        'icon'  => $r['is_cagnotte'] ? '🐷' : '🧾',
                        'label' => $r['title'],
                        'url'   => '/additions',
                    ];
                }
            }

            if ($enabled('albums')) {
                foreach (Database::fetchAll(
                    'SELECT id, title FROM albums WHERE family_id = ? AND title LIKE ? ORDER BY created_at DESC LIMIT 5',
                    [$familyId, $like]
                ) as $r) {
                    $results[] = ['group' => 'Albums photo', 'icon' => '🖼️', 'label' => $r['title'], 'url' => '/albums/' . (int)$r['id']];
                }
            }

            if ($enabled('chat')) {
                foreach (Database::fetchAll(
                    'SELECT id, content FROM messages WHERE family_id = ? AND content LIKE ? ORDER BY created_at DESC LIMIT 5',
                    [$familyId, $like]
                ) as $r) {
                    $excerpt = mb_strimwidth(trim((string)$r['content']), 0, 80, '…');
                    if ($excerpt === '') continue;
                    $results[] = ['group' => 'Chat familial', 'icon' => '💬', 'label' => $excerpt, 'url' => '/chat'];
                }
            }

            if ($enabled('comm_log')) {
                foreach (Database::fetchAll(
                    'SELECT id, content FROM comm_log_messages WHERE family_id = ? AND content LIKE ? ORDER BY created_at DESC LIMIT 5',
                    [$familyId, $like]
                ) as $r) {
                    $excerpt = mb_strimwidth(trim((string)$r['content']), 0, 80, '…');
                    if ($excerpt === '') continue;
                    $results[] = ['group' => 'Journal parental', 'icon' => '📝', 'label' => $excerpt, 'url' => '/comm-log'];
                }
            }

            // Messages privés : toujours cherchés (pas de bascule de module — fonctionnalité liée au
            // compte, pas à la famille), strictement limités aux fils où l'utilisateur est partie
            // prenante — jamais une conversation entre deux tiers.
            $myId = (int)$user['id'];
            foreach (Database::fetchAll(
                'SELECT dm.id, dm.content, IF(t.user_a_id = ?, t.user_b_id, t.user_a_id) as other_user_id
                 FROM dm_messages dm JOIN dm_threads t ON t.id = dm.thread_id
                 WHERE (t.user_a_id = ? OR t.user_b_id = ?) AND dm.content LIKE ?
                 ORDER BY dm.created_at DESC LIMIT 5',
                [$myId, $myId, $myId, $like]
            ) as $r) {
                $excerpt = mb_strimwidth(trim((string)$r['content']), 0, 80, '…');
                if ($excerpt === '') continue;
                $results[] = ['group' => 'Messages privés', 'icon' => '✉️', 'label' => $excerpt, 'url' => '/messages/' . (int)$r['other_user_id']];
            }

            return ['results' => array_slice($results, 0, 40)];
        });
    }
}
