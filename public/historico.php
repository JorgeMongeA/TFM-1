<?php

/**
 * Autor: Jorge Monge
 * Trabajo Final de Máster (TFM)
 * UOC - 2026
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/conexion.php';
require_once dirname(__DIR__) . '/app/auth.php';
require_once dirname(__DIR__) . '/app/layout.php';
require_once dirname(__DIR__) . '/app/inventario.php';

require_login();
requierePermiso(PERMISO_HISTORICO);

$filtros = leerFiltrosInventarioDesdeRequest($_GET, filtrosHistoricoPermitidos());
[$ordenar, $direccion] = leerOrdenInventarioDesdeRequest($_GET, columnasHistoricoOrdenables(), 'fecha_confirmacion_salida');
$direccion = isset($_GET['direccion']) ? $direccion : 'DESC';
$registros = [];
$errorCarga = '';
$columnasTabla = columnasHistoricoTabla();

try {
    $pdo = conectar();
    $registros = consultarHistorico($pdo, $filtros, $ordenar, $direccion);
} catch (Throwable $e) {
    $mensajeError = trim($e->getMessage());
    $errorCarga = $mensajeError !== '' ? $mensajeError : 'No se pudo cargar el historico.';
}

renderAppLayoutStart(
    'Inventario - Historico',
    'historico',
    'Inventario - Historico',
    'Mercancia confirmada y cerrada operativamente'
);
?>
<section class="panel panel-card">
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" action="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/historico.php" class="row g-3 align-items-end">
                <div class="col-12 col-md-6 col-xl-2">
                    <label class="form-label" for="numero_albaran">Numero albaran</label>
                    <input class="form-control" id="numero_albaran" name="numero_albaran" type="text" value="<?= htmlspecialchars($filtros['numero_albaran'], ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="col-12 col-md-6 col-xl-2">
                    <label class="form-label" for="editorial">Editorial</label>
                    <input class="form-control" id="editorial" name="editorial" type="text" value="<?= htmlspecialchars($filtros['editorial'], ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="col-12 col-md-6 col-xl-2">
                    <label class="form-label" for="colegio">Colegio</label>
                    <input class="form-control" id="colegio" name="colegio" type="text" value="<?= htmlspecialchars($filtros['colegio'], ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="col-12 col-md-6 col-xl-2">
                    <label class="form-label" for="codigo_centro">Codigo centro</label>
                    <input class="form-control" id="codigo_centro" name="codigo_centro" type="text" value="<?= htmlspecialchars($filtros['codigo_centro'], ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="col-12 col-md-6 col-xl-2">
                    <label class="form-label" for="destino">Destino</label>
                    <input class="form-control" id="destino" name="destino" type="text" value="<?= htmlspecialchars($filtros['destino'], ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="col-12 col-md-6 col-xl-2">
                    <label class="form-label" for="usuario_confirmacion">Usuario</label>
                    <input class="form-control" id="usuario_confirmacion" name="usuario_confirmacion" type="text" value="<?= htmlspecialchars($filtros['usuario_confirmacion'], ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="col-12 d-flex flex-wrap gap-2">
                    <button class="btn btn-primary mt-0" type="submit">Filtrar</button>
                    <a class="btn btn-outline-secondary" href="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/historico.php">Limpiar filtros</a>
                </div>
            </form>
        </div>
    </div>

    <?php if ($errorCarga !== ''): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($errorCarga, ENT_QUOTES, 'UTF-8') ?></div>
    <?php elseif ($registros === []): ?>
        <div class="alert alert-light border mb-0">No se han encontrado registros historicos con los filtros indicados.</div>
    <?php else: ?>
        <div class="table-responsive custom-table-wrap">
            <table class="table table-hover align-middle mb-0 data-table">
                <thead>
                    <tr>
                        <?php foreach ($columnasTabla as $columna => $titulo): ?>
                            <?php
                            $parametrosOrden = array_merge($filtros, ['ordenar' => $columna]);
                            $parametrosOrden['direccion'] = $columna === $ordenar && $direccion === 'ASC' ? 'DESC' : 'ASC';
                            $urlOrden = BASE_URL . '/historico.php?' . http_build_query($parametrosOrden);
                            ?>
                            <th scope="col">
                                <a class="cabecera-enlace<?= $ordenar === $columna ? ' activo' : '' ?>"
                                   href="<?= htmlspecialchars($urlOrden, ENT_QUOTES, 'UTF-8') ?>">
                                    <?= htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8') ?>
                                    <?php if ($ordenar === $columna): ?>
                                        <span class="orden-indicador"><?= $direccion === 'ASC' ? '^' : 'v' ?></span>
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
