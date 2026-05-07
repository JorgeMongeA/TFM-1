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

require_login();
requierePermiso(PERMISO_PEDIDOS, 'No tienes permisos para acceder al modulo de pedidos.');

$usuarioPedido = obtenerUsuarioPedidoActual();
$filtrosInventario = leerFiltrosInventarioDesdeRequest($_GET);
[$ordenarInventario, $direccionInventario] = leerOrdenInventarioDesdeRequest($_GET);
$filtrosPedidos = leerFiltrosListadoPedidosDesdeRequest($_GET);
$inventarioDisponible = [];
$lineasComprometidas = [];
$pedidos = [];
$error = '';
$mensaje = '';
$flashPedido = $_SESSION['flash_pedido'] ?? null;
unset($_SESSION['flash_pedido']);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $accion = trim((string) ($_POST['accion'] ?? ''));

    if ($accion === 'crear_pedido') {
        if (!puedeCrearPedidos()) {
            renderizarAccesoDenegado('No tienes permisos para crear pedidos.');
        }

        $seleccionados = $_POST['seleccionados'] ?? [];
        if (!is_array($seleccionados)) {
            $seleccionados = [$seleccionados];
        }

        try {
            $pdo = conectar();
            $resultadoCreacion = crearPedido(
                $pdo,
                $seleccionados,
                $usuarioPedido,
                trim((string) ($_POST['observaciones'] ?? ''))
            );

            $pedidoCreado = is_array($resultadoCreacion['pedido'] ?? null) ? $resultadoCreacion['pedido'] : [];
            $resultadoEmail = is_array($resultadoCreacion['email'] ?? null) ? $resultadoCreacion['email'] : [];

            $mensajeFlash = 'Pedido creado correctamente.';
            if (($resultadoEmail['enabled'] ?? false) === true) {
                $mensajeFlash .= ' ' . (string) ($resultadoEmail['message'] ?? '');
            } else {
                $mensajeFlash .= ' El aviso por email queda preparado pero no esta activo en este entorno.';
            }

            $_SESSION['flash_pedido'] = [
                'tipo' => 'success',
                'mensaje' => $mensajeFlash,
            ];

            header('Location: ' . BASE_URL . '/pedido.php?id=' . rawurlencode((string) ($pedidoCreado['id'] ?? 0)));
            exit;
        } catch (Throwable $e) {
            $mensajeError = trim($e->getMessage());
            $error = $mensajeError !== '' ? $mensajeError : 'No se ha podido crear el pedido.';
        }
    }
}

if (is_array($flashPedido)) {
    $mensaje = trim((string) ($flashPedido['mensaje'] ?? ''));
}

try {
    $pdo = isset($pdo) && $pdo instanceof PDO ? $pdo : conectar();

    if (puedeCrearPedidos()) {
        $inventarioDisponible = consultarInventario($pdo, $filtrosInventario, $ordenarInventario, $direccionInventario);
        $inventarioIdsDisponibles = array_map(
            static fn(array $fila): int => (int) ($fila['id'] ?? 0),
            $inventarioDisponible
        );
        $lineasComprometidas = obtenerLineasComprometidasPorInventarioIds($pdo, $inventarioIdsDisponibles);
        $pedidos = consultarPedidos($pdo, $filtrosPedidos, (int) ($usuarioPedido['user_id'] ?? 0));
    } elseif (puedeGestionarPedidos()) {
        $pedidos = consultarPedidos($pdo, $filtrosPedidos);
    }
} catch (Throwable $e) {
    $mensajeError = trim($e->getMessage());
    $error = $error !== '' ? $error : ($mensajeError !== '' ? $mensajeError : 'No se han podido cargar los pedidos.');
}

$columnasInventario = columnasInventarioTabla();
$estadosPedido = estadosPedidoDisponibles();
$tituloPagina = puedeGestionarPedidos() ? 'Pedidos recibidos' : 'Solicitar pedidos';
$subtituloPagina = puedeGestionarPedidos()
    ? 'Gestion de solicitudes internas enviadas por Edelvives'
    : 'Selecciona mercancia del inventario para generar una solicitud interna al almacen';

