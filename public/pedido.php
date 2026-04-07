<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/conexion.php';
require_once dirname(__DIR__) . '/app/auth.php';
require_once dirname(__DIR__) . '/app/layout.php';
require_once dirname(__DIR__) . '/app/inventario.php';
require_once dirname(__DIR__) . '/app/pedidos.php';

require_login();
requierePermiso(PERMISO_PEDIDOS, 'No tienes permisos para acceder a este pedido.');

$pedidoId = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$usuarioPedido = obtenerUsuarioPedidoActual();
$pedido = null;
$lineasPedido = [];
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
    }

    $lineasPedido = consultarLineasPedido($pdo, $pedidoId);
} catch (Throwable $e) {
    $mensajeError = trim($e->getMessage());
    $error = $mensajeError !== '' ? $mensajeError : 'No se ha podido cargar el pedido.';
}

renderAppLayoutStart(
    'Detalle de pedido',
    'pedidos',
    $pedido !== null ? 'Pedido ' . (string) ($pedido['codigo_pedido'] ?? '') : 'Detalle de pedido',
    'Consulta y gestion interna de solicitudes sin afectar al inventario activo'
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
                        <a class="btn btn-outline-secondary" href="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/pedido_print.php?id=<?= rawurlencode((string) $pedidoId) ?>" target="_blank" rel="noopener">Imprimir</a>
                        <a class="btn btn-outline-primary" href="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/pedidos.php">Volver a pedidos</a>
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-12 col-md-3">
                        <div class="border rounded-3 p-3 h-100 bg-light">
                            <p class="eyebrow mb-1">Estado</p>
                            <div><?= htmlspecialchars(etiquetaEstadoPedido((string) ($pedido['estado'] ?? '')), ENT_QUOTES, 'UTF-8') ?></div>
                        </div>
                    </div>
                    <div class="col-12 col-md-3">
                        <div class="border rounded-3 p-3 h-100 bg-light">
                            <p class="eyebrow mb-1">Lineas</p>
                            <div><?= htmlspecialchars((string) ($pedido['total_lineas'] ?? 0), ENT_QUOTES, 'UTF-8') ?></div>
                        </div>
                    </div>
                    <div class="col-12 col-md-3">
                        <div class="border rounded-3 p-3 h-100 bg-light">
                            <p class="eyebrow mb-1">Bultos</p>
                            <div><?= htmlspecialchars((string) ($pedido['total_bultos'] ?? 0), ENT_QUOTES, 'UTF-8') ?></div>
                        </div>
                    </div>
                    <div class="col-12 col-md-3">
                        <div class="border rounded-3 p-3 h-100 bg-light">
                            <p class="eyebrow mb-1">Ultima gestion</p>
                            <div><?= htmlspecialchars((string) (($pedido['fecha_ultima_gestion'] ?? '') !== '' ? $pedido['fecha_ultima_gestion'] : '-'), ENT_QUOTES, 'UTF-8') ?></div>
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
<?php renderAppLayoutEnd(); ?>
