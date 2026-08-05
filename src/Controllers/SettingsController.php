<?php
namespace App\Controllers;

use App\Core\Session;
use App\Core\Totp;
use App\Models\AccountDeletion;
use App\Models\Custody;
use App\Models\CustodyActivityLog;
use App\Models\DataExport;
use App\Models\TwoFactorAuth;
use App\Models\User;
use App\Models\Family;
use App\Models\Notification;
use App\Models\EmailLog;
use App\Models\SitterLink;
use App\Models\KioskLink;

class SettingsController extends BaseController
{
    public function index(array $params): void
    {
        $this->requireAuth();
        $user = Session::user();
        $family = Family::findById($user['family_id']);
        $members = User::getByFamily($user['family_id']);
        $coparentChildren = \App\Models\Custody::getChildNamesByUserIds(array_column($members, 'id'));
        $emailLogs = ($user['role'] === 'admin') ? EmailLog::getByFamily($user['family_id'], 30) : [];
        $sitterLinks = SitterLink::getByFamily($user['family_id']);
        $kioskLinks = ($user['role'] === 'admin') ? KioskLink::getByFamily($user['family_id']) : [];
        $coparentsForNotify = ($user['role'] === 'admin') ? Custody::getCoparentUsersForFamily($user['family_id']) : [];
        $twoFactorMethod = TwoFactorAuth::getMethod($user['id']);
        $friendFamiliesAccepted = \App\Models\FamilyFriend::getAcceptedFor((int)$user['family_id']);
        $friendFamiliesIncoming = \App\Models\FamilyFriend::getPendingIncoming((int)$user['family_id']);
        $friendFamiliesOutgoing = \App\Models\FamilyFriend::getPendingOutgoing((int)$user['family_id']);
        require BASE_PATH . '/templates/settings/index.php';
    }

    /** Déconnecte tous les appareils : révoque les jetons "se souvenir de moi" et invalide
     *  toute session déjà ouverte (y compris celle-ci — l'appelant doit rediriger vers /logout). */
    public function logoutAllDevices(array $params): void
    {
        $this->requireAuth();
        $this->json(function () {
            $user = Session::user();
            \App\Core\Database::execute('UPDATE users SET force_logout_at = ? WHERE id = ?', [gmdate('Y-m-d H:i:s'), $user['id']]);
            \App\Models\AuthToken::deleteForUser((int)$user['id']);
            return ['success' => true];
        });
    }

    // ── Authentification à deux facteurs ────────────────────────────

    /** Démarre l'enrôlement TOTP : génère un secret temporaire (non persisté tant que le code
     *  n'est pas confirmé) et le place en session le temps de la vérification. */
    public function startTwoFactorTotp(array $params): void
    {
        $this->requireAuth();
        $this->json(function () {
            $user = Session::user();
            $secret = Totp::generateSecret();
            Session::set('pending_totp_secret', $secret);
            return [
                'success' => true,
                'secret'  => $secret,
                'uri'     => Totp::provisioningUri($secret, $user['email']),
            ];
        });
    }

    public function confirmTwoFactorTotp(array $params): void
    {
        $this->requireAuth();
        $this->json(function () {
            $user = Session::user();
            $secret = Session::get('pending_totp_secret');
            $code = trim($this->jsonInput()['code'] ?? '');
            if (!$secret || !TwoFactorAuth::verifyTotpCode((int)$user['id'], $secret, $code)) {
                return ['success' => false, 'error' => 'Code invalide.'];
            }
            TwoFactorAuth::enableTotp((int)$user['id'], $secret);
            Session::delete('pending_totp_secret');
            return ['success' => true];
        });
    }

    public function enableTwoFactorEmail(array $params): void
    {
        $this->requireAuth();
        $this->json(function () {
            $user = Session::user();
            TwoFactorAuth::enableEmail((int)$user['id']);
            return ['success' => true];
        });
    }

