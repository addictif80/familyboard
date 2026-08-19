<?php
namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Database;
use App\Core\Session;
use App\Models\AccountDeletion;
use App\Models\LoginAttempt;
use App\Models\User;

/**
 * Libre-service pour un ex-titulaire de compte : redemander le rapport de suppression
 * (motif + page de données personnelles, voir AccountDeletion::deleteUser()) par e-mail,
 * sans avoir besoin de se connecter (impossible, le compte n'existe plus). Réutilise le motif
 * et l'export capturés au moment de la suppression — fonctionne même après une purge
 * définitive du contenu, puisque cet instantané en est indépendant.
 */
class DeletedAccountController extends BaseController
{
    public function showRequestForm(array $params): void
    {
        $error = Session::getFlash('error');
        $info = Session::getFlash('info');
        require BASE_PATH . '/templates/deleted_account/request.php';
    }

    public function submitRequest(array $params): void
    {
        if (!Csrf::verify()) {
            Session::flash('error', 'Session expirée, veuillez réessayer.');
            header('Location: ' . BASE_URL . '/compte-supprime');
            exit;
        }
        $email = trim($_POST['email'] ?? '');
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        // Message générique dans tous les cas (aucun compte supprimé pour cette adresse,
        // verrouillage, erreur d'envoi...) : révéler "cette adresse n'a jamais été supprimée"
        // permettrait d'apprendre qui a un compte FamilyBoard en testant des adresses en masse
        // — même précaution que AuthController::forgotPassword().
        $genericMessage = "Si un compte supprimé correspond à cette adresse, un e-mail vient de lui être envoyé.";

        if (!$email) {
            Session::flash('error', 'Veuillez indiquer votre adresse e-mail.');
            header('Location: ' . BASE_URL . '/compte-supprime');
            exit;
        }

        $acctKey = LoginAttempt::accountKey($email);
        if (LoginAttempt::isLocked('del_account', $ip) || LoginAttempt::isLocked('del_account', $acctKey)) {
            Session::flash('info', $genericMessage);
            header('Location: ' . BASE_URL . '/compte-supprime');
            exit;
        }
        LoginAttempt::record('del_account', $ip);
        LoginAttempt::record('del_account', $acctKey);

        // Le plus récent si plusieurs suppressions ont eu lieu pour la même adresse (compte
        // recréé puis supprimé à nouveau) — le rapport le plus utile pour qui le demande.
        $row = Database::fetch(
            'SELECT * FROM deleted_users WHERE email=? ORDER BY deleted_at DESC LIMIT 1',
            [User::normalizeEmail($email)]
        );
        if ($row) {
            AccountDeletion::resendDeletionReport($row);
        }

        Session::flash('info', $genericMessage);
        header('Location: ' . BASE_URL . '/compte-supprime');
        exit;
    }
}
