<?php
namespace App\Controllers;

use App\Core\Session;
use App\Core\Mail;
use App\Models\Post;
use App\Models\Follow;
use App\Models\FamilyFriend;
use App\Models\Album;
use App\Models\User;
use App\Models\Notification;
use App\Models\EmailContent;
use App\Core\EmailLayout;

class WallController extends BaseController
{
    public function index(array $params): void
    {
        $this->requireAuth();
        $this->requireModule('wall');
        $user = Session::user();
        $visibleAuthorIds = Follow::getVisibleAuthorIds((int)$user['id']);
        $friendFamilies = FamilyFriend::getAcceptedFor((int)$user['family_id']);
        $friendFamilyIds = array_map(fn($f) => (int)$f['family_id'], $friendFamilies);
        $posts = Post::getVisibleForUser($user['family_id'], (int)$user['id'], $visibleAuthorIds, $friendFamilyIds, 20);
        foreach ($posts as &$post) {
            $post['comments'] = Post::getComments($post['id']);
            $post['user_reaction'] = Post::getUserReaction($post['id'], $user['id']);
        }
        unset($post);

        $pendingPosts = $user['role'] === 'admin' ? Post::getPendingByFamily($user['family_id']) : [];

        // Panneau "Membres" : qui je suis / qui me suit / demandes en attente, pour gérer les
        // abonnements directement depuis le mur plutôt que sur une page séparée.
        $members = array_values(array_filter(
            User::getByFamily($user['family_id']),
            fn($m) => (int)$m['id'] !== (int)$user['id'] && $m['role'] !== 'coparent'
        ));
        foreach ($members as &$m) {
            $m['follow_status'] = Follow::status((int)$user['id'], (int)$m['id']);
            $m['follows_me'] = Follow::isAccepted((int)$m['id'], (int)$user['id']);
        }
        unset($m);

        // Membres des familles amies : même mécanique de suivi que ci-dessus, mais groupés par
        // famille pour rester lisibles dans le panneau "Membres" (scope "Amis" du mur social).
        $friendFamilyMembers = [];
        foreach ($friendFamilies as $ff) {
            $famMembers = array_values(array_filter(
                User::getByFamily((int)$ff['family_id']),
                fn($m) => $m['role'] !== 'coparent'
            ));
            foreach ($famMembers as &$m) {
                $m['follow_status'] = Follow::status((int)$user['id'], (int)$m['id']);
                $m['follows_me'] = Follow::isAccepted((int)$m['id'], (int)$user['id']);
            }
            unset($m);
            if ($famMembers) {
                $friendFamilyMembers[] = ['family_name' => $ff['family_name'], 'members' => $famMembers];
            }
        }

        $pendingFollowRequests = Follow::getPendingForApproval((int)$user['id']);

        // Onglet "Photos" : albums que leurs propriétaires ont choisi d'afficher sur le mur,
        // filtrés avec exactement les mêmes règles de portée que les publications.
        $wallAlbums = Album::getWallVisibleForUser((int)$user['family_id'], $visibleAuthorIds, $friendFamilyIds);
        foreach ($wallAlbums as &$wa) {
            $wa['photos'] = \App\Models\AlbumPhoto::getByAlbum((int)$wa['id']);
        }
        unset($wa);

        require BASE_PATH . '/templates/wall/index.php';
    }

