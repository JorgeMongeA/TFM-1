<?php

/**
 * Autor: Jorge Monge
 * Trabajo Final de Máster (TFM)
 * UOC - 2026
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/conexion.php';
require_once dirname(__DIR__) . '/app/auth.php';
require_once dirname(__DIR__) . '/app/centros.php';
require_once dirname(__DIR__) . '/app/layout.php';

require_login();
requierePermiso(PERMISO_CENTROS_EDICION);

$filtros = leerFiltrosCentrosDesdeRequest($_GET);
$columnasTabla = columnasCentrosTabla(true);
$registros = [];
$error = '';
$mensaje = '';

try {
    $pdo = conectar();

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && (string) ($_POST['accion'] ?? '') === 'eliminar') {
        $codigoCentro = trim((string) ($_POST['codigo_centro'] ?? ''));

        if ($codigoCentro === '') {
            throw new RuntimeException('No se ha indicado el centro a eliminar.');
        }

        eliminarCentro($pdo, $codigoCentro);
        header('Location: ' . BASE_URL . '/centros_editar.php?mensaje=eliminado');
        exit;
    }

    $registros = cargarCentros($pdo, $filtros);
} catch (Throwable $e) {
    $mensajeError = trim($e->getMessage());
    $error = $mensajeError !== '' ? $mensajeError : 'No se pudieron cargar los centros.';
}

$mensajeGet = trim((string) ($_GET['mensaje'] ?? ''));
if ($mensajeGet === 'eliminado') {
    $mensaje = 'Centro eliminado correctamente.';
} elseif ($mensajeGet === 'creado') {
    $mensaje = 'Centro creado correctamente.';
} elseif ($mensajeGet === 'actualizado') {
    $mensaje = 'Centro actualizado correctamente.';
}

renderAppLayoutStart(
    'Centros - Editar',
    'centros_editar',
    'Centros - Editar',
    'Edición básica de centros con acciones por fila'
);
?>
<section class="panel panel-card">
    <div class="d-flex flex-column flex-lg-row align-items-lg-end justify-content-between gap-3 mb-4">
        <form method="GET" action="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/centros_editar.php" class="row g-3 flex-grow-1">
            <div class="col-12 col-md-6 col-xl-2">
                <label class="form-label" for="codigo_centro">Código centro</label>
                <input class="form-control" id="codigo_centro" name="codigo_centro" type="text" value="<?= htmlspecialchars($filtros['codigo_centro'], ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="col-12 col-md-6 col-xl-3">
                <label class="form-label" for="nombre_centro">Nombre centro</label>
                <input class="form-control" id="nombre_centro" name="nombre_centro" type="text" value="<?= htmlspecialchars($filtros['nombre_centro'], ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="col-12 col-md-6 col-xl-2">
                <label class="form-label" for="ciudad">Ciudad</label>
                <input class="form-control" id="ciudad" name="ciudad" type="text" value="<?= htmlspecialchars($filtros['ciudad'], ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="col-12 col-md-6 col-xl-2">
                <label class="form-label" for="tipo">Tipo</label>
                <input class="form-control" id="tipo" name="tipo" type="text" value="<?= htmlspecialchars($filtros['tipo'], ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="col-12 col-md-6 col-xl-3">
                <label class="form-label" for="codigo_grupo">Código grupo</label>
                <input class="form-control" id="codigo_grupo" name="codigo_grupo" type="text" value="<?= htmlspecialchars($filtros['codigo_grupo'], ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="col-12 d-flex flex-wrap gap-2">
                <button class="btn btn-primary mt-0" type="submit">Filtrar</button>
                <a class="btn btn-outline-secondary" href="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/centros_editar.php">Limpiar filtros</a>
            </div>
        </form>
        <a class="btn btn-primary" href="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/centro_nuevo.php">Añadir centro</a>
    </div>

    <?php if ($mensaje !== ''): ?>
        <div class="alert alert-success"><?= htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php elseif ($registros === []): ?>
        <div class="alert alert-light border mb-0">No hay centros para editar con los filtros indicados.</div>
    <?php else: ?>
        <div class="table-responsive custom-table-wrap">
            <table class="table table-hover align-middle mb-0 data-table">
                <thead>
                    <tr>
                        <?php foreach ($columnasTabla as $titulo): ?>
                            <th scope="col"><?= htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8') ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($registros as $fila): ?>
                        <tr>
                            <td><?= htmlspecialchars((string) ($fila['codigo_centro'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string) (($fila['nombre_centro'] ?? '') !== '' ? $fila['nombre_centro'] : '-'), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string) (($fila['ciudad'] ?? '') !== '' ? $fila['ciudad'] : '-'), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string) (($fila['tipo'] ?? '') !== '' ? $fila['tipo'] : '-'), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string) (($fila['codigo_grupo'] ?? '') !== '' ? $fila['codigo_grupo'] : '-'), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string) (($fila['actualizado_en'] ?? '') !== '' ? $fila['actualizado_en'] : '-'), ENT_QUOTES, 'UTF-8') ?></td>
                            <td>
                                <div class="d-flex flex-wrap gap-2">
                                    <a class="btn btn-sm btn-outline-primary" href="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/centro_editar.php?codigo_centro=<?= rawurlencode((string) $fila['codigo_centro']) ?>">Editar</a>
                                    <form method="POST" action="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/centros_editar.php" onsubmit="return confirm('¿Eliminar este centro?');">
                                        <input type="hidden" name="accion" value="eliminar">
                                        <input type="hidden" name="codigo_centro" value="<?= htmlspecialchars((string) $fila['codigo_centro'], ENT_QUOTES, 'UTF-8') ?>">
                                        <button class="btn btn-sm btn-outline-danger mt-0" type="submit">Eliminar</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<?php renderAppLayoutEnd(); ?>
