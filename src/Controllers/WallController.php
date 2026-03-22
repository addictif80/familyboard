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
        $content = trim($_POST['content'] ?? '');
        $imagePath = $this->uploadImage('image');

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