    /** Désactiver la 2FA exige le mot de passe courant : ne pas laisser une session détournée
     *  (mais sans le mot de passe) désactiver la protection. */
    public function disableTwoFactor(array $params): void
    {
        $this->requireAuth();
        $this->json(function () {
            $user = Session::user();
            $password = $this->jsonInput()['password'] ?? '';
            if (!User::verify($user['email'], $password)) {
                return ['success' => false, 'error' => 'Mot de passe incorrect.'];
            }
            TwoFactorAuth::disable((int)$user['id']);
            return ['success' => true];
        });
    }

    public function updateProfile(array $params): void
    {
        $this->requireAuth();
        $user = Session::user();
        $name = trim($_POST['name'] ?? '');
        $color = $this->safeColor($_POST['color'] ?? null);
        $avatar = $this->uploadImage('avatar');
        // Un envoi d'avatar rejeté (format non supporté, fichier trop lourd…) ne doit pas passer
        // pour une mise à jour réussie — sans quoi rien ne prévient l'utilisateur que sa photo
        // n'a en réalité pas changé.
        $avatarError = $this->lastUploadError;

        $data = ['name' => $name ?: $user['name'], 'color' => $color];
        if ($avatar) $data['avatar'] = $avatar;
        $birthday = trim($_POST['birthday'] ?? '');
        if ($birthday !== '') {
            $d = \DateTime::createFromFormat('Y-m-d', $birthday);
            $data['birthday'] = ($d && $d->format('Y-m-d') === $birthday && $d < new \DateTime()) ? $birthday : null;
        } else {
            $data['birthday'] = null;
        }
        User::update($user['id'], $data);

        $passwordError = null;
        $password = $_POST['password'] ?? '';
        if ($password) {
            // Le mot de passe actuel est exigé pour changer le mot de passe : sans ça, un
            // détournement de session (XSS, poste partagé laissé ouvert...) suffirait à
            // prendre le contrôle définitif du compte sans jamais connaître le mot de passe.
            $currentPassword = $_POST['current_password'] ?? '';
            if (!User::verify($user['email'], $currentPassword)) {
                $passwordError = 'Mot de passe actuel incorrect — le mot de passe n\'a pas été changé.';
            } elseif (strlen($password) < 8) {
                $passwordError = 'Le nouveau mot de passe doit faire au moins 8 caractères.';
            } else {
                User::updatePassword($user['id'], $password);
                // Invalide toutes les autres sessions/appareils ("se souvenir de moi" inclus) :
                // un attaquant qui avait un accès parallèle ne doit pas le conserver après que
                // la victime a changé son mot de passe. La session courante reste active en
                // avançant son propre login_at au-delà du nouveau force_logout_at.
                \App\Core\Database::execute('UPDATE users SET force_logout_at = ? WHERE id = ?', [gmdate('Y-m-d H:i:s'), $user['id']]);
                \App\Models\AuthToken::deleteForUser((int)$user['id']);
                Session::set('login_at', time());
            }
        }

        if ($avatarError) {
            Session::flash('error', 'Profil mis à jour, mais la photo n\'a pas pu être changée : ' . $avatarError);
        } elseif ($passwordError) {
            Session::flash('error', 'Profil mis à jour. ' . $passwordError);
        } else {
            Session::flash('success', 'Profil mis à jour.');
        }
        header('Location: ' . BASE_URL . '/settings');
        exit;
    }

    public function updateFamily(array $params): void
    {
        $this->requireAdmin();
        $user = Session::user();
        $name = trim($_POST['family_name'] ?? '');
        $tz   = $_POST['timezone'] ?? '';
        if (!$tz || !in_array($tz, \DateTimeZone::listIdentifiers())) $tz = null;
        if ($name) Family::update($user['family_id'], $name, [
            'timezone'             => $tz,
            'weather_city'         => trim($_POST['weather_city']         ?? ''),
            'school_zone'          => trim($_POST['school_zone']          ?? ''),
            'caldav_sync_interval' => trim($_POST['caldav_sync_interval'] ?? ''),
        ]);
        Session::flash('success', 'Famille mise à jour.');
        header('Location: ' . BASE_URL . '/settings');
        exit;
    }