    public function create(array $params): void
    {
        $this->requireAuth();
        $user = Session::user();
        $content = $this->sanitizeHtml(trim($_POST['content'] ?? ''));
        $imageAttempted = !empty($_FILES['image']['name']);
        $imagePath = $this->uploadImage('image');

        if ($imageAttempted && !$imagePath) {
            Session::flash('error', 'L\'image est trop volumineuse (max 20 Mo) ou dans un format non supporté (JPEG, PNG, GIF, WebP uniquement).');
            header('Location: ' . BASE_URL . '/wall');
            exit;
        }

        if (!$content && !$imagePath) {
            Session::flash('error', 'Le post ne peut pas être vide.');
            header('Location: ' . BASE_URL . '/wall');
            exit;
        }

        $postType = in_array($_POST['post_type'] ?? '', ['family', 'network'], true) ? $_POST['post_type'] : 'personal';
        // Une publication "au nom de la famille" attend la validation d'un admin — sauf si
        // c'est justement un admin qui la publie : son approbation est déjà acquise. Les
        // publications "amis" et "familles amies" sont, elles, toujours publiées immédiatement.
        $status = ($postType === 'family' && $user['role'] !== 'admin') ? 'pending' : 'published';

        $postId = Post::create($user['family_id'], (int)$user['id'], $content, $imagePath, $postType, $status);

        if ($status === 'pending') {
            $this->notifyAdminsPending($user);
            Session::flash('success', 'Publication envoyée à l\'administrateur pour validation.');
            header('Location: ' . BASE_URL . '/wall');
            exit;
        }

        $this->notifyNewPost($user, $postType, $content);

        header('Location: ' . BASE_URL . '/wall');
        exit;
    }

    /** Prévient les admins de la famille de $user qu'une publication "famille" attend leur validation. */
    private function notifyAdminsPending(array $user): void
    {
        foreach (User::getByFamily($user['family_id']) as $admin) {
            if ($admin['role'] !== 'admin') continue;
            Notification::create((int)$admin['id'], 'wall',
                'Publication à valider',
                $user['name'] . ' propose une publication au nom de la famille.',
                BASE_URL . '/wall');
        }
    }

    /** Notifie (cloche + e-mail) les personnes concernées par une publication qui vient d'être
     *  publiée : toute la famille si "famille", ma famille + toutes mes familles amies si
     *  "familles amies", seulement les abonnés acceptés (même famille ou famille amie) sinon. */
    private function notifyNewPost(array $user, string $postType, string $content): void
    {
        if ($postType === 'family') {
            Notification::notifyFamily((int)$user['family_id'], (int)$user['id'], 'wall', 'Nouveau post', $user['name'] . ' a publié sur le mur familial.', BASE_URL . '/wall');
            $recipients = array_values(array_filter(User::getByFamily($user['family_id']), fn($m) => (int)$m['id'] !== (int)$user['id']));
        } elseif ($postType === 'network') {
            Notification::notifyNetwork((int)$user['family_id'], (int)$user['id'], 'wall', 'Nouveau post', $user['name'] . ' a publié pour les familles amies.', BASE_URL . '/wall');
            $recipients = array_values(array_filter(User::getByFamily($user['family_id']), fn($m) => (int)$m['id'] !== (int)$user['id']));
            foreach (FamilyFriend::getAcceptedFor((int)$user['family_id']) as $ff) {
                foreach (User::getByFamily((int)$ff['family_id']) as $m) {
                    if ($m['role'] !== 'coparent') $recipients[] = $m;
                }
            }
        } else {
            $recipients = [];
            foreach (Follow::getFollowers((int)$user['id']) as $follower) {
                Notification::create((int)$follower['follower_id'], 'wall', 'Nouveau post', $user['name'] . ' a publié.', BASE_URL . '/wall');
                $u = User::findById((int)$follower['follower_id']);
                if ($u) $recipients[] = $u;
            }
        }

        if (!$recipients) return;
        $rendered = EmailContent::render('wall_post', [
            'author_name' => $user['name'],
            'content'     => $content,
        ]);
        $html = EmailLayout::render($rendered['subject'], $rendered['message_html'], [
            'label' => 'Voir le mur',
            'url'   => BASE_URL . '/wall',
        ]);
        foreach ($recipients as $member) {
            Mail::notifyUser(array_merge($member, ['family_id' => $user['family_id']]), $rendered['subject'], $html);
        }
    }

