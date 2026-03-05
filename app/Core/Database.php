<?php

declare(strict_types=1);

final class Database
{
    private static ?PDO $connection = null;

    public static function getConnection(): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        $configPath = dirname(__DIR__, 2) . '/config/config.php';
        if (!is_file($configPath)) {
            throw new RuntimeException('Falta el archivo de configuración: config/config.php');
        }

        $config = require $configPath;

        foreach (['db_host','db_name','db_user','db_pass'] as $k) {
            if (!array_key_exists($k, $config)) {
                throw new RuntimeException("Configuración incompleta: falta '{$k}' en config/config.php");
            }
        }

        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=utf8mb4',
            $config['db_host'],
            $config['db_name']
        );

        try {
            self::$connection = new PDO($dsn, $config['db_user'], $config['db_pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            throw new RuntimeException('Error de conexión a la base de datos.', 0, $e);
        }

        return self::$connection;
    }
}