    public function regenerateCode(array $params): void
    {
        $this->requireAdmin();
        $user = Session::user();
        $code = Family::regenerateCode($user['family_id']);
        Session::flash('success', 'Nouveau code : ' . $code);
        header('Location: ' . BASE_URL . '/settings');
        exit;
    }

    public function updateModules(array $params): void
    {
        $this->requireAdmin();
        $user    = Session::user();
        $all     = array_keys(\App\Models\Family::MODULES);
        $enabled = $_POST['modules'] ?? [];
        $disabled = array_values(array_diff($all, $enabled));
        Family::setDisabledModules($user['family_id'], $disabled);
        Session::flash('success', 'Modules mis à jour.');
        header('Location: ' . BASE_URL . '/settings');
        exit;
    }

    /** Barre de navigation rapide (mobile/PWA) — préférence personnelle, pas un réglage
     *  d'administrateur familial : n'importe quel membre/admin choisit la sienne. */
    public function updateQuickNav(array $params): void
    {
        $this->requireAuth();
        $user = Session::user();
        $valid = array_keys(\App\Models\Family::MODULES);
        $selected = array_slice((array)($_POST['quick_nav'] ?? []), 0, 3);
        $slugs = array_values(array_intersect($selected, $valid));
        User::updateQuickNav((int)$user['id'], $slugs ?: null);
        Session::flash('success', 'Barre rapide mise à jour.');
        header('Location: ' . BASE_URL . '/settings');
        exit;
    }

    public function removeMember(array $params): void
    {
        $this->requireAdmin();
        $user = Session::user();
        $id = (int)$params['id'];
        if ($id !== $user['id']) {
            $member = User::findById($id);
            if ($member && $member['family_id'] === $user['family_id']) {
                // Contenu préservé (jamais supprimé en cascade) et tracé dans deleted_users,
                // pour tout membre — voir AccountDeletion::deleteUser().
                AccountDeletion::deleteUser($id, (int)$member['family_id'], 'family_admin');
            }
        }
        header('Location: ' . BASE_URL . '/settings');
        exit;
    }

