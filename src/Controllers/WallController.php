<?php
namespace App\Controllers;

use App\Core\Session;
use App\Core\Mail;
use App\Models\Post;
use App\Models\Follow;
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
        $posts = Post::getVisibleForUser($user['family_id'], (int)$user['id'], $visibleAuthorIds, 20);
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
        $pendingFollowRequests = Follow::getPendingForApproval((int)$user['id']);

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

        $postType = ($_POST['post_type'] ?? 'personal') === 'family' ? 'family' : 'personal';
        // Une publication "au nom de la famille" attend la validation d'un admin — sauf si
        // c'est justement un admin qui la publie : son approbation est déjà acquise.
        $status = ($postType === 'family' && $user['role'] !== 'admin') ? 'pending' : 'published';

        $postId = Post::create($user['family_id'], (int)$user['id'], $content, $imagePath, $postType, $status);

        if ($status === 'pending') {
            foreach (User::getByFamily($user['family_id']) as $admin) {
                if ($admin['role'] !== 'admin') continue;
                Notification::create((int)$admin['id'], 'wall',
                    'Publication à valider',
                    $user['name'] . ' propose une publication au nom de la famille.',
                    BASE_URL . '/wall');
            }
            Session::flash('success', 'Publication envoyée à l\'administrateur pour validation.');
            header('Location: ' . BASE_URL . '/wall');
            exit;
        }

        $this->notifyNewPost($user, $postType, $content);

        header('Location: ' . BASE_URL . '/wall');
        exit;
    }

    /** Notifie (cloche + e-mail) les personnes concernées par une publication qui vient
     *  d'être publiée : toute la famille si "famille", seulement les abonnés acceptés sinon. */
    private function notifyNewPost(array $user, string $postType, string $content): void
    {
        if ($postType === 'family') {
            Notification::notifyFamily((int)$user['family_id'], (int)$user['id'], 'wall', 'Nouveau post', $user['name'] . ' a publié sur le mur familial.', BASE_URL . '/wall');
            $recipients = array_values(array_filter(User::getByFamily($user['family_id']), fn($m) => (int)$m['id'] !== (int)$user['id']));
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
            if (!$post || $post['family_id'] !== $user['family_id']) {
                return ['success' => false];
            }
            if (!Post::isVisibleTo($post, (int)$user['id'], Follow::getVisibleAuthorIds((int)$user['id']))) {
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
            if (!$post || $post['family_id'] !== $user['family_id']) {
                return ['success' => false];
            }
            if (!Post::isVisibleTo($post, (int)$user['id'], Follow::getVisibleAuthorIds((int)$user['id']))) {
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
            $posts = Post::getVisibleForUser($user['family_id'], (int)$user['id'], $visibleAuthorIds, 10, $offset);
            foreach ($posts as &$post) {
                $post['comments'] = Post::getComments($post['id']);
                $post['user_reaction'] = Post::getUserReaction($post['id'], $user['id']);
            }
            return ['posts' => $posts, 'has_more' => count($posts) === 10];
        });
    }

    /** Partage une photo d'un de ses propres albums directement sur le mur, en tant que
     *  nouvelle publication personnelle (jamais celles des autres, même dans un album partagé). */
    public function sharePhoto(array $params): void
    {
        $this->requireAuth();
        $this->json(function () {
            $user = Session::user();
            $photoId = (int)($this->jsonInput()['photo_id'] ?? 0);
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

            $content = $this->sanitizeHtml(trim($this->jsonInput()['caption'] ?? ''));
            $postId = Post::create($user['family_id'], (int)$user['id'], $content, $newRelativePath, 'personal', 'published');
            $this->notifyNewPost($user, 'personal', $content);
            return ['success' => true, 'post_id' => $postId];
        });
    }
}