    public function approve(array $params): void
    {
        $this->requireAdmin();
        $user = Session::user();
        $post = Post::getById((int)$params['id']);
        if ($post && $post['family_id'] === $user['family_id'] && $post['status'] === 'pending') {
            Post::approve((int)$post['id'], (int)$user['id']);
            Notification::create((int)$post['user_id'], 'wall', 'Publication validée',
                'Votre publication au nom de la famille a été publiée.', BASE_URL . '/wall');
            $this->notifyNewPost(User::findById((int)$post['user_id']), 'family', $post['content']);
        }
        header('Location: ' . BASE_URL . '/wall');
        exit;
    }

    public function reject(array $params): void
    {
        $this->requireAdmin();
        $user = Session::user();
        $post = Post::getById((int)$params['id']);
        if ($post && $post['family_id'] === $user['family_id'] && $post['status'] === 'pending') {
            Post::reject((int)$post['id'], (int)$user['id']);
            Notification::create((int)$post['user_id'], 'wall', 'Publication refusée',
                'Votre publication proposée au nom de la famille n\'a pas été retenue.', BASE_URL . '/wall');
        }
        header('Location: ' . BASE_URL . '/wall');
        exit;
    }

    public function delete(array $params): void
    {
        $this->requireAuth();
        $user = Session::user();
        $id = (int)$params['id'];
        $post = Post::getById($id);
        if ($post && ($post['user_id'] === $user['id'] || $user['role'] === 'admin') && $post['family_id'] === $user['family_id']) {
            if ($post['image_path'] && file_exists(BASE_PATH . '/public' . $post['image_path'])) {
                unlink(BASE_PATH . '/public' . $post['image_path']);
            }
            Post::delete($id);
        }
        header('Location: ' . BASE_URL . '/wall');
        exit;
    }