    public function exportData(array $params): void
    {
        $this->requireAuth();
        $user = Session::user();
        $wholeFamily = ($_GET['scope'] ?? 'mine') === 'family';
        if ($wholeFamily && $user['role'] !== 'admin') {
            http_response_code(403);
            echo 'Réservé à l\'administrateur de la famille.';
            return;
        }

        $zipPath = DataExport::build((int)$user['id'], (int)$user['family_id'], $wholeFamily);
        $filename = ($wholeFamily ? 'famille' : 'mes-donnees') . '-' . date('Y-m-d') . '.zip';

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($zipPath));
        readfile($zipPath);
        @unlink($zipPath);
        exit;
    }

    /** Nombre de membres de la famille autres que $excludeId. */
    private function otherMembersCount(int $familyId, int $excludeId): int
    {
        $row = \App\Core\Database::fetch(
            'SELECT COUNT(*) as n FROM users WHERE family_id=? AND id!=?',
            [$familyId, $excludeId]
        );
        return (int)($row['n'] ?? 0);
    }

    public function deleteAccount(array $params): void
    {
        $this->requireAuth();
        $this->json(function () {
            $user = Session::user();
            $familyId = (int)$user['family_id'];
            $data = $this->jsonInput();

            if ($user['role'] === 'admin' && $this->otherMembersCount($familyId, (int)$user['id']) > 0) {
                $action = $data['action'] ?? '';
                if ($action === 'transfer') {
                    $targetId = (int)($data['transfer_to_user_id'] ?? 0);
                    $target = User::findById($targetId);
                    if (!$target || $target['family_id'] !== $familyId || $targetId === (int)$user['id']) {
                        return ['success' => false, 'error' => 'Membre invalide.'];
                    }
                    AccountDeletion::transferAdminAndDelete((int)$user['id'], $targetId, $familyId);
                } elseif ($action === 'delete_family') {
                    AccountDeletion::deleteFamily($familyId);
                } else {
                    return ['success' => false, 'error' => 'Choisissez de transférer le rôle admin ou de supprimer la famille.'];
                }
            } else {
                // Membre non-admin, co-parent, ou admin seul dans sa famille : suppression directe
                // (dans ce dernier cas, la famille n'a plus personne d'autre à qui la confier).
                if ($user['role'] === 'admin') {
                    AccountDeletion::deleteFamily($familyId);
                } elseif ($user['role'] === 'coparent') {
                    AccountDeletion::deleteCoparent((int)$user['id']);
                } else {
                    AccountDeletion::deleteUser((int)$user['id'], $familyId);
                }
            }

            \App\Core\RememberMe::clear();
            Session::destroy();
            return ['success' => true, 'redirect' => BASE_URL . '/login'];
        });
    }

    public function getNotifications(array $params): void
    {
        $this->requireAuth();
        $this->json(function () {
            $user = Session::user();
            $notifications = Notification::getByUser($user['id'], 20);
            $unread = Notification::getUnreadCount($user['id']);
            return ['notifications' => $notifications, 'unread' => $unread];
        });
    }

    public function markNotificationRead(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            Notification::markRead((int)$params['id'], $user['id']);
            return ['success' => true];
        });
    }

    public function markAllNotificationsRead(array $params): void
    {
        $this->requireAuth();
        $this->json(function () {
            $user = Session::user();
            Notification::markAllRead($user['id']);
            return ['success' => true];
        });
    }

    /**
     * Un admin de famille envoie une notification à ses membres. Si "inclure le co-parent"
     * est coché, les comptes ayant un accès garde partagée à cette famille (custody_access)
     * sont notifiés en plus, et l'envoi est journalisé dans le journal d'activité de la garde
     * partagée (immuable) pour chaque planning concerné.
     */
    public function sendNotification(array $params): void
    {
        $this->requireAdmin();
        $this->json(function () {
            $user = Session::user();
            $data = $this->jsonInput();
            $title = trim($data['title'] ?? '');
            $message = trim($data['message'] ?? '');
            $includeCoparent = !empty($data['include_coparent']);

            if (!$title || !$message) {
                return ['success' => false, 'error' => 'Titre et message requis.'];
            }
            if (mb_strlen($title) > 150 || mb_strlen($message) > 2000) {
                return ['success' => false, 'error' => 'Texte trop long.'];
            }

            $familyId = (int)$user['family_id'];

            $recipients = [];
            foreach (User::getByFamily($familyId) as $m) {
                if ((int)$m['id'] === (int)$user['id']) continue;
                if ($m['role'] === 'coparent' && !$includeCoparent) continue;
                $recipients[(int)$m['id']] = true;
            }

            $coparents = $includeCoparent ? Custody::getCoparentUsersForFamily($familyId) : [];
            foreach ($coparents as $cp) {
                if ((int)$cp['id'] !== (int)$user['id']) $recipients[(int)$cp['id']] = true;
            }

            foreach (array_keys($recipients) as $uid) {
                Notification::create($uid, 'family_admin', $title, $message, BASE_URL . '/');
            }

            if ($includeCoparent) {
                $scheduleIds = [];
                foreach ($coparents as $cp) {
                    foreach ($cp['schedule_ids'] as $sid) $scheduleIds[$sid] = true;
                }
                foreach (array_keys($scheduleIds) as $sid) {
                    CustodyActivityLog::record($sid, $user['id'], 'notification_sent', mb_strimwidth("$title — $message", 0, 150, '…'));
                }
            }

            return ['success' => true, 'count' => count($recipients)];
        });
    }

}
