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
        // Déconnexion globale demandée après l'établissement de cette session ("déconnecter tous
        // les appareils") : la session en cours doit être invalidée elle aussi.
        if (!empty($user['force_logout_at'])) {
            $forceLogoutTs = (new \DateTime($user['force_logout_at'], new \DateTimeZone('UTC')))->getTimestamp();
            if ((int)(Session::get('login_at') ?? 0) < $forceLogoutTs) {
                \App\Core\RememberMe::clear();
                Session::destroy();
                if ($this->isAjax()) {
                    http_response_code(401);
                    echo json_encode(['error' => 'Session déconnectée à distance.']);
                    exit;
                }
                header('Location: ' . BASE_URL . '/login');
                exit;
            }
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
        Session::set('user', Session::sanitizeUser($user));

        $tfaStatus = \App\Models\TwoFactorAuth::getPolicyStatus((int)$user['id']);
        if (!empty($tfaStatus['blocked']) && !$this->isTwoFactorSetupPath()) {
            if ($this->isAjax()) {
                http_response_code(403);
                echo json_encode(['error' => "La double authentification est obligatoire. Activez-la dans Paramètres pour continuer.", 'code' => 'two_factor_required']);
                exit;
            }
            Session::flash('error', 'La double authentification est désormais obligatoire. Activez-la ci-dessous pour continuer.');
            header('Location: ' . BASE_URL . '/settings');
            exit;
        }

        // Les appels fetch() JS envoient X-Requested-With/Accept (vérifié par isAjax()), ce qui
        // bloque déjà les soumissions de <form> cross-origin classiques vers ces endpoints ; le
        // jeton CSRF protège en plus les formulaires HTML POST natifs (listes, profil, mur...).
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$this->isAjax() && !\App\Core\Csrf::verify()) {
            http_response_code(403);
            echo 'Jeton de sécurité invalide ou expiré. Rechargez la page et réessayez.';
            exit;
        }
    }

    /** Chemins toujours accessibles même bloqué par la politique 2FA obligatoire : la page de
     *  paramètres (pour l'activer), ses propres routes 2FA, et la déconnexion. */
    private function isTwoFactorSetupPath(): bool
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
        foreach ([BASE_URL . '/settings', BASE_URL . '/logout'] as $allowed) {
            if ($path === $allowed || str_starts_with($path, $allowed . '/')) return true;
        }
        return false;
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

    /** Construit un en-tête Content-Disposition sûr pour un nom de fichier arbitraire (souvent
     *  fourni par l'utilisateur) : encodage RFC 6266 plutôt qu'un simple addslashes(), qui ne
     *  protège pas contre tous les caractères pouvant perturber le parsing par certains clients. */
    protected function contentDispositionHeader(string $filename, string $disposition = 'inline'): string
    {
        $ascii = preg_replace('/[^\x20-\x7E]/', '_', $filename);
        $ascii = str_replace(['"', '\\'], '_', $ascii);
        return sprintf(
            "Content-Disposition: %s; filename=\"%s\"; filename*=UTF-8''%s",
            $disposition,
            $ascii,
            rawurlencode($filename)
        );
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

    /** Raison du dernier rejet de uploadImage() (null si succès, ou si rien n'a été soumis) —
     *  les appelants qui veulent renvoyer un message précis à l'utilisateur (plutôt que de
     *  laisser un envoi refusé passer inaperçu, ex. un format non supporté) lisent cette
     *  propriété juste après l'appel. */
    protected ?string $lastUploadError = null;

    protected function uploadImage(string $field): ?string
    {
        $this->lastUploadError = null;
        if (!isset($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) return null;
        if ($_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
            $this->lastUploadError = 'Envoi du fichier interrompu ou trop volumineux.';
            return null;
        }
        $file = $_FILES[$field];
        if ($file['size'] > UPLOAD_MAX_SIZE) {
            $this->lastUploadError = 'Fichier trop volumineux (' . round(UPLOAD_MAX_SIZE / 1024 / 1024) . ' Mo maximum).';
            return null;
        }

        // Ne jamais faire confiance au Content-Type ou au nom de fichier fournis par le
        // client (trivialement falsifiables) : on inspecte le contenu réel du fichier.
        $realType = @mime_content_type($file['tmp_name']) ?: '';
        $extByType = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];

        // Les photos iPhone récentes sont par défaut au format HEIC/HEIF, jamais accepté par
        // ALLOWED_IMAGE_TYPES : converti en JPEG si l'extension Imagick le permet, plutôt que de
        // rejeter silencieusement l'immense majorité des photos envoyées depuis un iPhone. Le
        // fichier converti n'est plus "l'upload" au sens strict de PHP (move_uploaded_file ne
        // l'accepterait pas) — on le déplace donc explicitement avec rename() dans ce cas précis.
        $convertedPath = null;
        if (in_array($realType, ['image/heic', 'image/heif'], true) && class_exists(\Imagick::class)) {
            try {
                $convertedPath = tempnam(sys_get_temp_dir(), 'heic') . '.jpg';
                $img = new \Imagick($file['tmp_name']);
                $img->setImageFormat('jpeg');
                $img->writeImage($convertedPath);
                $img->destroy();
                $realType = 'image/jpeg';
            } catch (\Throwable) {
                @unlink($convertedPath);
                $this->lastUploadError = "Impossible de convertir cette photo (HEIC). Réessayez avec un JPEG/PNG.";
                return null;
            }
        }

        if (!in_array($realType, ALLOWED_IMAGE_TYPES, true) || !isset($extByType[$realType])) {
            if ($convertedPath) @unlink($convertedPath);
            $this->lastUploadError = in_array($realType, ['image/heic', 'image/heif'], true)
                ? "Format HEIC (photo iPhone) non supporté par ce serveur — réglez l'appareil photo sur \"Le plus compatible\" (JPEG) ou convertissez la photo avant l'envoi."
                : 'Format d\'image non supporté (JPEG, PNG, GIF ou WEBP uniquement).';
            return null;
        }
        $sourcePath = $convertedPath ?: $file['tmp_name'];
        if (@getimagesize($sourcePath) === false) {
            if ($convertedPath) @unlink($convertedPath);
            $this->lastUploadError = 'Fichier image invalide ou corrompu.';
            return null;
        }

        $filename = bin2hex(random_bytes(16)) . '.' . $extByType[$realType];
        $dest = UPLOAD_DIR . $filename;

        if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);
        $moved = $convertedPath ? rename($convertedPath, $dest) : move_uploaded_file($file['tmp_name'], $dest);
        if ($moved) {
            return '/public/uploads/' . $filename;
        }
        if ($convertedPath) @unlink($convertedPath);
        $this->lastUploadError = 'Échec de l\'enregistrement du fichier sur le serveur.';
        return null;
    }
}
