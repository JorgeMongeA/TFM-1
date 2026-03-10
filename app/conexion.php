<?php

declare(strict_types=1);

function conectar(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $configFile = dirname(__DIR__) . '/config/config.php';
    if (!is_file($configFile)) {
        throw new RuntimeException('No existe config/config.php');
    }

    $config = require $configFile;

    $host = (string) ($config['db_host'] ?? 'localhost');
    $dbname = (string) ($config['db_name'] ?? '');
    $user = (string) ($config['db_user'] ?? '');
    $pass = (string) ($config['db_pass'] ?? '');
    $charset = (string) ($config['db_charset'] ?? 'utf8mb4');

    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', $host, $dbname, $charset);

    try {
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    } catch (PDOException $e) {
        throw new RuntimeException('Error al conectar con la base de datos: ' . $e->getMessage(), 0, $e);
    }

    return $pdo;
}