    public function update(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $id = (int)$params['id'];
            $post = Post::getById($id);

            if (!$post || $post['family_id'] !== $user['family_id']) {
                return ['success' => false, 'error' => 'Post introuvable.'];
            }
            if ($post['user_id'] !== $user['id'] && $user['role'] !== 'admin') {
                return ['success' => false, 'error' => 'Non autorisé.'];
            }

            $content = $this->sanitizeHtml(trim($this->jsonInput()['content'] ?? ''));
            // Quill empty editor outputs '<p><br></p>'
            if (!$content || $content === '<p><br></p>') {
                return ['success' => false, 'error' => 'Le contenu ne peut pas être vide.'];
            }

            Post::update($id, $content);
            return ['success' => true, 'content' => $content];
        });
    }

    public function addComment(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $postId = (int)$params['id'];
            $post = Post::getById($postId);
            if (!$post || !$this->postVisible($post, $user)) {
                return ['success' => false];
            }
            $content = trim($this->jsonInput()['content'] ?? '');
            if (!$content) return ['success' => false];

            $commentId = Post::addComment($postId, $user['id'], $content);

            if ((int)$post['user_id'] !== $user['id']) {
                Notification::create($post['user_id'], 'wall', 'Nouveau commentaire',
                    $user['name'] . ' a commenté votre post : ' . mb_strimwidth($content, 0, 80, '…'),
                    BASE_URL . '/wall');
            }

            return ['success' => true, 'comment' => [
                'id' => $commentId,
                'user_name' => $user['name'],
                'user_color' => $user['color'],
                'user_avatar' => $user['avatar'],
                'content' => $content,
                'created_at' => date('Y-m-d H:i:s'),
            ]];
        });
    }

    public function toggleReaction(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $postId = (int)$params['id'];
            $post = Post::getById($postId);
            if (!$post || !$this->postVisible($post, $user)) {
                return ['success' => false];
            }
            $action = Post::toggleReaction($postId, $user['id']);
            $count = \App\Core\Database::fetch('SELECT COUNT(*) as c FROM post_reactions WHERE post_id=?', [$postId])['c'];

            if ($action === 'added' && (int)$post['user_id'] !== $user['id']) {
                Notification::create($post['user_id'], 'wall', 'Nouvelle réaction',
                    $user['name'] . ' a réagi ❤️ à votre post.', BASE_URL . '/wall');
            }

            return ['success' => true, 'action' => $action, 'count' => $count];
        });
    }

    public function loadMore(array $params): void
    {
        $this->requireAuth();
        $this->json(function () {
            $user = Session::user();
            $offset = (int)($_GET['offset'] ?? 0);
            $visibleAuthorIds = Follow::getVisibleAuthorIds((int)$user['id']);
            $friendFamilyIds = FamilyFriend::getAcceptedFamilyIds((int)$user['family_id']);
            $posts = Post::getVisibleForUser($user['family_id'], (int)$user['id'], $visibleAuthorIds, $friendFamilyIds, 10, $offset);
            foreach ($posts as &$post) {
                $post['comments'] = Post::getComments($post['id']);
                $post['user_reaction'] = Post::getUserReaction($post['id'], $user['id']);
            }
            return ['posts' => $posts, 'has_more' => count($posts) === 10];
        });
    }

    /** Vrai si $user peut voir $post, quelle que soit sa famille — centralise l'appel à
     *  Post::isVisibleTo() avec le contexte (abonnements + familles amies) de $user. */
    private function postVisible(array $post, array $user): bool
    {
        $visibleAuthorIds = Follow::getVisibleAuthorIds((int)$user['id']);
        $friendFamilyIds = FamilyFriend::getAcceptedFamilyIds((int)$user['family_id']);
        return Post::isVisibleTo($post, (int)$user['id'], (int)$user['family_id'], $visibleAuthorIds, $friendFamilyIds);
    }

    /** Mes propres photos d'albums, pour le sélecteur "Depuis un album" du formulaire de publication. */
    public function myAlbumPhotos(array $params): void
    {
        $this->requireAuth();
        $this->json(function () {
            $user = Session::user();
            return ['photos' => \App\Models\AlbumPhoto::getByUser((int)$user['id'])];
        });
    }

    /** Partage une photo d'un de ses propres albums directement sur le mur, en tant que
     *  nouvelle publication personnelle (jamais celles des autres, même dans un album partagé). */
    public function sharePhoto(array $params): void
    {
        $this->requireAuth();
        $this->json(function () {
            $user = Session::user();
            $data = $this->jsonInput();
            $photoId = (int)($data['photo_id'] ?? 0);
            $photo = \App\Models\AlbumPhoto::getById($photoId);
            if (!$photo || (int)$photo['user_id'] !== (int)$user['id'] || $photo['family_id'] !== $user['family_id']) {
                return ['success' => false, 'error' => 'Vous ne pouvez partager que vos propres photos.'];
            }
            // Copie physique du fichier plutôt que réutiliser le même chemin : la suppression de
            // la photo dans l'album (qui efface le fichier disque) ne doit jamais casser
            // l'image d'une publication déjà partagée sur le mur — les deux doivent pouvoir
            // vivre et être supprimées indépendamment l'une de l'autre.
            $sourcePath = BASE_PATH . '/public' . $photo['image_path'];
            $ext = pathinfo($photo['image_path'], PATHINFO_EXTENSION) ?: 'jpg';
            $newRelativePath = null;
            if (is_file($sourcePath)) {
                $newFilename = bin2hex(random_bytes(16)) . '.' . $ext;
                if (copy($sourcePath, UPLOAD_DIR . $newFilename)) {
                    $newRelativePath = '/public/uploads/' . $newFilename;
                }
            }
            if (!$newRelativePath) {
                return ['success' => false, 'error' => 'Photo introuvable sur le serveur.'];
            }

            $content = $this->sanitizeHtml(trim($data['caption'] ?? ''));
            $postType = in_array($data['scope'] ?? '', ['family', 'network'], true) ? $data['scope'] : 'personal';
            $status = ($postType === 'family' && $user['role'] !== 'admin') ? 'pending' : 'published';
            $postId = Post::create($user['family_id'], (int)$user['id'], $content, $newRelativePath, $postType, $status);
            if ($status === 'pending') {
                $this->notifyAdminsPending($user);
            } else {
                $this->notifyNewPost($user, $postType, $content);
            }
            return ['success' => true, 'post_id' => $postId, 'pending' => $status === 'pending'];
        });
    }
}
