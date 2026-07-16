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
        $invitation = Invitation::getByToken($token);
        if (!$invitation) {
            header('Location: ' . BASE_URL . '/login');
            exit;
        }

        $name     = trim($_POST['name'] ?? '');
        $password = $_POST['password'] ?? '';

        if (!$name || strlen($password) < 6) {
            Session::flash('error', 'Nom requis et mot de passe minimum 6 caractères.');
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
                // Move to this family
                \App\Core\Database::execute(
                    'UPDATE users SET family_id=? WHERE id=?',
                    [$invitation['family_id'], $existing['id']]
                );
            }
            Invitation::markUsed($token);
            Session::login($existing);
            header('Location: ' . BASE_URL . '/');
            exit;
        }

        // Create new account
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
        }
        Invitation::markUsed($token);

        $user = User::findById($userId);
        Session::login($user);
        Session::flash('success', $isCoparent
            ? 'Accès restreint activé pour le suivi de garde partagée.'
            : 'Bienvenue dans la famille ' . $invitation['family_name'] . ' !');
        header('Location: ' . BASE_URL . '/');
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
