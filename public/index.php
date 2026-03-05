<?php

declare(strict_types=1);

session_start();

$configPath = dirname(__DIR__) . '/config/config.php';
$config = require $configPath;
$BASE = rtrim((string) ($config['base_url'] ?? ''), '/');

require_once dirname(__DIR__) . '/app/Core/Database.php';
require_once dirname(__DIR__) . '/app/Core/Router.php';
require_once dirname(__DIR__) . '/app/Core/helpers.php';
require_once dirname(__DIR__) . '/app/Models/User.php';
require_once dirname(__DIR__) . '/app/Controllers/AuthController.php';
require_once dirname(__DIR__) . '/app/Middleware/AuthMiddleware.php';

$router = new Router();

$router->add('GET', '/', function () use ($BASE): void {
    header('Location: ' . $BASE . '/login');
    exit;
});

$router->add('GET', '/login', 'AuthController@showLogin');
$router->add('POST', '/login', 'AuthController@login');
$router->add('GET', '/logout', 'AuthController@logout');

$router->add('GET', '/dashboard', function (): void {
    AuthMiddleware::check();
    require dirname(__DIR__) . '/app/Views/dashboard.php';
});

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
if ($BASE !== '' && ($uri === $BASE || str_starts_with($uri, $BASE . '/'))) {
    $uri = substr($uri, strlen($BASE));
}
if ($uri === '') {
    $uri = '/';
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

$router->dispatch($uri, $method);
