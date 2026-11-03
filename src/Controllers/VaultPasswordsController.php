<?php
namespace App\Controllers;

use App\Core\Session;
use App\Models\VaultwardenSettings;

/**
 * Page dédiée au coffre-fort de mots de passe (Vaultwarden) — déplacée hors de /settings pour
 * correspondre au lien "🔐 Coffre-fort" du menu, qui ouvrait jusqu'ici directement l'instance
 * Vaultwarden dans un nouvel onglet sans passer par la gestion intégrée dans FamilyBoard
 * (voir public/js/vaultwarden-vault.js). Un seul endroit désormais : ce menu mène ici.
 */
class VaultPasswordsController extends BaseController
{
    public function index(array $params): void
    {
        $this->requireAuth();
        $user = Session::user();
        if ($user['role'] === 'coparent' || !VaultwardenSettings::get()) {
            Session::flash('error', "Le coffre-fort de mots de passe n'est pas disponible pour ce compte.");
            header('Location: ' . BASE_URL . '/');
            exit;
        }
        require BASE_PATH . '/templates/vault_passwords/index.php';
    }
}
