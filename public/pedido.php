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
require_once dirname(__DIR__) . '/app/pedidos.php';
require_once dirname(__DIR__) . '/app/albaranes.php';
require_once dirname(__DIR__) . '/app/actividad.php';

require_login();
requierePermiso(PERMISO_PEDIDOS, 'No tienes permisos para acceder a este pedido.');

$pedidoId = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$usuarioPedido = obtenerUsuarioPedidoActual();
$filtrosInventarioEdicion = leerFiltrosInventarioDesdeRequest($_GET);
[$ordenarInventarioEdicion, $direccionInventarioEdicion] = leerOrdenInventarioDesdeRequest($_GET);
$pedido = null;
$lineasPedido = [];
$timelinePedido = [];
$destinoPedidoAlbaran = null;
$inventarioDisponibleEdicion = [];
$lineasComprometidasEdicion = [];
$idsInventarioPedido = [];
$editarPedidoEdelvives = false;
$error = '';
$mensaje = '';
$flashPedido = $_SESSION['flash_pedido'] ?? null;
unset($_SESSION['flash_pedido']);

if (is_array($flashPedido)) {
    $mensaje = trim((string) ($flashPedido['mensaje'] ?? ''));
}

try {
    $pdo = conectar();
    $pedido = consultarPedidoPorId($pdo, $pedidoId);

    if ($pedido === null) {
        throw new RuntimeException('El pedido solicitado no existe.');
    }

    if (!usuarioPuedeVerPedido($pedido, $usuarioPedido)) {
        renderizarAccesoDenegado('No tienes permisos para acceder a este pedido.');
    }

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $accion = trim((string) ($_POST['accion'] ?? ''));

        if ($accion === 'actualizar_estado') {
            if (!puedeGestionarPedidos()) {
                renderizarAccesoDenegado('No tienes permisos para gestionar pedidos.');
            }

            actualizarEstadoPedido($pdo, $pedidoId, (string) ($_POST['estado'] ?? ''), $usuarioPedido);
            $_SESSION['flash_pedido'] = [
                'tipo' => 'success',
                'mensaje' => 'Estado del pedido actualizado correctamente.',
            ];
            header('Location: ' . BASE_URL . '/pedido.php?id=' . rawurlencode((string) $pedidoId));
            exit;
        }

        if ($accion === 'actualizar_pedido_edelvives') {
            $agregarIds = $_POST['agregar_ids'] ?? [];
            $quitarIds = $_POST['quitar_ids'] ?? [];

            if (!is_array($agregarIds)) {
                $agregarIds = [$agregarIds];
            }
            if (!is_array($quitarIds)) {
                $quitarIds = [$quitarIds];
            }

            $resultadoEdicion = actualizarLineasPedidoEnCreacionPorEdelvives(
                $pdo,
                $pedidoId,
                $agregarIds,
                $quitarIds,
                $usuarioPedido,
                (string) ($_POST['observaciones'] ?? '')
            );

            $_SESSION['flash_pedido'] = [
                'tipo' => 'success',
                'mensaje' => sprintf(
                    'Pedido modificado correctamente. Anadidas: %d. Quitadas: %d.',
                    (int) ($resultadoEdicion['lineas_anadidas'] ?? 0),
                    (int) ($resultadoEdicion['lineas_quitadas'] ?? 0)
                ),
            ];
            header('Location: ' . BASE_URL . '/pedido.php?id=' . rawurlencode((string) $pedidoId));
            exit;
        }

        if ($accion === 'cancelar_pedido_edelvives') {
            $resultadoCancelacion = cancelarPedidoEnCreacionPorEdelvives($pdo, $pedidoId, $usuarioPedido);
            $_SESSION['flash_pedido'] = [
                'tipo' => 'success',
                'mensaje' => 'Pedido cancelado correctamente. Lineas liberadas: ' . (int) ($resultadoCancelacion['lineas_liberadas'] ?? 0) . '.',
            ];
            header('Location: ' . BASE_URL . '/pedidos.php');
            exit;
        }
    }

    $lineasPedido = consultarLineasPedido($pdo, $pedidoId);
    $destinoPedidoAlbaran = resolverDestinoPedidoAlbaran($lineasPedido);
    $timelinePedido = obtenerTimelinePedido($pdo, $pedidoId);
    $idsInventarioPedido = array_values(array_filter(array_map(
        static fn(array $linea): int => (int) ($linea['inventario_id'] ?? 0),
        $lineasPedido
    ), static fn(int $inventarioId): bool => $inventarioId > 0));
    $editarPedidoEdelvives = usuarioPuedeEditarOCancelarPedidoEnCreacion($pedido, $usuarioPedido);

    if ($editarPedidoEdelvives) {
        $inventarioDisponibleEdicion = consultarInventario($pdo, $filtrosInventarioEdicion, $ordenarInventarioEdicion, $direccionInventarioEdicion);
        $inventarioIdsDisponibles = array_map(
            static fn(array $fila): int => (int) ($fila['id'] ?? 0),
            $inventarioDisponibleEdicion
        );
        $lineasComprometidasEdicion = obtenerLineasComprometidasPorInventarioIds($pdo, $inventarioIdsDisponibles, $pedidoId);
    }
} catch (Throwable $e) {
    $mensajeError = trim($e->getMessage());
    $error = $mensajeError !== '' ? $mensajeError : 'No se ha podido cargar el pedido.';
}

