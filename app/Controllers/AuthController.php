<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Models/User.php';

class AuthController
{
    public function showLogin(): void
    {
        if (isset($_SESSION['user'])) {
            header('Location: /dashboard');
            exit;
        }

        require dirname(__DIR__) . '/Views/login.php';
    }

    public function login(): void
    {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($username === '' || $password === '') {
            header('Location: /login?error=1');
            exit;
        }

        $userModel = new User();
        $user = $userModel->findByUsername($username);

        if ($user !== null && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user'] = [
                'id' => $user['id'],
                'username' => $user['username'],
            ];
            $_SESSION['rol'] = $user['rol'];

            header('Location: /dashboard');
            exit;
        }

        header('Location: /login?error=1');
        exit;
    }

    public function logout(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();
        header('Location: /login');
        exit;
    }
}
