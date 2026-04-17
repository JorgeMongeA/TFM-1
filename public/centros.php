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
requierePermiso(PERMISO_CENTROS_CONSULTA);

$registros = [];
$error = '';
$resultadoSincronizacion = null;
$filtros = leerFiltrosCentrosDesdeRequest($_GET);
$columnasTabla = columnasCentrosTabla();

try {
    $pdo = conectar();

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $config = cargarConfiguracion();
        $csvUrl = trim((string) ($config['centros_csv_url'] ?? ''));

        if ($csvUrl === '') {
            throw new RuntimeException('Falta la clave centros_csv_url en config/config.php.');
        }

        $resultadoSincronizacion = sincronizarCentrosDesdeCsv($pdo, $csvUrl);
    }

    $registros = cargarCentros($pdo, $filtros);
} catch (Throwable $e) {
    $mensajeError = trim($e->getMessage());
    $error = $mensajeError !== '' ? $mensajeError : 'No se pudieron cargar los centros.';
}

renderAppLayoutStart(
    'Centros - Consulta',
    'centros_consulta',
    'Centros - Consulta',
    'Consulta y sincronización de centros con filtros'
);
?>
<section class="panel panel-card">
    <div class="d-flex flex-column flex-lg-row gap-3 mb-4">
        <div class="card border-0 shadow-sm flex-grow-1">
            <div class="card-body">
                <form method="GET" action="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/centros.php" class="row g-3 align-items-end">
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
                        <a class="btn btn-outline-secondary" href="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/centros.php">Limpiar filtros</a>
                    </div>
                </form>
            </div>
        </div>
        <div class="card border-0 shadow-sm sync-card">
            <div class="card-body">
                <p class="eyebrow">Sincronización</p>
                <form method="POST" action="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/centros.php">
                    <button class="btn btn-primary mt-0 w-100" type="submit">Sincronizar centros</button>
                </form>
            </div>
        </div>
    </div>

    <?php if ($error !== ''): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <?php if ($resultadoSincronizacion !== null): ?>
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <p class="eyebrow">Resultado de la sincronización</p>
                <div class="row g-3 mb-3">
                    <div class="col-6 col-xl-3"><strong>Total leídos:</strong> <?= htmlspecialchars((string) $resultadoSincronizacion['total_leidos'], ENT_QUOTES, 'UTF-8') ?></div>
                    <div class="col-6 col-xl-3"><strong>Insertados:</strong> <?= htmlspecialchars((string) $resultadoSincronizacion['insertados'], ENT_QUOTES, 'UTF-8') ?></div>
                    <div class="col-6 col-xl-3"><strong>Actualizados:</strong> <?= htmlspecialchars((string) $resultadoSincronizacion['actualizados'], ENT_QUOTES, 'UTF-8') ?></div>
                    <div class="col-6 col-xl-3"><strong>Ignorados:</strong> <?= htmlspecialchars((string) $resultadoSincronizacion['ignorados'], ENT_QUOTES, 'UTF-8') ?></div>
                </div>
                <?php if (($resultadoSincronizacion['errores'] ?? []) !== []): ?>
                    <div class="alert alert-warning mb-0">
                        <?php foreach ($resultadoSincronizacion['errores'] as $detalleError): ?>
                            <div><?= htmlspecialchars((string) $detalleError, ENT_QUOTES, 'UTF-8') ?></div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($error === '' && $registros === []): ?>
        <div class="alert alert-light border mb-0">No hay centros que coincidan con los filtros actuales.</div>
    <?php elseif ($error === ''): ?>
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
                            <?php foreach (array_keys($columnasTabla) as $columna): ?>
                                <?php $valor = $fila[$columna] ?? ''; ?>
                                <td><?= htmlspecialchars((string) ($valor !== null && $valor !== '' ? $valor : '-'), ENT_QUOTES, 'UTF-8') ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<?php renderAppLayoutEnd(); ?>
