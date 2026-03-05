<?php

declare(strict_types=1);

class Router
{
    private array $routes = [];

    public function add(string $method, string $route, $controller): void
    {
        $normalizedMethod = strtoupper($method);
        $normalizedRoute = $this->normalizeRoute($route);
        $this->routes[$normalizedMethod][$normalizedRoute] = $controller;
    }

    public function dispatch(string $uri, string $method): void
    {
        $normalizedMethod = strtoupper($method);
        $normalizedUri = $this->normalizeRoute($uri);
        $controller = $this->routes[$normalizedMethod][$normalizedUri] ?? null;

        if ($controller === null) {
            http_response_code(404);
            echo '404 - Ruta no encontrada';
            return;
        }

        if (is_callable($controller)) {
            call_user_func($controller);
            return;
        }

        if (is_string($controller) && str_contains($controller, '@')) {
            [$controllerName, $action] = explode('@', $controller, 2);

            if (!class_exists($controllerName)) {
                $controllerPath = dirname(__DIR__) . '/Controllers/' . $controllerName . '.php';
                if (file_exists($controllerPath)) {
                    require_once $controllerPath;
                }
            }

            if (!class_exists($controllerName)) {
                throw new RuntimeException('Controlador no encontrado: ' . $controllerName);
            }

            $instance = new $controllerName();
            if (!method_exists($instance, $action)) {
                throw new RuntimeException('Método no encontrado: ' . $controller);
            }

            $instance->$action();
            return;
        }

        throw new RuntimeException('Ruta inválida para controlador: ' . $normalizedUri);
    }

    private function normalizeRoute(string $route): string
    {
        $clean = parse_url($route, PHP_URL_PATH) ?? '/';
        $normalized = '/' . trim($clean, '/');
        return $normalized === '//' ? '/' : $normalized;
    }
}
