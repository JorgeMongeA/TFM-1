<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/conexion.php';

$config = require dirname(__DIR__) . '/config/config.php';
if (!defined('BASE_URL')) {
    $base = rtrim((string) ($config['base_url'] ?? '/CON'), '/');
    define('BASE_URL', $base !== '' ? $base : '/CON');
}

function login(string $username, string $password): bool
{
    $username = trim($username);
    if ($username === '' || $password === '') {
        return false;
    }

    $pdo = conectar();
    $stmt = $pdo->prepare('SELECT id, username, password, rol_id FROM usuarios WHERE username = :username LIMIT 1');
    $stmt->execute(['username' => $username]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        return false;
    }

    session_regenerate_id(true);
    $_SESSION['user'] = $user['username'];
    $_SESSION['rol_id'] = (int) $user['rol_id'];
    $_SESSION['user_id'] = (int) $user['id'];

    return true;
}

function require_login(): void
{
    if (empty($_SESSION['user'])) {
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
}

function logout(): void
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
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}
