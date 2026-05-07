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
require_once dirname(__DIR__) . '/app/albaranes.php';
require_once dirname(__DIR__) . '/app/pedidos.php';
require_once dirname(__DIR__) . '/app/salidas.php';

require_login();
requierePermiso(PERMISO_ALBARANES);

function tokenConfirmacionAlbaran(): string
{
    if (!isset($_SESSION['albaran_confirmacion_token']) || !is_string($_SESSION['albaran_confirmacion_token'])) {
        $_SESSION['albaran_confirmacion_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['albaran_confirmacion_token'];
}

function validarTokenConfirmacionAlbaran(string $token): bool
{
    $tokenSesion = $_SESSION['albaran_confirmacion_token'] ?? '';

    return is_string($tokenSesion) && $tokenSesion !== '' && hash_equals($tokenSesion, $token);
}

$error = '';
$aviso = '';
$mercanciaSeleccionada = [];
$lineasConfirmadas = [];
$pedidosDisponiblesAlbaran = [];
$columnasTabla = columnasInventarioTabla();
$columnasHistorico = columnasHistoricoTabla();
$numeroAlbaran = trim((string) ($_GET['numero_albaran'] ?? ''));
$confirmado = (string) ($_GET['confirmado'] ?? '') === '1';
$seleccionadosIds = leerIdsSeleccionadosDesdeRequest($_GET);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $accion = trim((string) ($_POST['accion'] ?? ''));

    if ($accion === 'confirmar_albaran') {
        $token = trim((string) ($_POST['csrf_token'] ?? ''));
        $idsConfirmacion = leerIdsSeleccionadosDesdeRequest($_POST);

        if (!validarTokenConfirmacionAlbaran($token)) {
            $error = 'La sesion de confirmacion ha caducado. Vuelve a revisar el albaran antes de confirmar.';
        } elseif ($idsConfirmacion === []) {
            $error = 'No hay lineas validas para confirmar.';
        } else {
            try {
                $pdo = conectar();
                $resultado = confirmarAlbaranSalida($pdo, $idsConfirmacion, obtenerUsuarioOperacionActual());
                $urlRedireccion = BASE_URL . '/albaran.php?confirmado=1&numero_albaran=' . rawurlencode((string) $resultado['numero_albaran']);
                header('Location: ' . $urlRedireccion);
                exit;
            } catch (Throwable $e) {
                $mensaje = trim($e->getMessage());
                $error = $mensaje !== '' ? $mensaje : 'No se pudo confirmar el albaran.';
                $seleccionadosIds = $idsConfirmacion;
            }
        }
    }
}

try {
    $pdo = isset($pdo) && $pdo instanceof PDO ? $pdo : conectar();

    if ($numeroAlbaran !== '') {
        $lineasConfirmadas = consultarHistoricoPorNumeroAlbaran($pdo, $numeroAlbaran);

        if ($lineasConfirmadas === []) {
            $error = $error !== '' ? $error : 'No se ha encontrado un albaran confirmado con el numero indicado.';
        }
    } elseif ($seleccionadosIds !== []) {
        $mercanciaSeleccionada = consultarInventarioPorIds($pdo, $seleccionadosIds, INVENTARIO_ESTADO_ACTIVO);
        $idsActivos = array_map(static fn(array $fila): int => (int) ($fila['id'] ?? 0), $mercanciaSeleccionada);

        if (count($idsActivos) !== count($seleccionadosIds)) {
            $aviso = 'Algunas lineas ya no estaban activas y se han retirado de la revision del albaran.';
        }

        $seleccionadosIds = $idsActivos;
    }

    $pedidosDisponiblesAlbaran = consultarPedidosDisponiblesParaAlbaran($pdo);
} catch (Throwable $e) {
    $mensaje = trim($e->getMessage());
    $error = $error !== '' ? $error : ($mensaje !== '' ? $mensaje : 'No se pudo cargar el albaran.');
}

$resumenLineas = $mercanciaSeleccionada !== [] ? obtenerResumenMercanciaAlbaran($mercanciaSeleccionada) : ['lineas' => 0, 'bultos' => 0];
$resumenConfirmado = $lineasConfirmadas !== [] ? obtenerResumenMercanciaAlbaran($lineasConfirmadas) : ['lineas' => 0, 'bultos' => 0];
$csrfToken = tokenConfirmacionAlbaran();

renderAppLayoutStart(
    'Inventario - Albaran',
    'albaran',
    'Inventario - Albaran',
    'Revision y confirmacion controlada de salidas'
);
?>
<section class="panel panel-card">
    <?php if ($error !== ''): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <?php if ($aviso !== ''): ?>
        <div class="alert alert-warning"><?= htmlspecialchars($aviso, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <?php if ($lineasConfirmadas !== []): ?>
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <div class="alert alert-success d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-4">
                    <div>
                        <strong>Albaran confirmado correctamente.</strong>
                        <div>Numero asignado: <?= htmlspecialchars($numeroAlbaran, ENT_QUOTES, 'UTF-8') ?></div>
                        <div>Las lineas ya no forman parte del inventario activo y han pasado a historico.</div>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <a class="btn btn-outline-primary" href="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/albaran_pdf.php?tipo=salida&amp;numero_albaran=<?= rawurlencode($numeroAlbaran) ?>" target="_blank" rel="noopener">Ver PDF</a>
                        <a class="btn btn-outline-secondary" href="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/historico.php?numero_albaran=<?= rawurlencode($numeroAlbaran) ?>">Ver historico</a>
                        <a class="btn btn-primary" href="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/salida.php">Nueva salida</a>
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-12 col-md-4">
                        <div class="border rounded-3 p-3 h-100 bg-light">
                            <p class="eyebrow mb-1">Estado</p>
                            <h2 class="h5 mb-1">Confirmado</h2>
                            <p class="mb-0 text-body-secondary">Operacion cerrada en SQL y preparada para sincronizacion con Sheets.</p>
                        </div>
                    </div>
                    <div class="col-12 col-md-4">
                        <div class="border rounded-3 p-3 h-100 bg-light">
                            <p class="eyebrow mb-1">Lineas</p>
                            <h2 class="h5 mb-1"><?= htmlspecialchars((string) $resumenConfirmado['lineas'], ENT_QUOTES, 'UTF-8') ?></h2>
                            <p class="mb-0 text-body-secondary">Total de lineas incluidas en el albaran.</p>
                        </div>
                    </div>
                    <div class="col-12 col-md-4">
                        <div class="border rounded-3 p-3 h-100 bg-light">
                            <p class="eyebrow mb-1">Bultos</p>
                            <h2 class="h5 mb-1"><?= htmlspecialchars((string) $resumenConfirmado['bultos'], ENT_QUOTES, 'UTF-8') ?></h2>
                            <p class="mb-0 text-body-secondary">Total de bultos trazados en la confirmacion.</p>
                        </div>
                    </div>
                </div>

                <div class="table-responsive custom-table-wrap">
                    <table class="table table-hover align-middle mb-0 data-table">
                        <thead>
                            <tr>
                                <?php foreach ($columnasHistorico as $titulo): ?>
                                    <th scope="col"><?= htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8') ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($lineasConfirmadas as $fila): ?>
                                <tr>
                                    <?php foreach (array_keys($columnasHistorico) as $columna): ?>
                                        <?php $valor = $fila[$columna] ?? ''; ?>
                                        <td><?= htmlspecialchars((string) ($valor !== null && $valor !== '' ? $valor : '-'), ENT_QUOTES, 'UTF-8') ?></td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php elseif ($mercanciaSeleccionada !== []): ?>
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-4">
                    <div>
                        <p class="eyebrow mb-1">Revision previa</p>
                        <h2 class="section-title mb-1">Albaran de salida pendiente de confirmacion</h2>
                        <p class="mb-0 text-body-secondary">El PDF no cierra la salida. El numero definitivo de albaran se asignara al confirmar.</p>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <form method="GET" action="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/albaran_pdf.php" target="_blank" rel="noopener">
                            <input type="hidden" name="tipo" value="salida">
                            <?php foreach ($seleccionadosIds as $idSeleccionado): ?>
                                <input type="hidden" name="seleccionados[]" value="<?= htmlspecialchars((string) $idSeleccionado, ENT_QUOTES, 'UTF-8') ?>">
                            <?php endforeach; ?>
                            <button class="btn btn-outline-primary" type="submit">Ver PDF borrador</button>
                        </form>
                        <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#confirmarAlbaranModal">Confirmar albaran</button>
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-12 col-md-4">
                        <div class="border rounded-3 p-3 h-100 bg-light">
                            <p class="eyebrow mb-1">Lineas</p>
                            <h2 class="h5 mb-1"><?= htmlspecialchars((string) $resumenLineas['lineas'], ENT_QUOTES, 'UTF-8') ?></h2>
                            <p class="mb-0 text-body-secondary">Lineas activas que se moveran a historico al confirmar.</p>
                        </div>
                    </div>
                    <div class="col-12 col-md-4">
                        <div class="border rounded-3 p-3 h-100 bg-light">
                            <p class="eyebrow mb-1">Bultos</p>
                            <h2 class="h5 mb-1"><?= htmlspecialchars((string) $resumenLineas['bultos'], ENT_QUOTES, 'UTF-8') ?></h2>
                            <p class="mb-0 text-body-secondary">Cantidad total preparada para salida.</p>
                        </div>
                    </div>
                    <div class="col-12 col-md-4">
                        <div class="border rounded-3 p-3 h-100 bg-light">
                            <p class="eyebrow mb-1">Trazabilidad</p>
                            <h2 class="h5 mb-1">Lista</h2>
                            <p class="mb-0 text-body-secondary">Se guardaran estado, fecha, usuario, numero de albaran y marca de sync a historico.</p>
                        </div>
                    </div>
                </div>

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
                            <?php foreach ($mercanciaSeleccionada as $fila): ?>
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
            </div>
        </div>

        <div class="modal fade" id="confirmarAlbaranModal" tabindex="-1" aria-labelledby="confirmarAlbaranLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2 class="modal-title fs-5" id="confirmarAlbaranLabel">Confirmar albaran</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-3">Esta accion cerrara la salida de forma definitiva.</p>
                        <ul class="mb-0">
                            <li>Las lineas dejaran de aparecer en inventario activo.</li>
                            <li>Pasaran a historico con fecha, usuario y numero de albaran.</li>
                            <li>Quedaran marcadas para futura sincronizacion con Google Sheets.</li>
                        </ul>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <form method="POST" action="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/albaran.php" class="d-inline">
                            <input type="hidden" name="accion" value="confirmar_albaran">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                            <?php foreach ($seleccionadosIds as $idSeleccionado): ?>
                                <input type="hidden" name="seleccionados[]" value="<?= htmlspecialchars((string) $idSeleccionado, ENT_QUOTES, 'UTF-8') ?>">
                            <?php endforeach; ?>
                            <button type="submit" class="btn btn-primary">Confirmar y mover a historico</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <p class="eyebrow">Sin seleccion</p>
                <h2 class="section-title">No hay un albaran pendiente de revision</h2>
                <p class="texto mb-4">Selecciona mercancia desde la pantalla de salida para generar el PDF de borrador y confirmar la operacion cuando corresponda.</p>
                <a class="btn btn-primary" href="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/salida.php">Ir a salida</a>
            </div>
        </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm mt-4">
        <div class="card-body">
            <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-3">
                <div>
                    <p class="eyebrow mb-1">Pedidos listos para albaran</p>
                    <h2 class="section-title mb-1">Impresion de albaranes por pedido</h2>
                    <p class="mb-0 text-body-secondary">Solo se muestran pedidos preparados/completados listos para salida documental.</p>
                </div>
            </div>

            <?php if ($pedidosDisponiblesAlbaran === []): ?>
                <div class="alert alert-light border mb-0">No hay pedidos preparados/completados disponibles para albaran.</div>
            <?php else: ?>
                <div class="table-responsive custom-table-wrap">
                    <table class="table table-hover align-middle mb-0 data-table">
                        <thead>
                            <tr>
                                <th scope="col">Codigo pedido</th>
                                <th scope="col">Fecha</th>
                                <th scope="col">Solicitante</th>
                                <th scope="col">Destino</th>
                                <th scope="col">Lineas</th>
                                <th scope="col">Estado</th>
                                <th scope="col">Albaran</th>
                                <th scope="col">Accion</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pedidosDisponiblesAlbaran as $pedidoSalida): ?>
                                <?php
                                $pedidoSalidaId = (int) ($pedidoSalida['id'] ?? 0);
                                $lineasPedido = (int) ($pedidoSalida['lineas_pedido_total'] ?? 0);
                                if ($lineasPedido <= 0) {
                                    $lineasPedido = (int) ($pedidoSalida['total_lineas'] ?? 0);
                                }
                                $estadoPedido = (string) ($pedidoSalida['estado'] ?? '');
                                $destinoImprimible = (bool) ($pedidoSalida['destino_imprimible'] ?? false);
                                $destinoMensaje = trim((string) ($pedidoSalida['destino_mensaje'] ?? ''));
                                $albaranGenerado = (bool) ($pedidoSalida['albaran_generado'] ?? false);
                                ?>
                                <tr>
                                    <td>
                                        <a class="link-primary" href="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/pedido.php?id=<?= rawurlencode((string) $pedidoSalidaId) ?>">
                                            <?= htmlspecialchars((string) ($pedidoSalida['codigo_pedido'] ?? '-'), ENT_QUOTES, 'UTF-8') ?>
                                        </a>
                                    </td>
                                    <td><?= htmlspecialchars((string) (($pedidoSalida['fecha_stock_procesado'] ?? '') !== '' ? $pedidoSalida['fecha_stock_procesado'] : ($pedidoSalida['fecha_creacion'] ?? '-')), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars((string) (($pedidoSalida['usuario_creacion'] ?? '') !== '' ? $pedidoSalida['usuario_creacion'] : '-'), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td>
                                        <span class="badge text-bg-light border"><?= htmlspecialchars((string) ($pedidoSalida['destino_etiqueta'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php if (!$destinoImprimible && $destinoMensaje !== ''): ?>
                                            <div class="small text-danger mt-1"><?= htmlspecialchars($destinoMensaje, ENT_QUOTES, 'UTF-8') ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars((string) $lineasPedido, ENT_QUOTES, 'UTF-8') ?></td>
                                    <td>
                                        <span class="badge <?= htmlspecialchars(claseEstadoPedido($estadoPedido), ENT_QUOTES, 'UTF-8') ?>">
                                            <?= htmlspecialchars(etiquetaEstadoPedido($estadoPedido), ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($albaranGenerado): ?>
                                            <span class="badge text-bg-success">Generado</span>
                                        <?php else: ?>
                                            <span class="badge text-bg-secondary">Pendiente</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($destinoImprimible): ?>
                                            <a class="btn btn-sm btn-outline-primary"
                                               href="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/albaran_pdf.php?tipo=pedido&amp;pedido_id=<?= rawurlencode((string) $pedidoSalidaId) ?>"
                                               target="_blank"
                                               rel="noopener">
                                                <?= $albaranGenerado ? 'Reimprimir albaran' : 'Imprimir albaran' ?>
                                            </a>
                                        <?php else: ?>
                                            <button class="btn btn-sm btn-outline-secondary" type="button" disabled>No imprimible</button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php renderAppLayoutEnd(); ?>
