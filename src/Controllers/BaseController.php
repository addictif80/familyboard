<?php
namespace App\Controllers;

use App\Core\Session;
use App\Models\User;

class BaseController
{
    protected function requireAuth(): void
    {
        if (!Session::isLoggedIn()) {
            if ($this->isAjax()) {
                http_response_code(401);
                echo json_encode(['error' => 'Non authentifié']);
                exit;
            }
            header('Location: ' . BASE_URL . '/login');
            exit;
        }
        $user = User::findById(Session::userId());
        if (!$user) {
            Session::destroy();
            header('Location: ' . BASE_URL . '/login');
            exit;
        }
        // Check if account has been blocked while logged in
        if (!empty($user['blocked_at'])) {
            Session::destroy();
            $reason = $user['blocked_reason'] ?? '';
            require BASE_PATH . '/templates/blocked.php';
            exit;
        }
        Session::set('user', $user);
    }

    protected function requireModule(string $slug): void
    {
        $user = Session::user();
        if (!$user) return;
        try {
            $family   = \App\Models\Family::findById($user['family_id']);
            $disabled = \App\Models\Family::getDisabledModules($family ?? []);
        } catch (\Throwable) {
            return; // column may not exist yet during migration
        }
        if (!in_array($slug, $disabled, true)) return;

        if ($this->isAjax()) {
            http_response_code(403);
            echo json_encode(['error' => 'Module désactivé.']);
            exit;
        }
        Session::flash('error', 'Ce module est désactivé par votre administrateur familial.');
        header('Location: ' . BASE_URL . '/');
        exit;
    }

    protected function requireAdmin(): void
    {
        $this->requireAuth();
        if (Session::user()['role'] !== 'admin') {
            if ($this->isAjax()) {
                http_response_code(403);
                echo json_encode(['error' => 'Non autorisé']);
                exit;
            }
            header('Location: ' . BASE_URL . '/');
            exit;
        }
    }

    protected function json(callable $fn): void
    {
        header('Content-Type: application/json');
        ob_start();
        try {
            $result = $fn();
            ob_end_clean(); // discard any spurious warnings/notices
            echo json_encode($result);
        } catch (\Throwable $e) {
            ob_end_clean();
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    protected function jsonInput(): array
    {
        $raw = file_get_contents('php://input');
        if ($raw) {
            $data = json_decode($raw, true);
            return $data ?: [];
        }
        return $_POST;
    }

    protected function isAjax(): bool
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
            || !empty($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json');
    }

    /**
     * Strip HTML to a safe subset (bold, italic, lists, links…).
     * Removes event handlers and javascript: hrefs.
     */
    protected function sanitizeHtml(string $html): string
    {
        $allowed = '<p><br><strong><b><em><i><u><s><h2><h3><ul><ol><li><blockquote><a><span><pre><code>';
        $html = strip_tags($html, $allowed);
        // Remove event-handler attributes and javascript: hrefs
        $html = preg_replace('/\s+on\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]*)/i', '', $html);
        $html = preg_replace('/href\s*=\s*["\']?\s*javascript:[^"\'>\s]*/i', 'href="#"', $html);
        return $html;
    }

    protected function uploadImage(string $field): ?string
    {
        if (!isset($_FILES[$field]) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) return null;
        $file = $_FILES[$field];
        if ($file['size'] > UPLOAD_MAX_SIZE) return null;
        if (!in_array($file['type'], ALLOWED_IMAGE_TYPES)) return null;

        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = bin2hex(random_bytes(16)) . '.' . strtolower($ext);
        $dest = UPLOAD_DIR . $filename;

        if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);
        if (move_uploaded_file($file['tmp_name'], $dest)) {
            return '/public/uploads/' . $filename;
        }
        return null;
    }
}
