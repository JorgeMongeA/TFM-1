<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/conexion.php';
require_once dirname(__DIR__) . '/app/auth.php';
require_login();

$username = (string) ($_SESSION['username'] ?? '');

$columnasOrdenables = [
    'id',
    'editorial',
    'colegio',
    'codigo_centro',
    'ubicacion',
    'fecha_entrada',
    'fecha_salida',
    'bultos',
    'destino',
    'orden',
    'indicador_completa',
];

$ordenar = (string) ($_GET['ordenar'] ?? 'fecha_entrada');
if (!in_array($ordenar, $columnasOrdenables, true)) {
    $ordenar = 'fecha_entrada';
}

$direccion = strtoupper((string) ($_GET['direccion'] ?? 'ASC'));
if (!in_array($direccion, ['ASC', 'DESC'], true)) {
    $direccion = 'ASC';
}

$sql = "SELECT id, editorial, colegio, codigo_centro, ubicacion, fecha_entrada, fecha_salida, bultos, destino, `orden`, indicador_completa
        FROM inventario
        ORDER BY `{$ordenar}` {$direccion}";

$registros = [];
$errorCarga = '';

try {
    $pdo = conectar();
    $stmt = $pdo->query($sql);
    $registros = $stmt->fetchAll();
} catch (Throwable $e) {
    $errorCarga = 'No se pudo cargar el inventario.';
}

$columnasTabla = [
    'id' => 'ID',
    'editorial' => 'Editorial',
    'colegio' => 'Colegio',
    'codigo_centro' => 'Código centro',
    'ubicacion' => 'Ubicación',
    'fecha_entrada' => 'Fecha entrada',
    'fecha_salida' => 'Fecha salida',
    'bultos' => 'Bultos',
    'destino' => 'Destino',
    'orden' => 'Orden',
    'indicador_completa' => 'Indicador completa',
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventario</title>
    <link rel="stylesheet" href="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/css/estilos.css">
</head>
<body class="app-body">
    <header class="topbar">
        <div class="topbar-inner">
            <p class="brand">CONGREGACIONES</p>
            <nav class="main-nav">
                <a class="nav-link" href="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/dashboard.php">Dashboard</a>
                <a class="nav-link activo" href="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/inventario.php">Inventario</a>
                <a class="nav-link" href="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/entrada.php">Nueva entrada</a>
                <a class="nav-link salir" href="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/logout.php">Cerrar sesión</a>
            </nav>
            <p class="topbar-user">Usuario: <strong><?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?></strong></p>
        </div>
    </header>

    <main class="app-main">
        <section class="panel">
            <h1>Inventario</h1>
            <p class="subtitulo">Gestión y consulta del stock almacenado</p>

            <?php if ($errorCarga !== ''): ?>
                <p class="error"><?= htmlspecialchars($errorCarga, ENT_QUOTES, 'UTF-8') ?></p>
            <?php elseif ($registros === []): ?>
                <p class="texto">No hay registros en inventario.</p>
            <?php else: ?>
                <div class="tabla-responsive">
                    <table class="tabla-datos">
                        <thead>
                            <tr>
                                <?php foreach ($columnasTabla as $columna => $titulo): ?>
                                    <?php
                                    $siguienteDireccion = 'ASC';
                                    if ($columna === $ordenar) {
                                        $siguienteDireccion = $direccion === 'ASC' ? 'DESC' : 'ASC';
                                    }
                                    $urlOrden = BASE_URL . '/inventario.php?ordenar=' . $columna . '&direccion=' . $siguienteDireccion;
                                    ?>
                                    <th>
                                        <a class="cabecera-enlace<?= $ordenar === $columna ? ' activo' : '' ?>"
                                           href="<?= htmlspecialchars($urlOrden, ENT_QUOTES, 'UTF-8') ?>">
                                            <?= htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8') ?>
                                            <?php if ($ordenar === $columna): ?>
                                                <span class="orden-indicador"><?= $direccion === 'ASC' ? '▲' : '▼' ?></span>
                                            <?php endif; ?>
                                        </a>
                                    </th>
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
