<?php
namespace App\Controllers;

use App\Core\Session;
use App\Models\Family;
use App\Models\User;

class AuthController
{
    public function showLogin(array $params): void
    {
        if (Session::isLoggedIn()) {
            header('Location: ' . BASE_URL . '/');
            exit;
        }
        $error = Session::getFlash('error');
        require BASE_PATH . '/templates/auth/login.php';
    }

    public function login(array $params): void
    {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (!$email || !$password) {
            Session::flash('error', 'Veuillez remplir tous les champs.');
            header('Location: ' . BASE_URL . '/login');
            exit;
        }

        $user = User::verify($email, $password);
        if (!$user) {
            Session::flash('error', 'Email ou mot de passe incorrect.');
            header('Location: ' . BASE_URL . '/login');
            exit;
        }

        // Check if account is blocked
        if (!empty($user['blocked_at'])) {
            $reason = $user['blocked_reason'] ?? '';
            require BASE_PATH . '/templates/blocked.php';
            exit;
        }

        Session::login($user);
        header('Location: ' . BASE_URL . '/');
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

        if (strlen($password) < 6) {
            Session::flash('error', 'Le mot de passe doit faire au moins 6 caractères.');
            header('Location: ' . BASE_URL . '/register');
            exit;
        }

        if (User::findByEmail($email)) {
            Session::flash('error', 'Cet email est déjà utilisé.');
            header('Location: ' . BASE_URL . '/register');
            exit;
        }

        // Join existing family or create new one
        if ($inviteCode) {
            $family = Family::findByInviteCode(strtoupper($inviteCode));
            if (!$family) {
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
        Session::login($user);

        header('Location: ' . BASE_URL . '/');
        exit;
    }

    public function logout(array $params): void
    {
        Session::destroy();
        header('Location: ' . BASE_URL . '/login');
        exit;
    }
}
