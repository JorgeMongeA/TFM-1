<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/conexion.php';
require_once dirname(__DIR__) . '/app/auth.php';
require_once dirname(__DIR__) . '/app/centros.php';

require_login();

$username = (string) ($_SESSION['username'] ?? '');
$registros = [];
$error = '';
$resultadoSincronizacion = null;
$pdo = null;

$configPath = dirname(__DIR__) . '/config/config.php';
if (!is_file($configPath)) {
    throw new RuntimeException('No existe config/config.php');
}

$config = require $configPath;

$baseUrl = (string) ($config['base_url'] ?? '/CON/public');
$baseUrl = rtrim($baseUrl, '/');

try {
    $pdo = conectar();
} catch (Throwable $e) {
    $mensajeError = trim($e->getMessage());
    $error = $mensajeError !== '' ? $mensajeError : 'No se pudo conectar con la base de datos.';
}

if ($pdo instanceof PDO && (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST')) {
    try {
        $csvUrl = trim((string) ($config['centros_csv_url'] ?? ''));

        if ($csvUrl === '') {
            throw new RuntimeException('Falta la clave centros_csv_url en config/config.php.');
        }

        $resultadoSincronizacion = sincronizarCentrosDesdeCsv($pdo, $csvUrl);
    } catch (Throwable $e) {
        $mensajeError = trim($e->getMessage());
        $error = $mensajeError !== '' ? $mensajeError : 'No se pudieron sincronizar los centros.';
        $resultadoSincronizacion = [
            'total_leidos' => 0,
            'insertados' => 0,
            'actualizados' => 0,
            'ignorados' => 0,
            'errores' => [$error],
        ];
    }
}

if ($pdo instanceof PDO) {
    try {
        $registros = cargarCentros($pdo);
    } catch (Throwable $e) {
        if ($error === '') {
            $mensajeError = trim($e->getMessage());
            $error = $mensajeError !== '' ? $mensajeError : 'No se pudieron cargar los centros.';
        }
    }
}

$columnasTabla = [
    'codigo_centro' => 'Código centro',
    'nombre_centro' => 'Nombre centro',
    'ciudad' => 'Ciudad',
    'tipo' => 'Tipo',
    'codigo_grupo' => 'Código grupo',
    'actualizado_en' => 'Actualizado en',
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Centros</title>
    <link rel="stylesheet" href="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>/css/estilos.css">
</head>
<body class="app-body">
    <header class="topbar">
        <div class="topbar-inner">
            <p class="brand">CONGREGACIONES</p>
            <nav class="main-nav">
                <a class="nav-link" href="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>/dashboard.php">Dashboard</a>
                <a class="nav-link" href="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>/inventario.php">Inventario</a>
                <a class="nav-link" href="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>/entrada.php">Nueva entrada</a>
                <a class="nav-link activo" href="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>/centros.php">Centros</a>
                <a class="nav-link salir" href="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>/logout.php">Cerrar sesión</a>
            </nav>
            <p class="topbar-user">Usuario: <strong><?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?></strong></p>
        </div>
    </header>

    <main class="app-main">
        <section class="panel">
            <h1>Centros</h1>
            <p class="subtitulo">Gestión y sincronización de centros</p>

            <form method="POST" action="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>/centros.php">
                <button class="btn-primary" type="submit">Sincronizar centros</button>
            </form>

            <?php if ($error !== ''): ?>
                <p class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>

            <?php if ($resultadoSincronizacion !== null): ?>
                <div class="resultado-bloque">
                    <p class="resultado-titulo">Resultado de la sincronización</p>
                    <div class="resultado-grid">
                        <p><strong>Total leídos:</strong> <?= htmlspecialchars((string) $resultadoSincronizacion['total_leidos'], ENT_QUOTES, 'UTF-8') ?></p>
                        <p><strong>Insertados:</strong> <?= htmlspecialchars((string) $resultadoSincronizacion['insertados'], ENT_QUOTES, 'UTF-8') ?></p>
                        <p><strong>Actualizados:</strong> <?= htmlspecialchars((string) $resultadoSincronizacion['actualizados'], ENT_QUOTES, 'UTF-8') ?></p>
                        <p><strong>Ignorados:</strong> <?= htmlspecialchars((string) $resultadoSincronizacion['ignorados'], ENT_QUOTES, 'UTF-8') ?></p>
                    </div>

                    <?php if (($resultadoSincronizacion['errores'] ?? []) !== []): ?>
                        <div class="resultado-errores">
                            <p class="resultado-subtitulo">Errores</p>
                            <?php foreach ($resultadoSincronizacion['errores'] as $detalleError): ?>
                                <p class="error"><?= htmlspecialchars((string) $detalleError, ENT_QUOTES, 'UTF-8') ?></p>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($error === '' && $registros === []): ?>
                <p class="texto">No hay centros sincronizados.</p>
            <?php elseif ($error === ''): ?>
                <div class="tabla-responsive">
                    <table class="tabla-datos">
                        <thead>
                            <tr>
                                <?php foreach ($columnasTabla as $titulo): ?>
                                    <th><?= htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8') ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($registros as $fila): ?>
                                <tr>
                                    <?php foreach (array_keys($columnasTabla) as $columna): ?>
                                        <?php
                                        $valor = $fila[$columna] ?? '';
                                        if ($valor === null || $valor === '') {
                                            $valor = '-';
                                        }
                                        ?>
                                        <td><?= htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8') ?></td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>