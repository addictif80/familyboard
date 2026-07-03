<?php
namespace App\Controllers;

use App\Core\Session;
use App\Core\Mail;
use App\Models\Post;
use App\Models\User;
use App\Models\Notification;

class WallController extends BaseController
{
    public function index(array $params): void
    {
        $this->requireAuth();
        $this->requireModule('wall');
        $user = Session::user();
        $posts = Post::getByFamily($user['family_id'], 20);
        foreach ($posts as &$post) {
            $post['comments'] = Post::getComments($post['id']);
            $post['user_reaction'] = Post::getUserReaction($post['id'], $user['id']);
        }
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

        $postId = Post::create($user['family_id'], $user['id'], $content, $imagePath);
        Notification::notifyFamily($user['family_id'], $user['id'], 'wall', 'Nouveau post', $user['name'] . ' a publié sur le mur familial.', BASE_URL . '/wall');

        // Email notification
        $members = User::getByFamily($user['family_id']);
        foreach ($members as $member) {
            if ($member['id'] !== $user['id']) {
                Mail::notifyUser(array_merge($member, ['family_id' => $user['family_id']]),
                    'Nouveau post de ' . $user['name'],
                    '<h2>' . htmlspecialchars($user['name']) . ' a publié sur le mur</h2><p>' . nl2br(htmlspecialchars($content)) . '</p><a href="' . BASE_URL . '/wall">Voir le mur</a>'
                );
            }
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
            $posts = Post::getByFamily($user['family_id'], 10, $offset);
            foreach ($posts as &$post) {
                $post['comments'] = Post::getComments($post['id']);
                $post['user_reaction'] = Post::getUserReaction($post['id'], $user['id']);
            }
            return ['posts' => $posts, 'has_more' => count($posts) === 10];
        });
    }
}
