<?php

declare(strict_types=1);

require_once __DIR__ . '/conexion.php';

$configFile = dirname(__DIR__) . '/config/config.php';
$config = is_file($configFile) ? require $configFile : [];

if (!defined('BASE_URL')) {
    $base = rtrim((string) ($config['base_url'] ?? '/CON/public'), '/');
    define('BASE_URL', $base !== '' ? $base : '/CON/public');
}

function iniciar_sesion(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

function login(string $username, string $password): bool
{
    iniciar_sesion();

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
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['username'] = (string) $user['username'];
    $_SESSION['rol_id'] = (int) ($user['rol_id'] ?? 0);

    return true;
}

function require_login(): void
{
    iniciar_sesion();

    if (!isset($_SESSION['user_id'])) {
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
}

function logout(): void
{
    iniciar_sesion();
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
