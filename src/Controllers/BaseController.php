<?php
namespace App\Controllers;

use App\Core\Session;
use App\Models\User;

class BaseController
{
    /**
     * @param bool $allowCoparent Les comptes à accès restreint (role='coparent') n'ont, par
     *   défaut, accès à rien d'autre qu'au module Garde alternée / vue co-parent — passer
     *   true uniquement pour les contrôleurs qui gèrent explicitement leur accès restreint.
     */
    protected function requireAuth(bool $allowCoparent = false): void
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
            \App\Core\RememberMe::clear();
            Session::destroy();
            header('Location: ' . BASE_URL . '/login');
            exit;
        }
        // Check if account has been blocked while logged in
        if (!empty($user['blocked_at'])) {
            \App\Core\RememberMe::clear();
            Session::destroy();
            $reason = $user['blocked_reason'] ?? '';
            require BASE_PATH . '/templates/blocked.php';
            exit;
        }
        if (!$allowCoparent && $user['role'] === 'coparent') {
            if ($this->isAjax()) {
                http_response_code(403);
                echo json_encode(['error' => 'Accès restreint']);
                exit;
            }
            header('Location: ' . BASE_URL . '/');
            exit;
        }
        Session::set('user', $user);

        // Les appels fetch() JS envoient X-Requested-With/Accept (vérifié par isAjax()), ce qui
        // bloque déjà les soumissions de <form> cross-origin classiques vers ces endpoints ; le
        // jeton CSRF protège en plus les formulaires HTML POST natifs (listes, profil, mur...).
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$this->isAjax() && !\App\Core\Csrf::verify()) {
            http_response_code(403);
            echo 'Jeton de sécurité invalide ou expiré. Rechargez la page et réessayez.';
            exit;
        }
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

    /** Valide un code couleur hexadécimal (#rgb, #rrggbb…) ; retourne une valeur par défaut sinon. */
    protected function safeColor(?string $color, string $fallback = '#4A90D9'): string
    {
        return $color && preg_match('/^#[0-9a-fA-F]{3,8}$/', $color) ? $color : $fallback;
    }

    protected function uploadImage(string $field): ?string
    {
        if (!isset($_FILES[$field]) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) return null;
        $file = $_FILES[$field];
        if ($file['size'] > UPLOAD_MAX_SIZE) return null;

        // Ne jamais faire confiance au Content-Type ou au nom de fichier fournis par le
        // client (trivialement falsifiables) : on inspecte le contenu réel du fichier.
        $realType = @mime_content_type($file['tmp_name']) ?: '';
        $extByType = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
        if (!in_array($realType, ALLOWED_IMAGE_TYPES, true) || !isset($extByType[$realType])) return null;
        if (@getimagesize($file['tmp_name']) === false) return null;

        $filename = bin2hex(random_bytes(16)) . '.' . $extByType[$realType];
        $dest = UPLOAD_DIR . $filename;

        if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);
        if (move_uploaded_file($file['tmp_name'], $dest)) {
            return '/public/uploads/' . $filename;
        }
        return null;
    }
}
