<?php
namespace App\Controllers;

use App\Core\Mail;
use App\Models\Family;
use App\Models\User;
use App\Models\Vault;

class VaultAccessController extends BaseController
{
    /** Public, unauthenticated : lien magique personnel d'une personne de confiance. */
    public function view(array $params): void
    {
        $token = $params['token'] ?? '';
        $trustee = Vault::findTrusteeByToken($token);
        if (!$trustee) {
            http_response_code(404);
            require BASE_PATH . '/templates/vault/access.php';
            return;
        }

        $family = Family::findById((int)$trustee['family_id']);
        $entries = $trustee['status'] === 'approved' ? Vault::getEntries((int)$trustee['family_id']) : [];

        require BASE_PATH . '/templates/vault/access.php';
    }

    /** Téléchargement d'une pièce jointe par une personne de confiance approuvée — authentifié
     *  par son propre token de lien magique (pas de session), jamais par l'id seul de l'entrée. */
    public function serveFile(array $params): void
    {
        $token = $params['token'] ?? '';
        $trustee = Vault::findTrusteeByToken($token);
        if (!$trustee || $trustee['status'] !== 'approved') {
            http_response_code(403);
            echo 'Accès refusé.';
            return;
        }

        $entry = Vault::getEntryById((int)$params['id']);
        if (!$entry || (int)$entry['family_id'] !== (int)$trustee['family_id'] || !$entry['file_path']) {
            http_response_code(404);
            echo 'Introuvable.';
            return;
        }
        $path = BASE_PATH . $entry['file_path'];
        if (!file_exists($path)) {
            http_response_code(404);
            echo 'Introuvable.';
            return;
        }

        header('Content-Type: ' . $entry['file_mime']);
        header('Content-Length: ' . filesize($path));
        header($this->contentDispositionHeader($entry['file_original']));
        header('Cache-Control: private, max-age=3600');
        readfile($path);
        exit;
    }

    /** Téléchargement de tout le coffre-fort en une fois (ZIP) par une personne de confiance
     *  approuvée — même authentification par token que serveFile() ci-dessus. */
    public function downloadAll(array $params): void
    {
        $token = $params['token'] ?? '';
        $trustee = Vault::findTrusteeByToken($token);
        if (!$trustee || $trustee['status'] !== 'approved') {
            http_response_code(403);
            echo 'Accès refusé.';
            return;
        }

        $zipPath = Vault::buildZip((int)$trustee['family_id']);
        header('Content-Type: application/zip');
        header('Content-Length: ' . filesize($zipPath));
        header($this->contentDispositionHeader('coffre-fort.zip', 'attachment'));
        readfile($zipPath);
        unlink($zipPath);
        exit;
    }

    /** Déclenche une demande d'accès d'urgence — notifie les administrateurs de la famille,
     *  qui devront valider depuis l'application (jamais d'ouverture automatique par e-mail). */
    public function request(array $params): void
    {
        $this->json(function () use ($params) {
            $token = $params['token'] ?? '';
            $trustee = Vault::findTrusteeByToken($token);
            if (!$trustee) return ['success' => false, 'error' => 'Lien invalide.'];
            if ($trustee['status'] === 'approved') return ['success' => true, 'status' => 'approved'];
            if ($trustee['status'] === 'requested') return ['success' => true, 'status' => 'requested'];

            Vault::requestAccess((int)$trustee['id']);

            $family = Family::findById((int)$trustee['family_id']);
            $familyName = $family['name'] ?? 'votre famille';
            $admins = array_filter(User::getByFamily((int)$trustee['family_id']), fn($u) => $u['role'] === 'admin');
            $subject = 'Demande d\'accès au coffre-fort numérique — ' . $familyName;
            $body = '<p>' . htmlspecialchars($trustee['name']) . ' (' . htmlspecialchars($trustee['email']) . ') demande un accès '
                . 'd\'urgence au coffre-fort numérique de ' . htmlspecialchars($familyName) . '.</p>'
                . '<p>Connectez-vous à FamilyBoard, module « Coffre-fort », pour valider ou refuser cette demande.</p>';
            foreach ($admins as $admin) {
                Mail::send((int)$trustee['family_id'], $admin['email'], $admin['name'], $subject, $body, 'vault_access_request');
            }

            return ['success' => true, 'status' => 'requested'];
        });
    }
}
