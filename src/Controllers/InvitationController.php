<?php
namespace App\Controllers;

use App\Core\Session;
use App\Core\Mail;
use App\Core\Database;
use App\Models\Invitation;
use App\Models\User;
use App\Models\Family;
use App\Models\EmailContent;
use App\Models\CustodyActivityLog;
use App\Core\EmailLayout;

class InvitationController extends BaseController
{
    public function show(array $params): void
    {
        $token = $params['token'] ?? '';
        $invitation = Invitation::getByToken($token);
        if (!$invitation) {
            http_response_code(404);
            $error = 'Ce lien d\'invitation est invalide ou expiré.';
            require BASE_PATH . '/templates/invite/accept.php';
            return;
        }
        $invitedChildren = [];
        if (($invitation['invite_role'] ?? 'member') === 'coparent' && !empty($invitation['custody_schedule_ids'])) {
            foreach (explode(',', $invitation['custody_schedule_ids']) as $sid) {
                $schedule = \App\Models\Custody::getScheduleById((int)$sid);
                if ($schedule) $invitedChildren[] = $schedule['child_name'];
            }
        }
        require BASE_PATH . '/templates/invite/accept.php';
    }

    public function accept(array $params): void
    {
        $token = $params['token'] ?? '';
        if (!\App\Core\Csrf::verify()) {
            Session::flash('error', 'Session expirée, veuillez réessayer.');
            header('Location: ' . BASE_URL . '/invite/' . $token);
            exit;
        }
        $invitation = Invitation::getByToken($token);
        if (!$invitation) {
            header('Location: ' . BASE_URL . '/login');
            exit;
        }

        $name     = trim($_POST['name'] ?? '');
        $password = $_POST['password'] ?? '';

        if (!$name || !$password) {
            Session::flash('error', 'Nom et mot de passe requis.');
            header('Location: ' . BASE_URL . '/invite/' . $token);
            exit;
        }

        if (empty($_POST['accept_terms'])) {
            Session::flash('error', 'Vous devez accepter les CGU et la politique de confidentialité.');
            header('Location: ' . BASE_URL . '/invite/' . $token);
            exit;
        }

        $isCoparent  = ($invitation['invite_role'] ?? 'member') === 'coparent';
        $scheduleIds = ($isCoparent && !empty($invitation['custody_schedule_ids']))
            ? array_map('intval', explode(',', $invitation['custody_schedule_ids']))
            : [];

        // Check if email already has an account
        $existing = User::findByEmail($invitation['email']);
        if ($existing) {
            // Le lien d'invitation ne prouve que la possession de la boîte mail, pas du mot de
            // passe du compte existant — sans cette vérification, quiconque ouvre ce lien
            // pourrait se connecter sur ce compte et déclencher la migration de famille
            // ci-dessous sans jamais prouver qu'il en est le titulaire.
            if (!password_verify($password, $existing['password'])) {
                Session::flash('error', 'Un compte existe déjà avec cet email. Mot de passe incorrect.');
                header('Location: ' . BASE_URL . '/invite/' . $token);
                exit;
            }

            if ($isCoparent) {
                // Pont inter-familles : on ne touche pas à la famille/au rôle
                // existants de ce compte, on ajoute seulement l'accès aux
                // plannings de garde de l'invitation.
                foreach ($scheduleIds as $sid) {
                    \App\Models\Custody::grantAccess((int)$existing['id'], $sid);
                }
                // Cette connexion est la première fois que ce compte accède à CES
                // plannings-ci — active le journal d'activité partagé même si le
                // compte existait déjà (rattaché à une autre famille).
                CustodyActivityLog::activate((int)$existing['id'], $scheduleIds);
            } else {
                // Rejoint la famille en tant que membre à part entière : le rôle est
                // explicitement remis à "member", sinon un administrateur de son ancienne
                // famille garderait ce rôle et deviendrait automatiquement administrateur de
                // la nouvelle famille qui l'invite.
                $previousFamilyId = (int)$existing['family_id'];
                \App\Core\Database::execute(
                    "UPDATE users SET family_id=?, role='member' WHERE id=?",
                    [$invitation['family_id'], $existing['id']]
                );
                // Recharger le compte : $existing contient encore l'ancien family_id/role, or
                // Session::login() les copie tels quels dans la session.
                $existing = User::findById((int)$existing['id']);
                // L'alias mail de l'ancienne famille perd ce membre, celui de la nouvelle le
                // gagne — les deux doivent être recalculés.
                \App\Core\Mailcow::syncFamily($previousFamilyId);
                \App\Core\Mailcow::syncFamily((int)$invitation['family_id']);
            }
            Invitation::markUsed($token);
            Session::login($existing);
            header('Location: ' . BASE_URL . '/');
            exit;
        }

        // Create new account
        if (strlen($password) < 8) {
            Session::flash('error', 'Le mot de passe doit faire au moins 8 caractères.');
            header('Location: ' . BASE_URL . '/invite/' . $token);
            exit;
        }
        $colors = ['#4A90D9', '#E74C3C', '#27AE60', '#F39C12', '#8E44AD', '#16A085'];
        $color = $colors[array_rand($colors)];
        $role = $isCoparent ? 'coparent' : 'member';
        $userId = User::create($invitation['family_id'], $name, $invitation['email'], $password, $role, $color);
        if ($isCoparent) {
            foreach ($scheduleIds as $sid) {
                \App\Models\Custody::grantAccess($userId, $sid);
            }
            // Premier login (le compte vient d'être créé) : active le journal d'activité.
            CustodyActivityLog::activate($userId, $scheduleIds);
        } else {
            \App\Core\Mailcow::syncFamily((int)$invitation['family_id']);
        }
        Invitation::markUsed($token);

        $user = User::findById($userId);
        Session::login($user);
        Session::flash('success', $isCoparent
            ? 'Accès restreint activé pour le suivi de garde partagée.'
            : 'Bienvenue dans la famille ' . $invitation['family_name'] . ' !');
        // Nouveau compte co-parent : présentation de ce qu'il peut faire avec son accès
        // restreint avant de le laisser sur son espace — pas un configurateur, juste un tour.
        header('Location: ' . BASE_URL . ($isCoparent ? '/coparent/welcome' : '/'));
        exit;
    }

    public function sendInvite(array $params): void
    {
        $this->requireAdmin();
        $this->json(function () {
            $user = Session::user();
            $data = $this->jsonInput();
            $email = trim($data['email'] ?? '');

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return ['success' => false, 'error' => 'Adresse email invalide.'];
            }

            // Check not already a member
            $existing = User::findByEmail($email);
            if ($existing && $existing['family_id'] === $user['family_id']) {
                return ['success' => false, 'error' => 'Cette personne est déjà membre de la famille.'];
            }

            $token = Invitation::create($user['family_id'], $user['id'], $email);
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $inviteUrl = $scheme . '://' . $_SERVER['HTTP_HOST'] . BASE_URL . '/invite/' . $token;

            $family = Family::findById($user['family_id']);
            $rendered = EmailContent::render('invitation', [
                'family_name' => $family['name'],
                'sender_name' => $user['name'],
                'user_name'   => $email,
            ]);
            $html = EmailLayout::render($rendered['subject'], $rendered['message_html'], [
                'label' => 'Rejoindre la famille',
                'url'   => $inviteUrl,
            ]);

            $ok = Mail::send($user['family_id'], $email, $email, $rendered['subject'], $html, 'invitation', $user['id']);
            if ($ok) {
                return ['success' => true];
            }
            return ['success' => false, 'error' => 'Erreur d\'envoi. Vérifiez la configuration SMTP.'];
        });
    }
}
