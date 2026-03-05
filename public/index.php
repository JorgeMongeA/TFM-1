<?php

declare(strict_types=1);

session_start();

$configPath = dirname(__DIR__) . '/config/config.php';
if (!file_exists($configPath)) {
    $configPath = dirname(__DIR__) . '/config/config.example.php';
}
$config = require $configPath;

require_once dirname(__DIR__) . '/app/Core/Database.php';
require_once dirname(__DIR__) . '/app/Core/Router.php';
require_once dirname(__DIR__) . '/app/Models/User.php';
require_once dirname(__DIR__) . '/app/Controllers/AuthController.php';
require_once dirname(__DIR__) . '/app/Middleware/AuthMiddleware.php';

$router = new Router();

$router->add('GET', '/', function (): void {
    header('Location: /login');
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
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

$router->dispatch($uri, $method);
