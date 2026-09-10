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
