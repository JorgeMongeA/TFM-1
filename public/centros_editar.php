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
$paginacion = leerPaginacionDesdeRequest($_GET);
[$ordenar, $direccion] = leerOrdenCentrosDesdeRequest($_GET);
$columnasTabla = columnasCentrosTabla(true);
$registros = [];
$paginacionVista = null;
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
        $returnQuery = trim((string) ($_POST['return_query'] ?? ''));
        $query = [];
        if ($returnQuery !== '') {
            parse_str($returnQuery, $query);
        }
        $query['mensaje'] = 'eliminado';
        header('Location: ' . BASE_URL . '/centros_editar.php?' . http_build_query($query));
        exit;
    }

    $resultadoConsulta = cargarCentros($pdo, $filtros, $ordenar, $direccion, $paginacion);
    $registros = is_array($resultadoConsulta['registros'] ?? null) ? $resultadoConsulta['registros'] : [];
    $paginacionVista = is_array($resultadoConsulta['paginacion'] ?? null) ? $resultadoConsulta['paginacion'] : null;
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
                <label class="form-label" for="ciudad">Localidad</label>
                <input class="form-control" id="ciudad" name="ciudad" type="text" value="<?= htmlspecialchars($filtros['ciudad'], ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="col-12 col-md-6 col-xl-3">
                <label class="form-label" for="congregacion">Congregación</label>
                <input class="form-control" id="congregacion" name="congregacion" type="text" value="<?= htmlspecialchars($filtros['congregacion'], ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="col-12 col-md-6 col-xl-2">
                <label class="form-label" for="destino">Destino</label>
                <input class="form-control" id="destino" name="destino" type="text" value="<?= htmlspecialchars($filtros['destino'], ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="col-12 d-flex flex-wrap gap-2">
                <input type="hidden" name="ordenar" value="<?= htmlspecialchars($ordenar, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="direccion" value="<?= htmlspecialchars($direccion, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="per_page" value="<?= htmlspecialchars((string) ($paginacionVista['per_page'] ?? $paginacion['per_page']), ENT_QUOTES, 'UTF-8') ?>">
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
        <div class="table-responsive custom-table-wrap scroll-horizontal-visible">
            <table class="table table-hover align-middle mb-0 data-table">
                <thead>
                    <tr>
                        <?php foreach ($columnasTabla as $columna => $titulo): ?>
                            <th scope="col">
                                <?php if ($columna === 'acciones'): ?>
                                    <?= htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8') ?>
                                <?php else: ?>
                                    <?php
                                    $parametrosOrden = array_merge($filtros, [
                                        'ordenar' => $columna,
                                        'per_page' => (int) ($paginacionVista['per_page'] ?? $paginacion['per_page']),
                                        'page' => 1,
                                    ]);
                                    $parametrosOrden['direccion'] = $columna === $ordenar && $direccion === 'ASC' ? 'DESC' : 'ASC';
                                    $urlOrden = BASE_URL . '/centros_editar.php?' . http_build_query($parametrosOrden);
                                    ?>
                                    <a class="cabecera-enlace<?= $ordenar === $columna ? ' activo' : '' ?>" href="<?= htmlspecialchars($urlOrden, ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8') ?>
                                        <?php if ($ordenar === $columna): ?>
                                            <span class="orden-indicador"><?= $direccion === 'ASC' ? '^' : 'v' ?></span>
                                        <?php endif; ?>
                                    </a>
                                <?php endif; ?>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($registros as $fila): ?>
                        <tr>
                            <?php foreach (array_keys($columnasTabla) as $columna): ?>
                                <?php if ($columna === 'acciones'): ?>
                                    <td>
                                        <div class="d-flex flex-wrap gap-2">
                                            <a class="btn btn-sm btn-outline-primary" href="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/centro_editar.php?codigo_centro=<?= rawurlencode((string) $fila['codigo_centro']) ?>">Editar</a>
                                            <form method="POST" action="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/centros_editar.php" onsubmit="return confirm('¿Eliminar este centro?');">
                                                <input type="hidden" name="accion" value="eliminar">
                                                <input type="hidden" name="codigo_centro" value="<?= htmlspecialchars((string) $fila['codigo_centro'], ENT_QUOTES, 'UTF-8') ?>">
                                                <input type="hidden" name="return_query" value="<?= htmlspecialchars((string) ($_SERVER['QUERY_STRING'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                                <button class="btn btn-sm btn-outline-danger mt-0" type="submit">Eliminar</button>
                                            </form>
                                        </div>
                                    </td>
                                <?php else: ?>
                                    <?php $valor = $fila[$columna] ?? ''; ?>
                                    <td><?= htmlspecialchars((string) ($valor !== null && $valor !== '' ? $valor : '-'), ENT_QUOTES, 'UTF-8') ?></td>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php renderPaginacionListado(
            BASE_URL . '/centros_editar.php',
            $paginacionVista ?? construirPaginacion(count($registros), 1, $paginacion['per_page']),
            array_merge($filtros, ['ordenar' => $ordenar, 'direccion' => $direccion, 'per_page' => (int) ($paginacionVista['per_page'] ?? $paginacion['per_page'])])
        ); ?>
    <?php endif; ?>
</section>
<?php renderAppLayoutEnd(); ?>
