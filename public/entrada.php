<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/conexion.php';
require_once dirname(__DIR__) . '/app/auth.php';
require_login();

const DESTINOS_PERMITIDOS = ['EDV', 'EPL'];

function obtenerSiguienteIdInventario(PDO $pdo): string
{
    $stmt = $pdo->query('SELECT MAX(id) + 1 AS siguiente FROM inventario');
    $siguiente = $stmt->fetchColumn();

    if ($siguiente === false || $siguiente === null) {
        return '1';
    }

    return (string) max(1, (int) $siguiente);
}

$username = (string) ($_SESSION['username'] ?? '');
$pdo = null;
$siguienteId = '';

try {
    $pdo = conectar();
    $siguienteId = obtenerSiguienteIdInventario($pdo);
} catch (Throwable $e) {
    $siguienteId = '';
}

$datos = [
    'id' => $siguienteId,
    'editorial' => '',
    'colegio' => '',
    'codigo_centro' => '',
    'ubicacion' => '',
    'fecha_entrada' => '',
    'bultos' => '',
    'fecha_salida' => '',
    'destino' => '',
    'orden' => '',
    'indicador_completa' => '',
];

$required = ['editorial', 'colegio', 'codigo_centro', 'ubicacion', 'fecha_entrada', 'bultos'];
$labels = [
    'editorial' => 'Editorial',
    'colegio' => 'Colegio',
    'codigo_centro' => 'Código centro',
    'ubicacion' => 'Ubicación',
    'fecha_entrada' => 'Fecha entrada',
    'bultos' => 'Bultos',
];
$error = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    foreach ($datos as $key => $value) {
        $datos[$key] = trim((string) ($_POST[$key] ?? ''));
    }

    try {
        if (!$pdo instanceof PDO) {
            $pdo = conectar();
        }

        if ($datos['id'] === '') {
            $datos['id'] = obtenerSiguienteIdInventario($pdo);
        }
    } catch (Throwable $e) {
        $error = 'No se pudo calcular el siguiente ID disponible.';
    }

    $faltantes = [];
    if ($error === '') {
        foreach ($required as $key) {
            if ($datos[$key] === '') {
                $faltantes[] = $key;
            }
        }

        if ($faltantes !== []) {
            $campos = array_map(static fn(string $key): string => $labels[$key] ?? $key, $faltantes);
            $error = 'Completa los campos obligatorios: ' . implode(', ', $campos) . '.';
        }
    }

    if ($error === '') {
        $idValidado = filter_var($datos['id'], FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        if ($idValidado === false) {
            $error = 'El ID debe ser un número entero mayor que cero.';
        }
    }

    if ($error === '' && !in_array($datos['destino'], array_merge([''], DESTINOS_PERMITIDOS), true)) {
        $error = 'El destino seleccionado no es válido.';
    }

    if ($error === '') {
        $sql = 'INSERT INTO inventario (
                    id, editorial, colegio, codigo_centro, ubicacion, fecha_entrada, bultos,
                    fecha_salida, destino, `orden`, indicador_completa
                ) VALUES (
                    :id, :editorial, :colegio, :codigo_centro, :ubicacion, :fecha_entrada, :bultos,
                    :fecha_salida, :destino, :orden, :indicador_completa
                )';

        try {
            if (!$pdo instanceof PDO) {
                $pdo = conectar();
            }

            $stmtExiste = $pdo->prepare('SELECT id FROM inventario WHERE id = :id');
            $stmtExiste->execute([':id' => (int) $datos['id']]);

            if ($stmtExiste->fetch() !== false) {
                $error = 'El ID ya existe en el inventario. Introduzca otro valor.';
            } else {
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':id' => (int) $datos['id'],
                    ':editorial' => $datos['editorial'],
                    ':colegio' => $datos['colegio'],
                    ':codigo_centro' => $datos['codigo_centro'],
                    ':ubicacion' => $datos['ubicacion'],
                    ':fecha_entrada' => $datos['fecha_entrada'],
                    ':bultos' => (int) $datos['bultos'],
                    ':fecha_salida' => $datos['fecha_salida'] !== '' ? $datos['fecha_salida'] : null,
                    ':destino' => $datos['destino'] !== '' ? $datos['destino'] : null,
                    ':orden' => $datos['orden'] !== '' ? $datos['orden'] : null,
                    ':indicador_completa' => $datos['indicador_completa'] !== '' ? $datos['indicador_completa'] : null,
                ]);

                header('Location: ' . BASE_URL . '/inventario.php');
                exit;
            }
        } catch (Throwable $e) {
            $error = 'No se pudo guardar la entrada. Revisa los datos e inténtalo de nuevo.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nueva entrada</title>
    <link rel="stylesheet" href="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/css/estilos.css">
</head>
<body class="app-body">
    <header class="topbar">
        <div class="topbar-inner">
            <p class="brand">CONGREGACIONES</p>
            <nav class="main-nav">
                <a class="nav-link" href="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/dashboard.php">Dashboard</a>
                <a class="nav-link" href="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/inventario.php">Inventario</a>
                <a class="nav-link activo" href="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/entrada.php">Nueva entrada</a>
                <a class="nav-link" href="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/centros.php">Centros</a>
                <a class="nav-link salir" href="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/logout.php">Cerrar sesión</a>
            </nav>
            <p class="topbar-user">Usuario: <strong><?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?></strong></p>
        </div>
    </header>

    <main class="app-main">
        <section class="panel">
            <h1>Entrada de inventario</h1>
            <p class="subtitulo">Registro de nueva entrada en almacén</p>

            <?php if ($error !== ''): ?>
                <p class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>

            <form method="POST" action="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/entrada.php" class="form-grid">
                <div class="campo">
                    <label for="id">ID</label>
                    <input id="id" name="id" type="number" min="1" value="<?= htmlspecialchars($datos['id'], ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="campo">
                    <label for="editorial">Editorial *</label>
                    <input id="editorial" name="editorial" type="text" required value="<?= htmlspecialchars($datos['editorial'], ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="campo">
                    <label for="colegio">Colegio *</label>
                    <input id="colegio" name="colegio" type="text" required value="<?= htmlspecialchars($datos['colegio'], ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="campo">
                    <label for="codigo_centro">Código centro *</label>
                    <input id="codigo_centro" name="codigo_centro" type="text" required value="<?= htmlspecialchars($datos['codigo_centro'], ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="campo">
                    <label for="ubicacion">Ubicación *</label>
                    <input id="ubicacion" name="ubicacion" type="text" required value="<?= htmlspecialchars($datos['ubicacion'], ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="campo">
                    <label for="fecha_entrada">Fecha entrada *</label>
                    <input id="fecha_entrada" name="fecha_entrada" type="date" required value="<?= htmlspecialchars($datos['fecha_entrada'], ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="campo">
                    <label for="bultos">Bultos *</label>
                    <input id="bultos" name="bultos" type="number" min="0" required value="<?= htmlspecialchars($datos['bultos'], ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="campo">
                    <label for="fecha_salida">Fecha salida</label>
                    <input id="fecha_salida" name="fecha_salida" type="date" value="<?= htmlspecialchars($datos['fecha_salida'], ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="campo">
                    <label for="destino">Destino</label>
                    <select id="destino" name="destino">
                        <option value=""<?= $datos['destino'] === '' ? ' selected' : '' ?>>Selecciona destino</option>
                        <?php foreach (DESTINOS_PERMITIDOS as $destino): ?>
                            <option value="<?= htmlspecialchars($destino, ENT_QUOTES, 'UTF-8') ?>"<?= $datos['destino'] === $destino ? ' selected' : '' ?>>
                                <?= htmlspecialchars($destino, ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="campo">
                    <label for="orden">Orden</label>
                    <input id="orden" name="orden" type="text" value="<?= htmlspecialchars($datos['orden'], ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="campo">
                    <label for="indicador_completa">Indicador completa</label>
                    <input id="indicador_completa" name="indicador_completa" type="text" value="<?= htmlspecialchars($datos['indicador_completa'], ENT_QUOTES, 'UTF-8') ?>">
                </div>

                <div class="acciones-form">
                    <button class="btn-primary" type="submit">Guardar entrada</button>
                    <a class="btn-secundario" href="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/inventario.php">Volver a inventario</a>
                </div>
            </form>
        </section>
    </main>
</body>
</html>
