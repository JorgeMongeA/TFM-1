<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/conexion.php';

$configFile = dirname(__DIR__) . '/config/config.php';
$config = is_file($configFile) ? require $configFile : [];

if (!defined('BASE_URL')) {
    $base = rtrim((string) ($config['base_url'] ?? '/CON/public'), '/');
    define('BASE_URL', $base !== '' ? $base : '/CON/public');
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
    $_SESSION['usuario_id'] = (int) $user['id'];
    $_SESSION['usuario'] = (string) $user['username'];
    $_SESSION['rol_id'] = (int) ($user['rol_id'] ?? 0);

    return true;
}

function require_login(): void
{
    if (empty($_SESSION['usuario'])) {
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
