<?php

declare(strict_types=1);

function base_url(string $path = ''): string
{
    static $base = null;

    if ($base === null) {
        $config = require dirname(__DIR__, 2) . '/config/config.php';
        $base = rtrim((string) ($config['base_url'] ?? ''), '/');
    }

    $path = trim($path, '/');
    if ($path === '') {
        return $base !== '' ? $base : '/';
    }

    return ($base !== '' ? $base : '') . '/' . $path;
}
