<?php

declare(strict_types=1);

function cargarConfiguracion(): array
{
    static $config = null;

    if (is_array($config)) {
        return $config;
    }

    $configFile = dirname(__DIR__) . '/config/config.php';
    if (!is_file($configFile)) {
        throw new RuntimeException('No existe config/config.php');
    }

    $config = require $configFile;

    return is_array($config) ? $config : [];
}

function obtenerBaseUrl(): string
{
    $config = cargarConfiguracion();
    $base = rtrim((string) ($config['base_url'] ?? '/CON/public'), '/');

    return $base !== '' ? $base : '/CON/public';
}

if (!defined('BASE_URL')) {
    define('BASE_URL', obtenerBaseUrl());
}

function conectar(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $config = cargarConfiguracion();

    foreach (['db_host', 'db_name', 'db_user', 'db_pass'] as $key) {
        if (!array_key_exists($key, $config) || trim((string) $config[$key]) === '') {
            throw new RuntimeException("Falta la clave obligatoria '{$key}' en config/config.php");
        }
    }

    $host = (string) $config['db_host'];
    $dbname = (string) $config['db_name'];
    $user = (string) $config['db_user'];
    $pass = (string) $config['db_pass'];
    $charset = (string) ($config['db_charset'] ?? 'utf8mb4');

    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', $host, $dbname, $charset);

    try {
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } catch (PDOException $e) {
        throw new RuntimeException('Error de conexion con la base de datos: ' . $e->getMessage(), 0, $e);
    }

    return $pdo;
}
