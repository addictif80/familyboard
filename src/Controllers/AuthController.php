<?php
namespace App\Controllers;

use App\Core\EmailLayout;
use App\Core\Mail;
use App\Core\RememberMe;
use App\Core\Session;
use App\Models\Family;
use App\Models\LoginAttempt;
use App\Models\TwoFactorAuth;
use App\Models\User;

class AuthController
{
    public function showLogin(array $params): void
    {
        if (Session::isLoggedIn()) {
            header('Location: ' . BASE_URL . '/');
            exit;
        }
        Session::delete('pending_2fa_user_id');
        $error = Session::getFlash('error');
        require BASE_PATH . '/templates/auth/login.php';
    }

    public function login(array $params): void
    {
        if (!\App\Core\Csrf::verify()) {
            Session::flash('error', 'Session expirée, veuillez réessayer.');
            header('Location: ' . BASE_URL . '/login');
            exit;
        }
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        if (!$email || !$password) {
            Session::flash('error', 'Veuillez remplir tous les champs.');
            header('Location: ' . BASE_URL . '/login');
            exit;
        }

        $acctKey = LoginAttempt::accountKey($email);
        if (LoginAttempt::isLocked('user', $ip) || LoginAttempt::isLocked('user', $acctKey)) {
            Session::flash('error', 'Trop de tentatives. Réessayez dans ' . LoginAttempt::minutesUntilUnlock() . ' minutes.');
            header('Location: ' . BASE_URL . '/login');
            exit;
        }

        $user = User::verify($email, $password);
        if (!$user) {
            LoginAttempt::record('user', $ip);
            LoginAttempt::record('user', $acctKey);
            Session::flash('error', 'Email ou mot de passe incorrect.');
            header('Location: ' . BASE_URL . '/login');
            exit;
        }
        LoginAttempt::clear('user', $ip);
        LoginAttempt::clear('user', $acctKey);

        // Check if account is blocked
        if (!empty($user['blocked_at'])) {
            $reason = $user['blocked_reason'] ?? '';
            require BASE_PATH . '/templates/blocked.php';
            exit;
        }

        $this->completeLogin($user);
    }

    /** Termine la connexion — ou, si la 2FA est activée, met la connexion en attente d'un code. */
    private function completeLogin(array $user): void
    {
        $method = TwoFactorAuth::getMethod((int)$user['id']);
        if ($method === null) {
            Session::login($user);
            RememberMe::issue($user['id']);
            header('Location: ' . BASE_URL . '/');
            exit;
        }

        Session::set('pending_2fa_user_id', $user['id']);
        if ($method === 'email') {
            $this->sendTwoFactorEmailCode($user);
        }
        header('Location: ' . BASE_URL . '/login/2fa');
        exit;
    }

    private function sendTwoFactorEmailCode(array $user): void
    {
        $code = TwoFactorAuth::issueEmailCode((int)$user['id']);
        $html = EmailLayout::render(
            'Votre code de vérification',
            '<p>Voici votre code de connexion FamilyBoard :</p>'
            . '<p style="font-size:28px;font-weight:700;letter-spacing:4px">' . htmlspecialchars($code) . '</p>'
            . '<p>Ce code expire dans 10 minutes. Si vous n\'êtes pas à l\'origine de cette tentative de connexion, ignorez cet email.</p>'
        );
        Mail::send((int)$user['family_id'], $user['email'], $user['name'], 'Votre code de vérification FamilyBoard', $html, 'otp_2fa');
    }

    public function showTwoFactor(array $params): void
    {
        $userId = Session::get('pending_2fa_user_id');
        if (!$userId) {
            header('Location: ' . BASE_URL . '/login');
            exit;
        }
        $method = TwoFactorAuth::getMethod((int)$userId);
        $error = Session::getFlash('error');
        $info = Session::getFlash('info');
        require BASE_PATH . '/templates/auth/two_factor.php';
    }