renderAppLayoutStart(
    'Pedidos',
    'pedidos',
    $tituloPagina,
    $subtituloPagina
);
?>
<section class="panel panel-card">
    <?php if ($mensaje !== ''): ?>
        <div class="alert alert-success"><?= htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <?php if (puedeCrearPedidos()): ?>
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" action="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/pedidos.php" class="row g-3 align-items-end" autocomplete="off">
                    <div class="col-12 col-md-6 col-xl-3">
                        <label class="form-label" for="editorial">Editorial</label>
                        <input class="form-control" id="editorial" name="editorial" type="text" autocomplete="off" value="<?= htmlspecialchars($filtrosInventario['editorial'], ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-12 col-md-6 col-xl-3">
                        <label class="form-label" for="colegio">Colegio</label>
                        <input class="form-control" id="colegio" name="colegio" type="text" autocomplete="off" value="<?= htmlspecialchars($filtrosInventario['colegio'], ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-12 col-md-6 col-xl-3">
                        <label class="form-label" for="codigo_centro">Codigo centro</label>
                        <input class="form-control" id="codigo_centro" name="codigo_centro" type="text" autocomplete="off" value="<?= htmlspecialchars($filtrosInventario['codigo_centro'], ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-12 col-md-6 col-xl-3">
                        <label class="form-label" for="destino">Destino</label>
                        <input class="form-control" id="destino" name="destino" type="text" autocomplete="off" value="<?= htmlspecialchars($filtrosInventario['destino'], ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <input type="hidden" name="codigo_pedido" value="<?= htmlspecialchars($filtrosPedidos['codigo_pedido'], ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="estado" value="<?= htmlspecialchars($filtrosPedidos['estado'], ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="usuario_creacion" value="<?= htmlspecialchars($filtrosPedidos['usuario_creacion'], ENT_QUOTES, 'UTF-8') ?>">
                    <div class="col-12 d-flex flex-wrap gap-2">
                        <button class="btn btn-primary mt-0" type="submit">Filtrar inventario</button>
                        <a class="btn btn-outline-secondary" href="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/pedidos.php">Limpiar</a>
                    </div>
                </form>
            </div>
        </div>

        <form method="POST" action="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/pedidos.php?<?= htmlspecialchars(http_build_query(array_merge($filtrosInventario, $filtrosPedidos, ['ordenar' => $ordenarInventario, 'direccion' => $direccionInventario])), ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="accion" value="crear_pedido">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-3">
                        <div>
                            <p class="eyebrow mb-1">Mercancia disponible</p>
                            <p class="mb-0 text-body-secondary">Selecciona lineas activas del inventario para componer el pedido.</p>
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                            <button class="btn btn-outline-secondary" type="button" id="seleccionar_todo_pedido">Seleccionar visibles</button>
                            <button class="btn btn-outline-secondary" type="button" id="limpiar_seleccion_pedido">Limpiar seleccion</button>
                            <button class="btn btn-primary" type="submit"<?= $inventarioDisponible === [] ? ' disabled' : '' ?>>Crear pedido</button>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="observaciones">Observaciones para almacen</label>
                        <textarea class="form-control" id="observaciones" name="observaciones" rows="3" placeholder="Indica cualquier detalle util para la preparacion del pedido."><?= htmlspecialchars((string) ($_POST['observaciones'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                    </div>

                    <?php if ($inventarioDisponible === []): ?>
                        <div class="alert alert-light border mb-0">No hay mercancia activa con los filtros indicados.</div>
                    <?php else: ?>
                        <div class="table-responsive custom-table-wrap pedidos-disponibles-table-wrap">
                            <table class="table table-hover align-middle mb-0 data-table pedidos-disponibles-table">
                                <thead>
                                    <tr>
                                        <th scope="col" style="width: 56px;">Sel.</th>
                                        <?php foreach ($columnasInventario as $columna => $titulo): ?>
                                            <?php
                                            $parametrosOrden = array_merge($filtrosInventario, $filtrosPedidos, ['ordenar' => $columna]);
                                            $parametrosOrden['direccion'] = $columna === $ordenarInventario && $direccionInventario === 'ASC' ? 'DESC' : 'ASC';
                                            $urlOrden = BASE_URL . '/pedidos.php?' . http_build_query($parametrosOrden);
                                            ?>
                                            <th scope="col">
                                                <a class="cabecera-enlace<?= $ordenarInventario === $columna ? ' activo' : '' ?>"
                                                   href="<?= htmlspecialchars($urlOrden, ENT_QUOTES, 'UTF-8') ?>">
                                                    <?= htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8') ?>
                                                </a>
                                            </th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($inventarioDisponible as $fila): ?>
                                        <?php $filaId = (int) ($fila['id'] ?? 0); ?>
                                        <?php $lineaComprometida = $lineasComprometidas[$filaId] ?? null; ?>
                                        <tr>
                                            <td>
                                                <?php
                                                $codigoPedidoComprometido = is_array($lineaComprometida) ? trim((string) ($lineaComprometida['codigo_pedido'] ?? '')) : '';
                                                $estadoComprometido = is_array($lineaComprometida) ? etiquetaEstadoPedido((string) ($lineaComprometida['estado'] ?? '')) : '';
                                                $detalleCompromiso = is_array($lineaComprometida)
                                                    ? 'Ya incluida en pedido en curso ' . ($codigoPedidoComprometido !== '' ? $codigoPedidoComprometido . ' - ' : '') . $estadoComprometido
                                                    : '';
                                                ?>
                                                <input class="form-check-input pedido-checkbox" type="checkbox" name="seleccionados[]" value="<?= htmlspecialchars((string) $filaId, ENT_QUOTES, 'UTF-8') ?>"<?= is_array($lineaComprometida) ? ' disabled title="' . htmlspecialchars($detalleCompromiso, ENT_QUOTES, 'UTF-8') . '"' : '' ?>>
                                                <?php if (is_array($lineaComprometida)): ?>
                                                    <span class="badge badge-estado-pedido <?= htmlspecialchars(claseEstadoPedido((string) ($lineaComprometida['estado'] ?? '')), ENT_QUOTES, 'UTF-8') ?> ms-2"
                                                          title="<?= htmlspecialchars($detalleCompromiso, ENT_QUOTES, 'UTF-8') ?>">
                                                        <?= htmlspecialchars($estadoComprometido, ENT_QUOTES, 'UTF-8') ?>
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <?php foreach (array_keys($columnasInventario) as $columna): ?>
                                                <?php $valor = $fila[$columna] ?? ''; ?>
                                                <td><?= htmlspecialchars((string) ($valor !== null && $valor !== '' ? $valor : '-'), ENT_QUOTES, 'UTF-8') ?></td>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    <?php endif; ?>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="d-flex flex-column flex-lg-row align-items-lg-end justify-content-between gap-3 mb-4">
                <div>
                    <p class="eyebrow mb-1"><?= puedeGestionarPedidos() ? 'Gestion de pedidos' : 'Mis pedidos' ?></p>
                    <p class="mb-0 text-body-secondary">
                        <?= puedeGestionarPedidos()
                            ? 'Consulta las solicitudes pendientes y cambia su estado operativo.'
                            : 'Seguimiento de las solicitudes que has enviado a almacen.' ?>
                    </p>
                </div>
                <form method="GET" action="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/pedidos.php" class="row g-2 align-items-end" autocomplete="off">
                    <?php if (puedeCrearPedidos()): ?>
                        <?php foreach ($filtrosInventario as $clave => $valor): ?>
                            <input type="hidden" name="<?= htmlspecialchars($clave, ENT_QUOTES, 'UTF-8') ?>" value="<?= htmlspecialchars($valor, ENT_QUOTES, 'UTF-8') ?>">
                        <?php endforeach; ?>
                        <input type="hidden" name="ordenar" value="<?= htmlspecialchars($ordenarInventario, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="direccion" value="<?= htmlspecialchars($direccionInventario, ENT_QUOTES, 'UTF-8') ?>">
                    <?php endif; ?>
                    <div class="col-12 col-md-auto">
                        <label class="form-label" for="codigo_pedido">Codigo</label>
                        <input class="form-control" id="codigo_pedido" name="codigo_pedido" type="text" autocomplete="off" value="<?= htmlspecialchars($filtrosPedidos['codigo_pedido'], ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-12 col-md-auto">
                        <label class="form-label" for="estado">Estado</label>
                        <select class="form-select" id="estado" name="estado">
                            <option value="">Todos</option>
                            <?php foreach ($estadosPedido as $estado => $label): ?>
                                <option value="<?= htmlspecialchars($estado, ENT_QUOTES, 'UTF-8') ?>"<?= $filtrosPedidos['estado'] === $estado ? ' selected' : '' ?>>
                                    <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php if (puedeGestionarPedidos()): ?>
                        <div class="col-12 col-md-auto">
                            <label class="form-label" for="usuario_creacion">Solicitante</label>
                            <input class="form-control" id="usuario_creacion" name="usuario_creacion" type="text" autocomplete="off" value="<?= htmlspecialchars($filtrosPedidos['usuario_creacion'], ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                    <?php endif; ?>
                    <div class="col-12 col-md-auto d-flex gap-2">
                        <button class="btn btn-primary mt-0" type="submit">Filtrar pedidos</button>
                        <a class="btn btn-outline-secondary" href="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/pedidos.php">Limpiar</a>
                    </div>
                </form>
            </div>

            <?php if ($pedidos === []): ?>
                <div class="alert alert-light border mb-0">No hay pedidos que coincidan con los filtros actuales.</div>
            <?php else: ?>
                <div class="table-responsive custom-table-wrap">
                    <table class="table table-hover align-middle mb-0 data-table">
                        <thead>
                            <tr>
                                <th scope="col">Codigo</th>
                                <th scope="col">Estado</th>
                                <th scope="col">Solicitante</th>
                                <th scope="col">Fecha creacion</th>
                                <th scope="col">Lineas</th>
                                <th scope="col">Bultos</th>
                                <th scope="col">Ultima gestion</th>
                                <th scope="col">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pedidos as $pedido): ?>
                                <tr>
                                    <td><?= htmlspecialchars((string) ($pedido['codigo_pedido'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><span class="badge <?= htmlspecialchars(claseEstadoPedido((string) ($pedido['estado'] ?? '')), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(etiquetaEstadoPedido((string) ($pedido['estado'] ?? '')), ENT_QUOTES, 'UTF-8') ?></span></td>
                                    <td><?= htmlspecialchars((string) ($pedido['usuario_creacion'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars((string) ($pedido['fecha_creacion'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars((string) ($pedido['total_lineas'] ?? 0), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars((string) ($pedido['total_bultos'] ?? 0), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars((string) (($pedido['fecha_ultima_gestion'] ?? '') !== '' ? $pedido['fecha_ultima_gestion'] : '-'), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td>
                                        <div class="d-flex flex-wrap gap-2">
                                            <a class="btn btn-sm btn-outline-primary" href="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/pedido.php?id=<?= rawurlencode((string) ($pedido['id'] ?? 0)) ?>">Abrir</a>
                                            <a class="btn btn-sm btn-outline-secondary" href="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/pedido_print.php?id=<?= rawurlencode((string) ($pedido['id'] ?? 0)) ?>" target="_blank" rel="noopener">Imprimir</a>
                                        </div>
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

<script src="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/js/autocomplete.js"></script>
<script>
const inventarioAutocompleteEndpoint = <?= json_encode(BASE_URL . '/inventario_autocomplete.php', JSON_UNESCAPED_SLASHES) ?>;
const pedidosAutocompleteEndpoint = <?= json_encode(BASE_URL . '/pedidos_autocomplete.php', JSON_UNESCAPED_SLASHES) ?>;

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

['codigo_pedido', 'usuario_creacion'].forEach((campo) => {
    window.AppAutocomplete?.init({
        inputSelector: `#${campo}`,
        endpoint: pedidosAutocompleteEndpoint,
        minChars: 1,
        limit: 10,
        params: { campo },
        emptyText: 'No hay pedidos que coincidan.',
        getInputValue: (item) => item.value || item.label || '',
    });
});
</script>

<?php if (puedeCrearPedidos()): ?>
<script>
const pedidoCheckboxes = Array.from(document.querySelectorAll('.pedido-checkbox:not(:disabled)'));
const seleccionarTodoPedido = document.getElementById('seleccionar_todo_pedido');
const limpiarSeleccionPedido = document.getElementById('limpiar_seleccion_pedido');

if (seleccionarTodoPedido) {
    seleccionarTodoPedido.addEventListener('click', () => {
        pedidoCheckboxes.forEach((checkbox) => {
            checkbox.checked = true;
        });
    });
}

if (limpiarSeleccionPedido) {
    limpiarSeleccionPedido.addEventListener('click', () => {
        pedidoCheckboxes.forEach((checkbox) => {
            checkbox.checked = false;
        });
    });
}
</script>
<?php endif; ?>
<?php renderAppLayoutEnd(); ?>
