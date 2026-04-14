<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/conexion.php';
require_once dirname(__DIR__) . '/app/auth.php';
require_once dirname(__DIR__) . '/app/layout.php';
require_once dirname(__DIR__) . '/app/inventario.php';
require_once dirname(__DIR__) . '/app/inventario_sync.php';
require_once dirname(__DIR__) . '/app/inventario_sync_bidireccional.php';

require_login();
requierePermiso(PERMISO_INVENTARIO_CONSULTA);

function tokenAnulacionInventario(): string
{
    iniciar_sesion();

    if (!isset($_SESSION['inventario_anulacion_token']) || !is_string($_SESSION['inventario_anulacion_token'])) {
        $_SESSION['inventario_anulacion_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['inventario_anulacion_token'];
}

function validarTokenAnulacionInventario(string $token): bool
{
    iniciar_sesion();
    $tokenSesion = $_SESSION['inventario_anulacion_token'] ?? '';

    return is_string($tokenSesion) && $tokenSesion !== '' && hash_equals($tokenSesion, $token);
}

$filtros = leerFiltrosInventarioDesdeRequest($_GET);
[$ordenar, $direccion] = leerOrdenInventarioDesdeRequest($_GET);
$registros = [];
$errorCarga = '';
$resultadoSincronizacion = null;
$resultadoReflejoSheets = null;
$flashInventario = $_SESSION['flash_inventario'] ?? null;
$flashSyncHistorico = $_SESSION['flash_sync_historico'] ?? null;
unset($_SESSION['flash_inventario'], $_SESSION['flash_sync_historico']);

$mensajeInventario = '';
$tipoMensajeInventario = 'success';
$columnasTabla = columnasInventarioTabla();
$csrfAnulacion = tokenAnulacionInventario();

if (is_array($flashInventario)) {
    $mensajeInventario = trim((string) ($flashInventario['mensaje'] ?? ''));
    $tipoMensajeInventario = trim((string) ($flashInventario['tipo'] ?? 'success'));

    if (!in_array($tipoMensajeInventario, ['success', 'warning', 'danger'], true)) {
        $tipoMensajeInventario = 'success';
    }
}

try {
    $pdo = conectar();

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $accionInventario = trim((string) ($_POST['accion'] ?? ''));
        $accionSincronizacion = trim((string) ($_POST['sync_action'] ?? 'sheet_to_sql'));

        if ($accionInventario === 'anular_inventario') {
            requierePermiso(PERMISO_INVENTARIO_EDICION, 'No tienes permisos para anular entradas de inventario.');

            $token = trim((string) ($_POST['csrf_token'] ?? ''));
            if (!validarTokenAnulacionInventario($token)) {
                throw new RuntimeException('La sesion de anulacion ha caducado. Vuelve a intentarlo.');
            }

            $inventarioId = (int) ($_POST['inventario_id'] ?? 0);
            $motivoAnulacion = trim((string) ($_POST['motivo_anulacion'] ?? ''));
            anularEntradaInventario($pdo, $inventarioId, obtenerContextoActividadActual(), $motivoAnulacion);

            $mensajeFlash = 'Entrada ID ' . $inventarioId . ' eliminada correctamente.';
            $tipoFlash = 'success';

            try {
                $config = cargarConfiguracion();
                $scriptUrl = obtenerUrlWebAppGoogleSheets($config);
                eliminarFilaInventarioEnSheets($scriptUrl, $inventarioId);
                $mensajeFlash .= ' Google Sheets se ha actualizado.';
            } catch (Throwable $syncError) {
                error_log('[GOOGLE_SYNC] inventario_consulta.php anulacion | ' . $syncError->getMessage());
                $tipoFlash = 'warning';
                $mensajeFlash .= ' El borrado en la app esta hecho, pero Google Sheets no ha respondido.';
            }

            $_SESSION['flash_inventario'] = [
                'tipo' => $tipoFlash,
                'mensaje' => $mensajeFlash,
            ];

            $returnQuery = trim((string) ($_POST['return_query'] ?? ''));
            $urlRedireccion = BASE_URL . '/inventario_consulta.php' . ($returnQuery !== '' ? '?' . $returnQuery : '');
            header('Location: ' . $urlRedireccion);
            exit;
        }

        requierePermiso(PERMISO_SINCRONIZACIONES, 'No tienes permisos para sincronizar inventario.');
        $config = cargarConfiguracion();

        if ($accionSincronizacion === 'sql_to_sheet') {
            $scriptUrl = obtenerUrlWebAppGoogleSheets($config);
            $token = obtenerTokenSyncGoogleSheets();
            $resultadoReflejoSheets = sincronizarInventarioSheetsDesdeSql($pdo, $scriptUrl, $token);
        } else {
            $scriptUrl = obtenerUrlWebAppGoogleSheets($config);
            $token = obtenerTokenSyncGoogleSheets();
            $resultadoSincronizacion = sincronizarInventarioBidireccional($pdo, $scriptUrl, $token);
        }
    }

    $registros = consultarInventario($pdo, $filtros, $ordenar, $direccion);
} catch (Throwable $e) {
    error_log('[GOOGLE_SYNC] inventario_consulta.php | ' . $e->getMessage());
    $mensajeError = trim($e->getMessage());
    $errorCarga = $mensajeError !== '' ? $mensajeError : 'No se pudo cargar el inventario.';
}

renderAppLayoutStart(
    'Inventario - Consulta',
    'inventario_consulta',
    'Inventario - Consulta',
    'Consulta de stock actual y borrado directo con reflejo hacia Google Sheets'
);
?>
<section class="panel panel-card">
    <div class="d-flex flex-column flex-lg-row gap-3 mb-4">
        <div class="card border-0 shadow-sm flex-grow-1">
            <div class="card-body">
                <form method="GET" action="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/inventario_consulta.php" class="row g-3 align-items-end">
                    <div class="col-12 col-md-6 col-xl-3">
                        <label class="form-label" for="editorial">Editorial</label>
                        <input class="form-control" id="editorial" name="editorial" type="text" value="<?= htmlspecialchars($filtros['editorial'], ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-12 col-md-6 col-xl-3">
                        <label class="form-label" for="colegio">Colegio</label>
                        <input class="form-control" id="colegio" name="colegio" type="text" value="<?= htmlspecialchars($filtros['colegio'], ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-12 col-md-6 col-xl-3">
                        <label class="form-label" for="codigo_centro">Codigo centro</label>
                        <input class="form-control" id="codigo_centro" name="codigo_centro" type="text" value="<?= htmlspecialchars($filtros['codigo_centro'], ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-12 col-md-6 col-xl-3">
                        <label class="form-label" for="destino">Destino</label>
                        <input class="form-control" id="destino" name="destino" type="text" value="<?= htmlspecialchars($filtros['destino'], ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-12 d-flex flex-wrap gap-2">
                        <button class="btn btn-primary mt-0" type="submit">Filtrar</button>
                        <a class="btn btn-outline-secondary" href="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/inventario_consulta.php">Limpiar filtros</a>
                        <a class="btn btn-outline-dark" href="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/inventario_pdf.php?<?= htmlspecialchars(http_build_query(array_merge($filtros, ['ordenar' => $ordenar, 'direccion' => $direccion])), ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">Imprimir stock PDF</a>
                    </div>
                </form>
            </div>
        </div>
        <?php if (puedeSincronizar()): ?>
        <div class="card border-0 shadow-sm sync-card">
            <div class="card-body">
                <p class="eyebrow">Sincronizacion</p>
                <div class="d-grid gap-2">
                    <form method="POST" action="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/inventario_consulta.php">
                        <input type="hidden" name="sync_action" value="sheet_to_sql">
                        <button class="btn btn-primary mt-0 w-100" type="submit">Sincronizar desde Google Sheets</button>
                    </form>
                    <form method="POST" action="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/inventario_consulta.php">
                        <input type="hidden" name="sync_action" value="sql_to_sheet">
                        <button class="btn btn-outline-primary w-100" type="submit">Reflejar inventario en Google Sheets</button>
                    </form>
                    <form method="POST" action="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/sync_historico.php">
                        <button class="btn btn-outline-success w-100" type="submit">Sincronizar historico con Google Sheets</button>
                    </form>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($errorCarga !== ''): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($errorCarga, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <?php if ($mensajeInventario !== ''): ?>
        <div class="alert alert-<?= htmlspecialchars($tipoMensajeInventario, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($mensajeInventario, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <?php if (is_array($flashSyncHistorico)): ?>
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <p class="eyebrow">Sincronizacion de historico</p>
                <?php if (($flashSyncHistorico['ok'] ?? false) === true): ?>
                    <?php $resultadoHistorico = is_array($flashSyncHistorico['resultado'] ?? null) ? $flashSyncHistorico['resultado'] : []; ?>
                    <div class="alert alert-success mb-3">Historico sincronizado correctamente con Google Sheets.</div>
                    <div class="row g-3">
                        <div class="col-6 col-xl-3"><strong>Insertados en historico:</strong> <?= htmlspecialchars((string) ($resultadoHistorico['insertados_historico'] ?? 0), ENT_QUOTES, 'UTF-8') ?></div>
                        <div class="col-6 col-xl-3"><strong>Ya existentes:</strong> <?= htmlspecialchars((string) ($resultadoHistorico['ya_existian_historico'] ?? 0), ENT_QUOTES, 'UTF-8') ?></div>
                        <div class="col-6 col-xl-3"><strong>Retirados de inventario:</strong> <?= htmlspecialchars((string) ($resultadoHistorico['retirados_inventario'] ?? 0), ENT_QUOTES, 'UTF-8') ?></div>
                        <div class="col-6 col-xl-3"><strong>Marcados en SQL:</strong> <?= htmlspecialchars((string) ($resultadoHistorico['sincronizados_sql'] ?? 0), ENT_QUOTES, 'UTF-8') ?></div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-danger mb-0"><?= htmlspecialchars((string) ($flashSyncHistorico['mensaje'] ?? 'No se pudo sincronizar el historico con Google Sheets.'), ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($resultadoSincronizacion !== null): ?>
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <p class="eyebrow">Resultado de sincronizacion Google Sheets -> SQL</p>
                <div class="row g-3 mb-3">
                    <div class="col-6 col-xl-3"><strong>Total SQL:</strong> <?= htmlspecialchars((string) ($resultadoSincronizacion['total_sql'] ?? 0), ENT_QUOTES, 'UTF-8') ?></div>
                    <div class="col-6 col-xl-3"><strong>Total Sheets:</strong> <?= htmlspecialchars((string) ($resultadoSincronizacion['total_sheet'] ?? 0), ENT_QUOTES, 'UTF-8') ?></div>
                    <div class="col-6 col-xl-3"><strong>Insertados en SQL:</strong> <?= htmlspecialchars((string) ($resultadoSincronizacion['insertados_en_sql'] ?? 0), ENT_QUOTES, 'UTF-8') ?></div>
                    <div class="col-6 col-xl-3"><strong>Actualizados en SQL:</strong> <?= htmlspecialchars((string) ($resultadoSincronizacion['actualizados_en_sql'] ?? 0), ENT_QUOTES, 'UTF-8') ?></div>
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

    <?php if ($resultadoReflejoSheets !== null): ?>
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <p class="eyebrow">Resultado de sincronizacion SQL -> Google Sheets</p>
                <div class="alert alert-success mb-3">
                    Google Sheets refleja ahora exactamente el inventario activo de la aplicacion.
                </div>
                <div class="row g-3">
                    <div class="col-6 col-xl-4"><strong>Filas activas en SQL:</strong> <?= htmlspecialchars((string) ($resultadoReflejoSheets['total_sql'] ?? 0), ENT_QUOTES, 'UTF-8') ?></div>
                    <div class="col-6 col-xl-4"><strong>Filas escritas en Sheets:</strong> <?= htmlspecialchars((string) ($resultadoReflejoSheets['reemplazados_en_sheet'] ?? 0), ENT_QUOTES, 'UTF-8') ?></div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($errorCarga === '' && $registros === []): ?>
        <div class="alert alert-light border mb-0">No se han encontrado registros de inventario con los filtros indicados.</div>
    <?php elseif ($errorCarga === ''): ?>
        <div class="table-responsive custom-table-wrap">
            <table class="table table-hover align-middle mb-0 data-table">
                <thead>
                    <tr>
                        <?php foreach ($columnasTabla as $columna => $titulo): ?>
                            <?php
                            $parametrosOrden = array_merge($filtros, ['ordenar' => $columna]);
                            $parametrosOrden['direccion'] = $columna === $ordenar && $direccion === 'ASC' ? 'DESC' : 'ASC';
                            $urlOrden = BASE_URL . '/inventario_consulta.php?' . http_build_query($parametrosOrden);
                            ?>
                            <th scope="col">
                                <a class="cabecera-enlace<?= $ordenar === $columna ? ' activo' : '' ?>"
                                   href="<?= htmlspecialchars($urlOrden, ENT_QUOTES, 'UTF-8') ?>">
                                    <?= htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8') ?>
                                    <?php if ($ordenar === $columna): ?>
                                        <span class="orden-indicador"><?= $direccion === 'ASC' ? '▲' : '▼' ?></span>
                                    <?php endif; ?>
                                </a>
                            </th>
                        <?php endforeach; ?>
                        <?php if (puedeEditarInventario()): ?>
                            <th scope="col">Acciones</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($registros as $fila): ?>
                        <tr>
                            <?php foreach (array_keys($columnasTabla) as $columna): ?>
                                <?php $valor = $fila[$columna] ?? ''; ?>
                                <td><?= htmlspecialchars((string) ($valor !== null && $valor !== '' ? $valor : '-'), ENT_QUOTES, 'UTF-8') ?></td>
                            <?php endforeach; ?>
                            <?php if (puedeEditarInventario()): ?>
                                <?php
                                $filaId = (int) ($fila['id'] ?? 0);
                                $resumenFila = trim(implode(' | ', array_filter([
                                    'ID ' . $filaId,
                                    (string) ($fila['editorial'] ?? ''),
                                    (string) ($fila['colegio'] ?? ''),
                                    (string) ($fila['ubicacion'] ?? ''),
                                ])));
                                ?>
                                <td>
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-outline-danger"
                                        data-bs-toggle="modal"
                                        data-bs-target="#anularInventarioModal"
                                        data-inventario-id="<?= htmlspecialchars((string) $filaId, ENT_QUOTES, 'UTF-8') ?>"
                                        data-inventario-resumen="<?= htmlspecialchars($resumenFila, ENT_QUOTES, 'UTF-8') ?>"
                                    >
                                        Borrar
                                    </button>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php if (puedeEditarInventario()): ?>
<div class="modal fade" id="anularInventarioModal" tabindex="-1" aria-labelledby="anularInventarioLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title fs-5" id="anularInventarioLabel">Borrar entrada de inventario</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2">Esta accion borrara la entrada del sistema y tratara de borrarla tambien de Google Sheets.</p>
                <div class="alert alert-light border mb-3">
                    <strong>Entrada seleccionada:</strong>
                    <div id="anularInventarioResumen" class="mt-1">Sin seleccion</div>
                </div>
                <p class="mb-0 text-body-secondary">La operacion quedara registrada en actividad.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <form method="POST" action="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/inventario_consulta.php" class="w-100 d-grid gap-2">
                    <input type="hidden" name="accion" value="anular_inventario">
                    <input type="hidden" name="inventario_id" id="anularInventarioId" value="">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfAnulacion, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="return_query" value="<?= htmlspecialchars((string) ($_SERVER['QUERY_STRING'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="motivo_anulacion" value="Borrado manual desde inventario">
                    <button type="submit" class="btn btn-danger">Confirmar borrado</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
const anularInventarioModal = document.getElementById('anularInventarioModal');
const anularInventarioIdInput = document.getElementById('anularInventarioId');
const anularInventarioResumen = document.getElementById('anularInventarioResumen');

if (anularInventarioModal) {
    anularInventarioModal.addEventListener('show.bs.modal', (event) => {
        const trigger = event.relatedTarget;
        const inventarioId = trigger?.getAttribute('data-inventario-id') || '';
        const resumen = trigger?.getAttribute('data-inventario-resumen') || 'Sin seleccion';

        if (anularInventarioIdInput) {
            anularInventarioIdInput.value = inventarioId;
        }

        if (anularInventarioResumen) {
            anularInventarioResumen.textContent = resumen;
        }
    });
}
</script>
<?php endif; ?>
<?php renderAppLayoutEnd(); ?>