renderAppLayoutStart(
    'Detalle de pedido',
    'pedidos',
    $pedido !== null ? 'Pedido ' . (string) ($pedido['codigo_pedido'] ?? '') : 'Detalle de pedido',
    'Consulta y gestion interna de solicitudes con trazabilidad operativa de stock'
);
?>
<section class="panel panel-card">
    <?php if ($mensaje !== ''): ?>
        <div class="alert alert-success"><?= htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php elseif ($pedido !== null): ?>
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 mb-4">
                    <div>
                        <p class="eyebrow mb-1">Resumen del pedido</p>
                        <h2 class="section-title mb-2"><?= htmlspecialchars((string) ($pedido['codigo_pedido'] ?? ''), ENT_QUOTES, 'UTF-8') ?></h2>
                        <p class="mb-0 text-body-secondary">
                            Solicitado por <?= htmlspecialchars((string) ($pedido['usuario_creacion'] ?? '-'), ENT_QUOTES, 'UTF-8') ?>
                            el <?= htmlspecialchars((string) ($pedido['fecha_creacion'] ?? '-'), ENT_QUOTES, 'UTF-8') ?>
                        </p>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <span class="badge <?= htmlspecialchars(claseEstadoPedido((string) ($pedido['estado'] ?? '')), ENT_QUOTES, 'UTF-8') ?> align-self-start">
                            <?= htmlspecialchars(etiquetaEstadoPedido((string) ($pedido['estado'] ?? '')), ENT_QUOTES, 'UTF-8') ?>
                        </span>
                        <?php if ($editarPedidoEdelvives): ?>
                            <a class="btn btn-outline-primary" href="#modificarPedido">Modificar pedido</a>
                            <form method="POST" action="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/pedido.php" onsubmit="return confirm('Esta accion cancelara el pedido y liberara sus lineas.');">
                                <input type="hidden" name="accion" value="cancelar_pedido_edelvives">
                                <input type="hidden" name="id" value="<?= htmlspecialchars((string) $pedidoId, ENT_QUOTES, 'UTF-8') ?>">
                                <button class="btn btn-outline-danger" type="submit">Cancelar pedido</button>
                            </form>
                        <?php endif; ?>
                        <?php
                        $pedidoEstado = (string) ($pedido['estado'] ?? '');
                        $pedidoStockProcesado = (int) ($pedido['stock_procesado'] ?? 0) === 1;
                        $pedidoImprimibleAlbaran = $pedidoStockProcesado
                            && in_array($pedidoEstado, [PEDIDO_ESTADO_PREPARADO, PEDIDO_ESTADO_COMPLETADO], true)
                            && is_array($destinoPedidoAlbaran)
                            && (bool) ($destinoPedidoAlbaran['imprimible'] ?? false)
                            && puedeGestionarAlbaranes();
                        ?>
                        <?php if ($pedidoImprimibleAlbaran): ?>
                            <a class="btn btn-outline-primary" href="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/albaran_pdf.php?tipo=pedido&amp;pedido_id=<?= rawurlencode((string) $pedidoId) ?>" target="_blank" rel="noopener">Imprimir albaran</a>
                        <?php endif; ?>
                        <a class="btn btn-outline-secondary" href="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/pedido_print.php?id=<?= rawurlencode((string) $pedidoId) ?>" target="_blank" rel="noopener">Imprimir</a>
                        <a class="btn btn-outline-primary" href="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/pedidos.php">Volver a pedidos</a>
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-12 col-md-6 col-xl-3">
                        <div class="border rounded-3 p-3 h-100 bg-light">
                            <p class="eyebrow mb-1">Estado</p>
                            <div><?= htmlspecialchars(etiquetaEstadoPedido((string) ($pedido['estado'] ?? '')), ENT_QUOTES, 'UTF-8') ?></div>
                        </div>
                    </div>
                    <div class="col-12 col-md-6 col-xl-3">
                        <div class="border rounded-3 p-3 h-100 bg-light">
                            <p class="eyebrow mb-1">Lineas</p>
                            <div><?= htmlspecialchars((string) ($pedido['total_lineas'] ?? 0), ENT_QUOTES, 'UTF-8') ?></div>
                        </div>
                    </div>
                    <div class="col-12 col-md-6 col-xl-3">
                        <div class="border rounded-3 p-3 h-100 bg-light">
                            <p class="eyebrow mb-1">Bultos</p>
                            <div><?= htmlspecialchars((string) ($pedido['total_bultos'] ?? 0), ENT_QUOTES, 'UTF-8') ?></div>
                        </div>
                    </div>
                    <div class="col-12 col-md-6 col-xl-3">
                        <div class="border rounded-3 p-3 h-100 bg-light">
                            <p class="eyebrow mb-1">Ultima gestion</p>
                            <div><?= htmlspecialchars((string) (($pedido['fecha_ultima_gestion'] ?? '') !== '' ? $pedido['fecha_ultima_gestion'] : '-'), ENT_QUOTES, 'UTF-8') ?></div>
                        </div>
                    </div>
                    <div class="col-12 col-md-6 col-xl-3">
                        <div class="border rounded-3 p-3 h-100 bg-light">
                            <p class="eyebrow mb-1">Stock</p>
                            <div>
                                <?= (int) ($pedido['stock_procesado'] ?? 0) === 1 ? 'Stock descontado' : 'Pendiente de descuento' ?>
                            </div>
                            <small class="text-body-secondary">
                                <?= htmlspecialchars((string) (($pedido['fecha_stock_procesado'] ?? '') !== '' ? $pedido['fecha_stock_procesado'] : '-'), ENT_QUOTES, 'UTF-8') ?>
                            </small>
                        </div>
                    </div>
                </div>

                <?php if (((string) ($pedido['observaciones'] ?? '')) !== ''): ?>
                    <div class="border rounded-3 p-3 bg-light mb-4">
                        <p class="eyebrow mb-1">Observaciones</p>
                        <p class="mb-0"><?= nl2br(htmlspecialchars((string) $pedido['observaciones'], ENT_QUOTES, 'UTF-8')) ?></p>
                    </div>
                <?php endif; ?>

                <?php if (puedeGestionarPedidos()): ?>
                    <form method="POST" action="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/pedido.php" class="row g-3 align-items-end mb-4">
                        <input type="hidden" name="accion" value="actualizar_estado">
                        <input type="hidden" name="id" value="<?= htmlspecialchars((string) $pedidoId, ENT_QUOTES, 'UTF-8') ?>">
                        <div class="col-12 col-md-4">
                            <label class="form-label" for="estado">Estado del pedido</label>
                            <select class="form-select" id="estado" name="estado">
                                <?php foreach (estadosPedidoDisponibles() as $estado => $label): ?>
                                    <option value="<?= htmlspecialchars($estado, ENT_QUOTES, 'UTF-8') ?>"<?= (string) ($pedido['estado'] ?? '') === $estado ? ' selected' : '' ?>>
                                        <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-auto">
                            <button class="btn btn-primary mt-0" type="submit">Guardar estado</button>
                        </div>
                    </form>
                <?php endif; ?>

                <?php if ($editarPedidoEdelvives): ?>
                    <?php $columnasInventarioEdicion = columnasInventarioTabla(); ?>
                    <div class="card border-0 shadow-sm mb-4" id="modificarPedido">
                        <div class="card-body">
                            <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-3">
                                <div>
                                    <p class="eyebrow mb-1">Edicion del pedido</p>
                                    <h3 class="section-title mb-1">Modificar pedido en creacion</h3>
                                    <p class="mb-0 text-body-secondary">Puedes anadir o quitar lineas mientras el pedido siga en estado pendiente.</p>
                                </div>
                            </div>

                            <form method="GET" action="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/pedido.php" class="row g-3 align-items-end mb-4" autocomplete="off">
                                <input type="hidden" name="id" value="<?= htmlspecialchars((string) $pedidoId, ENT_QUOTES, 'UTF-8') ?>">
                                <div class="col-12 col-md-6 col-xl-3">
                                    <label class="form-label" for="editorial">Editorial</label>
                                    <input class="form-control" id="editorial" name="editorial" type="text" value="<?= htmlspecialchars($filtrosInventarioEdicion['editorial'], ENT_QUOTES, 'UTF-8') ?>" autocomplete="off">
                                </div>
                                <div class="col-12 col-md-6 col-xl-3">
                                    <label class="form-label" for="colegio">Colegio</label>
                                    <input class="form-control" id="colegio" name="colegio" type="text" value="<?= htmlspecialchars($filtrosInventarioEdicion['colegio'], ENT_QUOTES, 'UTF-8') ?>" autocomplete="off">
                                </div>
                                <div class="col-12 col-md-6 col-xl-3">
                                    <label class="form-label" for="codigo_centro">Codigo centro</label>
                                    <input class="form-control" id="codigo_centro" name="codigo_centro" type="text" value="<?= htmlspecialchars($filtrosInventarioEdicion['codigo_centro'], ENT_QUOTES, 'UTF-8') ?>" autocomplete="off">
                                </div>
                                <div class="col-12 col-md-6 col-xl-3">
                                    <label class="form-label" for="destino">Destino</label>
                                    <input class="form-control" id="destino" name="destino" type="text" value="<?= htmlspecialchars($filtrosInventarioEdicion['destino'], ENT_QUOTES, 'UTF-8') ?>" autocomplete="off">
                                </div>
                                <div class="col-12 d-flex flex-wrap gap-2">
                                    <button class="btn btn-outline-primary mt-0" type="submit">Filtrar inventario</button>
                                    <a class="btn btn-outline-secondary" href="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/pedido.php?id=<?= rawurlencode((string) $pedidoId) ?>#modificarPedido">Limpiar</a>
                                </div>
                            </form>

                            <form method="POST" action="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/pedido.php?<?= htmlspecialchars(http_build_query(array_merge($filtrosInventarioEdicion, ['id' => $pedidoId, 'ordenar' => $ordenarInventarioEdicion, 'direccion' => $direccionInventarioEdicion])), ENT_QUOTES, 'UTF-8') ?>#modificarPedido">
                                <input type="hidden" name="accion" value="actualizar_pedido_edelvives">
                                <input type="hidden" name="id" value="<?= htmlspecialchars((string) $pedidoId, ENT_QUOTES, 'UTF-8') ?>">

                                <div class="mb-3">
                                    <label class="form-label" for="observaciones">Observaciones para almacen</label>
                                    <textarea class="form-control" id="observaciones" name="observaciones" rows="3"><?= htmlspecialchars((string) ($pedido['observaciones'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                                </div>

                                <div class="table-responsive custom-table-wrap mb-3">
                                    <table class="table table-hover align-middle mb-0 data-table">
                                        <thead>
                                            <tr>
                                                <th scope="col" style="width: 70px;">Quitar</th>
                                                <th scope="col">ID inventario</th>
                                                <th scope="col">Editorial</th>
                                                <th scope="col">Colegio</th>
                                                <th scope="col">Codigo centro</th>
                                                <th scope="col">Ubicacion</th>
                                                <th scope="col">Fecha entrada</th>
                                                <th scope="col">Bultos</th>
                                                <th scope="col">Destino</th>
                                                <th scope="col">Orden</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($lineasPedido as $linea): ?>
                                                <tr>
                                                    <td>
                                                        <input class="form-check-input" type="checkbox" name="quitar_ids[]" value="<?= htmlspecialchars((string) ($linea['inventario_id'] ?? 0), ENT_QUOTES, 'UTF-8') ?>">
                                                    </td>
                                                    <td><?= htmlspecialchars((string) ($linea['inventario_id'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                                                    <td><?= htmlspecialchars((string) (($linea['editorial'] ?? '') !== '' ? $linea['editorial'] : '-'), ENT_QUOTES, 'UTF-8') ?></td>
                                                    <td><?= htmlspecialchars((string) (($linea['colegio'] ?? '') !== '' ? $linea['colegio'] : '-'), ENT_QUOTES, 'UTF-8') ?></td>
                                                    <td><?= htmlspecialchars((string) (($linea['codigo_centro'] ?? '') !== '' ? $linea['codigo_centro'] : '-'), ENT_QUOTES, 'UTF-8') ?></td>
                                                    <td><?= htmlspecialchars((string) (($linea['ubicacion'] ?? '') !== '' ? $linea['ubicacion'] : '-'), ENT_QUOTES, 'UTF-8') ?></td>
                                                    <td><?= htmlspecialchars((string) (($linea['fecha_entrada'] ?? '') !== '' ? $linea['fecha_entrada'] : '-'), ENT_QUOTES, 'UTF-8') ?></td>
                                                    <td><?= htmlspecialchars((string) ($linea['bultos'] ?? 0), ENT_QUOTES, 'UTF-8') ?></td>
                                                    <td><?= htmlspecialchars((string) (($linea['destino'] ?? '') !== '' ? $linea['destino'] : '-'), ENT_QUOTES, 'UTF-8') ?></td>
                                                    <td><?= htmlspecialchars((string) (($linea['orden'] ?? '') !== '' ? $linea['orden'] : '-'), ENT_QUOTES, 'UTF-8') ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-3">
                                    <div>
                                        <p class="eyebrow mb-1">Inventario activo para anadir</p>
                                        <p class="mb-0 text-body-secondary">Las lineas ya comprometidas por otros pedidos no se pueden anadir.</p>
                                    </div>
                                    <div class="d-flex flex-wrap gap-2">
                                        <button class="btn btn-outline-secondary" type="button" id="seleccionarTodoAgregar">Seleccionar visibles</button>
                                        <button class="btn btn-outline-secondary" type="button" id="limpiarAgregar">Limpiar seleccion</button>
                                    </div>
                                </div>

                                <?php if ($inventarioDisponibleEdicion === []): ?>
                                    <div class="alert alert-light border">No hay lineas activas disponibles con los filtros actuales.</div>
                                <?php else: ?>
                                    <div class="table-responsive custom-table-wrap mb-4">
                                        <table class="table table-hover align-middle mb-0 data-table">
                                            <thead>
                                                <tr>
                                                    <th scope="col" style="width: 90px;">Anadir</th>
                                                    <?php foreach ($columnasInventarioEdicion as $columna => $titulo): ?>
                                                        <?php
                                                        $parametrosOrden = array_merge($filtrosInventarioEdicion, ['id' => $pedidoId, 'ordenar' => $columna]);
                                                        $parametrosOrden['direccion'] = $columna === $ordenarInventarioEdicion && $direccionInventarioEdicion === 'ASC' ? 'DESC' : 'ASC';
                                                        $urlOrden = BASE_URL . '/pedido.php?' . http_build_query($parametrosOrden) . '#modificarPedido';
                                                        ?>
                                                        <th scope="col">
                                                            <a class="cabecera-enlace<?= $ordenarInventarioEdicion === $columna ? ' activo' : '' ?>" href="<?= htmlspecialchars($urlOrden, ENT_QUOTES, 'UTF-8') ?>">
                                                                <?= htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8') ?>
                                                            </a>
                                                        </th>
                                                    <?php endforeach; ?>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($inventarioDisponibleEdicion as $filaDisponible): ?>
                                                    <?php
                                                    $filaId = (int) ($filaDisponible['id'] ?? 0);
                                                    $lineaComprometida = $lineasComprometidasEdicion[$filaId] ?? null;
                                                    $estaYaEnPedido = in_array($filaId, $idsInventarioPedido, true);
                                                    $bloqueada = is_array($lineaComprometida) || $estaYaEnPedido;
                                                    $estadoComprometido = is_array($lineaComprometida) ? etiquetaEstadoPedido((string) ($lineaComprometida['estado'] ?? '')) : '';
                                                    $codigoComprometido = is_array($lineaComprometida) ? trim((string) ($lineaComprometida['codigo_pedido'] ?? '')) : '';
                                                    $detalleCompromiso = is_array($lineaComprometida)
                                                        ? 'Incluida en pedido ' . ($codigoComprometido !== '' ? $codigoComprometido . ' - ' : '') . $estadoComprometido
                                                        : ($estaYaEnPedido ? 'Ya incluida en este pedido.' : '');
                                                    ?>
                                                    <tr>
                                                        <td>
                                                            <input class="form-check-input agregar-linea-checkbox" type="checkbox" name="agregar_ids[]" value="<?= htmlspecialchars((string) $filaId, ENT_QUOTES, 'UTF-8') ?>"<?= $bloqueada ? ' disabled' : '' ?>>
                                                            <?php if ($estaYaEnPedido): ?>
                                                                <span class="badge text-bg-success ms-2">Actual</span>
                                                            <?php elseif (is_array($lineaComprometida)): ?>
                                                                <span class="badge <?= htmlspecialchars(claseEstadoPedido((string) ($lineaComprometida['estado'] ?? '')), ENT_QUOTES, 'UTF-8') ?> ms-2" title="<?= htmlspecialchars($detalleCompromiso, ENT_QUOTES, 'UTF-8') ?>">
                                                                    <?= htmlspecialchars($estadoComprometido, ENT_QUOTES, 'UTF-8') ?>
                                                                </span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <?php foreach (array_keys($columnasInventarioEdicion) as $columna): ?>
                                                            <?php $valor = $filaDisponible[$columna] ?? ''; ?>
                                                            <td><?= htmlspecialchars((string) ($valor !== null && $valor !== '' ? $valor : '-'), ENT_QUOTES, 'UTF-8') ?></td>
                                                        <?php endforeach; ?>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>

                                <div class="d-flex flex-wrap gap-2">
                                    <button class="btn btn-primary" type="submit">Guardar cambios del pedido</button>
                                    <a class="btn btn-outline-secondary" href="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/pedido.php?id=<?= rawurlencode((string) $pedidoId) ?>">Descartar cambios</a>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="pedido-timeline-card border rounded-4 p-3 p-lg-4 bg-light mb-4">
                    <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-2 mb-3">
                        <div>
                            <p class="eyebrow mb-1">Seguimiento</p>
                            <h3 class="section-title mb-0">Timeline del pedido</h3>
                        </div>
                        <p class="mb-0 text-body-secondary">Evolucion del pedido con cambios de estado y responsables.</p>
                    </div>

                    <?php if ($timelinePedido === []): ?>
                        <div class="alert alert-light border mb-0">Este pedido todavia no tiene eventos registrados.</div>
                    <?php else: ?>
                        <div class="pedido-timeline">
                            <?php foreach ($timelinePedido as $evento): ?>
                                <article class="pedido-timeline-item">
                                    <div class="pedido-timeline-marker">
                                        <span class="pedido-timeline-dot pedido-timeline-dot--<?= htmlspecialchars((string) ($evento['tipo_evento'] ?? 'evento'), ENT_QUOTES, 'UTF-8') ?>">
                                            <?= htmlspecialchars((string) ($evento['icono'] ?? '*'), ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                    </div>
                                    <div class="pedido-timeline-content">
                                        <div class="d-flex flex-column flex-xl-row align-items-xl-center justify-content-between gap-2 mb-2">
                                            <div class="d-flex flex-wrap gap-2 align-items-center">
                                                <span class="badge <?= htmlspecialchars((string) ($evento['badge'] ?? 'text-bg-secondary'), ENT_QUOTES, 'UTF-8') ?>">
                                                    <?php if ((string) ($evento['tipo_evento'] ?? '') === PEDIDO_EVENTO_CREADO): ?>
                                                        Creacion
                                                    <?php elseif ((string) ($evento['tipo_evento'] ?? '') === PEDIDO_EVENTO_STOCK_PROCESADO): ?>
                                                        Stock
                                                    <?php else: ?>
                                                        <?= htmlspecialchars(etiquetaEstadoPedido((string) ($evento['estado_nuevo'] ?? '')), ENT_QUOTES, 'UTF-8') ?>
                                                    <?php endif; ?>
                                                </span>
                                                <span class="pedido-timeline-relative"><?= htmlspecialchars((string) ($evento['tiempo_relativo'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                                            </div>
                                            <span class="pedido-timeline-date"><?= htmlspecialchars((string) ($evento['fecha_evento_legible'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></span>
                                        </div>
                                        <p class="pedido-timeline-text mb-2"><?= htmlspecialchars((string) ($evento['descripcion'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                                        <div class="pedido-timeline-meta">
                                            <span>Usuario <?= htmlspecialchars((string) (($evento['usuario'] ?? '') !== '' ? $evento['usuario'] : 'sistema'), ENT_QUOTES, 'UTF-8') ?></span>
                                            <?php if (((string) ($evento['estado_anterior'] ?? '')) !== '' && ((string) ($evento['estado_nuevo'] ?? '')) !== ''): ?>
                                                <span>
                                                    <?= htmlspecialchars(etiquetaEstadoPedido((string) $evento['estado_anterior']), ENT_QUOTES, 'UTF-8') ?>
                                                    &rarr;
                                                    <?= htmlspecialchars(etiquetaEstadoPedido((string) $evento['estado_nuevo']), ENT_QUOTES, 'UTF-8') ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if ($lineasPedido === []): ?>
                    <div class="alert alert-light border mb-0">Este pedido no contiene lineas.</div>
                <?php else: ?>
                    <div class="table-responsive custom-table-wrap">
                        <table class="table table-hover align-middle mb-0 data-table">
                            <thead>
                                <tr>
                                    <th scope="col">ID inventario</th>
                                    <th scope="col">Editorial</th>
                                    <th scope="col">Colegio</th>
                                    <th scope="col">Codigo centro</th>
                                    <th scope="col">Ubicacion</th>
                                    <th scope="col">Fecha entrada</th>
                                    <th scope="col">Bultos</th>
                                    <th scope="col">Destino</th>
                                    <th scope="col">Orden</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($lineasPedido as $linea): ?>
                                    <tr>
                                        <td><?= htmlspecialchars((string) ($linea['inventario_id'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars((string) (($linea['editorial'] ?? '') !== '' ? $linea['editorial'] : '-'), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars((string) (($linea['colegio'] ?? '') !== '' ? $linea['colegio'] : '-'), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars((string) (($linea['codigo_centro'] ?? '') !== '' ? $linea['codigo_centro'] : '-'), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars((string) (($linea['ubicacion'] ?? '') !== '' ? $linea['ubicacion'] : '-'), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars((string) (($linea['fecha_entrada'] ?? '') !== '' ? $linea['fecha_entrada'] : '-'), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars((string) ($linea['bultos'] ?? 0), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars((string) (($linea['destino'] ?? '') !== '' ? $linea['destino'] : '-'), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars((string) (($linea['orden'] ?? '') !== '' ? $linea['orden'] : '-'), ENT_QUOTES, 'UTF-8') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</section>
<?php if ($editarPedidoEdelvives): ?>
<script src="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/js/autocomplete.js"></script>
<script>
const inventarioAutocompleteEndpoint = <?= json_encode(BASE_URL . '/inventario_autocomplete.php', JSON_UNESCAPED_SLASHES) ?>;

['editorial', 'colegio', 'codigo_centro', 'destino'].forEach((campo) => {
    window.AppAutocomplete?.init({
        inputSelector: `#${campo}`,
        endpoint: inventarioAutocompleteEndpoint,
        minChars: 1,
        limit: 10,
        params: { campo },
        emptyText: 'No hay valores en stock activo.',
        getInputValue: (item) => item.value || item.label || '',
    });
});

const agregarCheckboxes = Array.from(document.querySelectorAll('.agregar-linea-checkbox:not(:disabled)'));
const seleccionarTodoAgregar = document.getElementById('seleccionarTodoAgregar');
const limpiarAgregar = document.getElementById('limpiarAgregar');

if (seleccionarTodoAgregar) {
    seleccionarTodoAgregar.addEventListener('click', () => {
        agregarCheckboxes.forEach((checkbox) => {
            checkbox.checked = true;
        });
    });
}

if (limpiarAgregar) {
    limpiarAgregar.addEventListener('click', () => {
        agregarCheckboxes.forEach((checkbox) => {
            checkbox.checked = false;
        });
    });
}
</script>
<?php endif; ?>
<?php renderAppLayoutEnd(); ?>