    public function verifyTwoFactor(array $params): void
    {
        if (!\App\Core\Csrf::verify()) {
            Session::flash('error', 'Session expirée, veuillez réessayer.');
            header('Location: ' . BASE_URL . '/login/2fa');
            exit;
        }
        $userId = (int)(Session::get('pending_2fa_user_id') ?? 0);
        if (!$userId) {
            header('Location: ' . BASE_URL . '/login');
            exit;
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $acctKey = 'acct:' . $userId;
        if (LoginAttempt::isLocked('2fa', $ip) || LoginAttempt::isLocked('2fa', $acctKey)) {
            Session::flash('error', 'Trop de tentatives. Réessayez dans ' . LoginAttempt::minutesUntilUnlock() . ' minutes.');
            header('Location: ' . BASE_URL . '/login/2fa');
            exit;
        }

        $user = User::findById($userId);
        $method = $user ? TwoFactorAuth::getMethod($userId) : null;
        $code = trim($_POST['code'] ?? '');

        $ok = false;
        if ($user && $method === 'totp') {
            $secret = TwoFactorAuth::getTotpSecret($userId);
            $ok = $secret !== null && TwoFactorAuth::verifyTotpCode($userId, $secret, $code);
        } elseif ($user && $method === 'email') {
            $ok = TwoFactorAuth::verifyEmailCode($userId, $code);
        }

        if (!$ok) {
            LoginAttempt::record('2fa', $ip);
            LoginAttempt::record('2fa', $acctKey);
            Session::flash('error', 'Code invalide ou expiré.');
            header('Location: ' . BASE_URL . '/login/2fa');
            exit;
        }
        LoginAttempt::clear('2fa', $ip);
        LoginAttempt::clear('2fa', $acctKey);
        Session::delete('pending_2fa_user_id');
        Session::login($user);
        RememberMe::issue($user['id']);
        header('Location: ' . BASE_URL . '/');
        exit;
    }

    public function resendTwoFactorEmail(array $params): void
    {
        if (!\App\Core\Csrf::verify()) {
            header('Location: ' . BASE_URL . '/login/2fa');
            exit;
        }
        $userId = (int)(Session::get('pending_2fa_user_id') ?? 0);
        $user = $userId ? User::findById($userId) : null;
        if ($user && TwoFactorAuth::getMethod($userId) === 'email') {
            $this->sendTwoFactorEmailCode($user);
            Session::flash('info', 'Un nouveau code a été envoyé.');
        }
        header('Location: ' . BASE_URL . '/login/2fa');
        exit;
    }

    public function showRegister(array $params): void
    {
        if (Session::isLoggedIn()) {
            header('Location: ' . BASE_URL . '/');
            exit;
        }
        $error = Session::getFlash('error');
        $inviteCode = $_GET['invite'] ?? '';
        require BASE_PATH . '/templates/auth/register.php';
    }

    public function register(array $params): void
    {
        if (!\App\Core\Csrf::verify()) {
            Session::flash('error', 'Session expirée, veuillez réessayer.');
            header('Location: ' . BASE_URL . '/register');
            exit;
        }
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $familyName = trim($_POST['family_name'] ?? '');
        $inviteCode = trim($_POST['invite_code'] ?? '');

        if (!$name || !$email || !$password) {
            Session::flash('error', 'Veuillez remplir tous les champs obligatoires.');
            header('Location: ' . BASE_URL . '/register');
            exit;
        }

        if (empty($_POST['accept_terms'])) {
            Session::flash('error', 'Vous devez accepter les CGU et la politique de confidentialité pour créer un compte.');
            header('Location: ' . BASE_URL . '/register');
            exit;
        }

        if (strlen($password) < 8) {
            Session::flash('error', 'Le mot de passe doit faire au moins 8 caractères.');
            header('Location: ' . BASE_URL . '/register');
            exit;
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        if (LoginAttempt::isLocked('register', $ip)) {
            Session::flash('error', 'Trop de tentatives. Réessayez dans ' . LoginAttempt::minutesUntilUnlock() . ' minutes.');
            header('Location: ' . BASE_URL . '/register');
            exit;
        }

        $existing = User::findByEmail($email);
        if ($existing) {
            // Message volontairement neutre : révéler "cet email est déjà utilisé" permettrait
            // à quiconque de découvrir qui a un compte FamilyBoard en testant des adresses en
            // masse (risque concret ici vu l'usage co-parent en contexte de séparation). Le
            // titulaire du compte existant est prévenu par e-mail de la tentative à sa place.
            LoginAttempt::record('register', $ip);
            self::notifyExistingAccountOfRegistrationAttempt($existing);
            Session::flash('error', 'Impossible de créer ce compte. Si vous avez déjà un compte avec cette adresse, connectez-vous plutôt.');
            header('Location: ' . BASE_URL . '/register');
            exit;
        }

        // Join existing family or create new one
        if ($inviteCode) {
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            if (LoginAttempt::isLocked('invite', $ip)) {
                Session::flash('error', 'Trop de tentatives. Réessayez dans ' . LoginAttempt::minutesUntilUnlock() . ' minutes.');
                header('Location: ' . BASE_URL . '/register');
                exit;
            }
            $family = Family::findByInviteCode(strtoupper($inviteCode));
            if (!$family) {
                LoginAttempt::record('invite', $ip);
                Session::flash('error', 'Code d\'invitation invalide.');
                header('Location: ' . BASE_URL . '/register');
                exit;
            }
            $familyId = $family['id'];
            $role = 'member';
        } else {
            if (!$familyName) {
                Session::flash('error', 'Veuillez donner un nom à votre famille.');
                header('Location: ' . BASE_URL . '/register');
                exit;
            }
            $familyId = Family::create($familyName);
            $role = 'admin';
        }

        $colors = ['#4A90D9', '#E74C3C', '#2ECC71', '#F39C12', '#9B59B6', '#1ABC9C', '#E67E22', '#3498DB'];
        $color = $colors[array_rand($colors)];

        $userId = User::create($familyId, $name, $email, $password, $role, $color);
        $user = User::findById($userId);
        $this->completeLogin($user);
    }

    /** Prévient le titulaire d'un compte existant qu'une inscription a été tentée avec son
     *  adresse — best-effort, ne doit jamais faire échouer/ralentir la réponse au formulaire. */
    private static function notifyExistingAccountOfRegistrationAttempt(array $existing): void
    {
        try {
            $html = EmailLayout::render(
                'Tentative de création de compte',
                '<p>Quelqu\'un a tenté de créer un compte FamilyBoard avec votre adresse e-mail.</p>'
                . '<p>Si c\'est vous, connectez-vous simplement avec votre compte existant. '
                . 'Si ce n\'est pas vous, aucune action n\'est requise : votre compte n\'a pas été modifié.</p>'
            );
            Mail::send((int)$existing['family_id'], $existing['email'], $existing['name'], 'Tentative de création de compte — FamilyBoard', $html, 'security');
        } catch (\Throwable $e) {
            error_log('notifyExistingAccountOfRegistrationAttempt failed: ' . $e->getMessage());
        }
    }

    public function logout(array $params): void
    {
        // If a system admin is mid-impersonation and logs out via the normal button
        // (instead of the "Revenir à l'admin" banner), still close the audit entry
        // rather than leaving it stuck "en cours" forever.
        if (!empty($_SESSION['impersonation']['log_id'])) {
            \App\Models\ImpersonationLog::end((int)$_SESSION['impersonation']['log_id']);
        }
        RememberMe::clear();
        Session::destroy();
        header('Location: ' . BASE_URL . '/login');
        exit;
    }
}
