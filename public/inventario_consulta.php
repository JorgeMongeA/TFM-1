<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/conexion.php';
require_once dirname(__DIR__) . '/app/auth.php';
require_once dirname(__DIR__) . '/app/layout.php';
require_once dirname(__DIR__) . '/app/inventario.php';
require_once dirname(__DIR__) . '/app/inventario_sync.php';
require_once dirname(__DIR__) . '/app/inventario_sync_bidireccional.php';

require_login();

$filtros = leerFiltrosInventarioDesdeRequest($_GET);
[$ordenar, $direccion] = leerOrdenInventarioDesdeRequest($_GET);
$registros = [];
$errorCarga = '';
$resultadoSincronizacion = null;
$resultadoBidireccional = null;
$columnasTabla = columnasInventarioTabla();

try {
    $pdo = conectar();

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $config = cargarConfiguracion();
        $accionSincronizacion = trim((string) ($_POST['sync_action'] ?? 'csv'));

        if ($accionSincronizacion === 'bidireccional') {
            $scriptUrl = trim((string) ($config['google_inventory_sync_url'] ?? ''));
            $token = trim((string) ($config['google_inventory_sync_token'] ?? ''));

            if ($scriptUrl === '' || $scriptUrl === 'PONER_AQUI_URL_WEB_APP_DE_APPS_SCRIPT') {
                throw new RuntimeException('Falta configurar google_inventory_sync_url en config/config.php.');
            }

            if ($token === '' || $token === 'PONER_AQUI_TOKEN_SECRETO_SIMPLE') {
                throw new RuntimeException('Falta configurar google_inventory_sync_token en config/config.php.');
            }

            $resultadoBidireccional = sincronizarInventarioBidireccional($pdo, $scriptUrl, $token);
        } else {
            $csvUrl = trim((string) ($config['inventario_csv_url'] ?? ''));
            if ($csvUrl === '') {
                throw new RuntimeException('Falta la clave inventario_csv_url en config/config.php.');
            }

            $resultadoSincronizacion = sincronizarInventarioDesdeCsv($pdo, $csvUrl);
        }
    }

    $registros = consultarInventario($pdo, $filtros, $ordenar, $direccion);
} catch (Throwable $e) {
    $mensajeError = trim($e->getMessage());
    $errorCarga = $mensajeError !== '' ? $mensajeError : 'No se pudo cargar el inventario.';
}

renderAppLayoutStart(
    'Inventario - Consulta',
    'inventario_consulta',
    'Inventario - Consulta',
    'Consulta de stock actual con filtros y sincronización conservadora con Google Sheets'
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
                        <label class="form-label" for="codigo_centro">Código centro</label>
                        <input class="form-control" id="codigo_centro" name="codigo_centro" type="text" value="<?= htmlspecialchars($filtros['codigo_centro'], ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-12 col-md-6 col-xl-3">
                        <label class="form-label" for="destino">Destino</label>
                        <input class="form-control" id="destino" name="destino" type="text" value="<?= htmlspecialchars($filtros['destino'], ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-12 d-flex flex-wrap gap-2">
                        <button class="btn btn-primary mt-0" type="submit">Filtrar</button>
                        <a class="btn btn-outline-secondary" href="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/inventario_consulta.php">Limpiar filtros</a>
                    </div>
                </form>
            </div>
        </div>
        <div class="card border-0 shadow-sm sync-card">
            <div class="card-body">
                <p class="eyebrow">Sincronización</p>
                <div class="d-grid gap-2">
                    <form method="POST" action="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/inventario_consulta.php">
                        <input type="hidden" name="sync_action" value="csv">
                        <button class="btn btn-primary mt-0 w-100" type="submit">Sincronizar desde Google Sheets</button>
                    </form>
                    <form method="POST" action="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/inventario_consulta.php">
                        <input type="hidden" name="sync_action" value="bidireccional">
                        <button class="btn btn-outline-primary w-100" type="submit">Sincronizar bidireccional</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <?php if ($errorCarga !== ''): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($errorCarga, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <?php if ($resultadoSincronizacion !== null): ?>
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <p class="eyebrow">Resultado de sincronización CSV</p>
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

    <?php if ($resultadoBidireccional !== null): ?>
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <p class="eyebrow">Resultado de sincronización bidireccional</p>
                <?php
                $hayCambiosBidireccional =
                    (int) ($resultadoBidireccional['insertados_en_sql'] ?? 0) > 0
                    || (int) ($resultadoBidireccional['insertados_en_sheet'] ?? 0) > 0
                    || (int) ($resultadoBidireccional['actualizados_en_sql'] ?? 0) > 0;
                ?>
                <div class="alert <?= $hayCambiosBidireccional ? 'alert-success' : 'alert-light border' ?> mb-3">
                    <?php if ($hayCambiosBidireccional): ?>
                        Inventario sincronizado correctamente. Se han aplicado inserciones o actualizaciones.
                    <?php else: ?>
                        Inventario sincronizado correctamente. No hay cambios.
                    <?php endif; ?>
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-6 col-xl-2"><strong>Total en SQL:</strong> <?= htmlspecialchars((string) $resultadoBidireccional['total_sql'], ENT_QUOTES, 'UTF-8') ?></div>
                    <div class="col-6 col-xl-2"><strong>Total en Sheets:</strong> <?= htmlspecialchars((string) $resultadoBidireccional['total_sheet'], ENT_QUOTES, 'UTF-8') ?></div>
                    <div class="col-6 col-xl-3"><strong>Insertados en SQL:</strong> <?= htmlspecialchars((string) $resultadoBidireccional['insertados_en_sql'], ENT_QUOTES, 'UTF-8') ?></div>
                    <div class="col-6 col-xl-3"><strong>Insertados en Sheets:</strong> <?= htmlspecialchars((string) $resultadoBidireccional['insertados_en_sheet'], ENT_QUOTES, 'UTF-8') ?></div>
                    <div class="col-6 col-xl-2"><strong>Actualizados en SQL:</strong> <?= htmlspecialchars((string) ($resultadoBidireccional['actualizados_en_sql'] ?? 0), ENT_QUOTES, 'UTF-8') ?></div>
                    <div class="col-6 col-xl-2"><strong>Cambios detectados:</strong> <?= htmlspecialchars((string) ($resultadoBidireccional['cambios_detectados'] ?? 0), ENT_QUOTES, 'UTF-8') ?></div>
                    <div class="col-12 col-xl-2"><strong>Coincidencias:</strong> <?= htmlspecialchars((string) $resultadoBidireccional['coincidencias'], ENT_QUOTES, 'UTF-8') ?></div>
                </div>
                <?php if (($resultadoBidireccional['errores'] ?? []) !== []): ?>
                    <div class="alert alert-warning mb-0">
                        <?php foreach ($resultadoBidireccional['errores'] as $detalleError): ?>
                            <div><?= htmlspecialchars((string) $detalleError, ENT_QUOTES, 'UTF-8') ?></div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <p class="eyebrow">Depuración temporal</p>
                <div class="row g-3 mb-3">
                    <div class="col-6 col-xl-2"><strong>Total SQL:</strong> <?= htmlspecialchars((string) ($resultadoBidireccional['total_sql'] ?? 0), ENT_QUOTES, 'UTF-8') ?></div>
                    <div class="col-6 col-xl-2"><strong>Total Sheets:</strong> <?= htmlspecialchars((string) ($resultadoBidireccional['total_sheet'] ?? 0), ENT_QUOTES, 'UTF-8') ?></div>
                    <div class="col-6 col-xl-2"><strong>Insertados SQL:</strong> <?= htmlspecialchars((string) ($resultadoBidireccional['insertados_en_sql'] ?? 0), ENT_QUOTES, 'UTF-8') ?></div>
                    <div class="col-6 col-xl-3"><strong>Insertados Sheets:</strong> <?= htmlspecialchars((string) ($resultadoBidireccional['insertados_en_sheet'] ?? 0), ENT_QUOTES, 'UTF-8') ?></div>
                    <div class="col-12 col-xl-3"><strong>Coincidencias:</strong> <?= htmlspecialchars((string) ($resultadoBidireccional['coincidencias'] ?? 0), ENT_QUOTES, 'UTF-8') ?></div>
                </div>

                <div class="row g-3">
                    <div class="col-12 col-xl-6">
                        <div class="border rounded-3 p-3 bg-light h-100">
                            <h3 class="h6 mb-2">IDs para insertar en Sheets</h3>
                            <pre class="debug-pre mb-0"><?= htmlspecialchars(json_encode($resultadoBidireccional['ids_para_insertar_en_sheet'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8') ?></pre>
                        </div>
                    </div>
                    <div class="col-12 col-xl-6">
                        <div class="border rounded-3 p-3 bg-light h-100">
                            <h3 class="h6 mb-2">IDs para insertar en SQL</h3>
                            <pre class="debug-pre mb-0"><?= htmlspecialchars(json_encode($resultadoBidireccional['ids_para_insertar_en_sql'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8') ?></pre>
                        </div>
                    </div>
                    <div class="col-12 col-xl-6">
                        <div class="border rounded-3 p-3 bg-light h-100">
                            <h3 class="h6 mb-2">IDs para actualizar en SQL</h3>
                            <pre class="debug-pre mb-0"><?= htmlspecialchars(json_encode($resultadoBidireccional['ids_para_actualizar_en_sql'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8') ?></pre>
                        </div>
                    </div>
                    <div class="col-12 col-xl-6">
                        <div class="border rounded-3 p-3 bg-light h-100">
                            <h3 class="h6 mb-2">IDs duplicados en SQL</h3>
                            <pre class="debug-pre mb-0"><?= htmlspecialchars(json_encode($resultadoBidireccional['ids_duplicados_sql'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8') ?></pre>
                        </div>
                    </div>
                    <div class="col-12 col-xl-6">
                        <div class="border rounded-3 p-3 bg-light h-100">
                            <h3 class="h6 mb-2">IDs duplicados en Sheets</h3>
                            <pre class="debug-pre mb-0"><?= htmlspecialchars(json_encode($resultadoBidireccional['ids_duplicados_sheet'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8') ?></pre>
                        </div>
                    </div>
                    <div class="col-12 col-xl-6">
                        <div class="border rounded-3 p-3 bg-light h-100">
                            <h3 class="h6 mb-2">IDs ignorados por existir ya en SQL</h3>
                            <pre class="debug-pre mb-0"><?= htmlspecialchars(json_encode($resultadoBidireccional['ids_ignorados_por_existir'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8') ?></pre>
                        </div>
                    </div>
                    <div class="col-12 col-xl-6">
                        <div class="border rounded-3 p-3 bg-light h-100">
                            <h3 class="h6 mb-2">Respuesta get_inventory</h3>
                            <pre class="debug-pre mb-0"><?= htmlspecialchars(json_encode($resultadoBidireccional['respuesta_get_inventory'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8') ?></pre>
                        </div>
                    </div>
                    <div class="col-12 col-xl-6">
                        <div class="border rounded-3 p-3 bg-light h-100">
                            <h3 class="h6 mb-2">Respuesta append_inventory_rows</h3>
                            <pre class="debug-pre mb-0"><?= htmlspecialchars(json_encode($resultadoBidireccional['respuesta_append_inventory_rows'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8') ?></pre>
                        </div>
                    </div>
                    <div class="col-12 col-xl-6">
                        <div class="border rounded-3 p-3 bg-light h-100">
                            <h3 class="h6 mb-2">Primeros IDs SQL</h3>
                            <pre class="debug-pre mb-0"><?= htmlspecialchars(json_encode($resultadoBidireccional['ids_sql'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8') ?></pre>
                        </div>
                    </div>
                    <div class="col-12 col-xl-6">
                        <div class="border rounded-3 p-3 bg-light h-100">
                            <h3 class="h6 mb-2">Primeros IDs Sheets</h3>
                            <pre class="debug-pre mb-0"><?= htmlspecialchars(json_encode($resultadoBidireccional['ids_sheet'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8') ?></pre>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="border rounded-3 p-3 bg-light mb-3">
                            <h3 class="h6 mb-2">Diferencias detectadas</h3>
                            <pre class="debug-pre mb-0"><?= htmlspecialchars(json_encode($resultadoBidireccional['diferencias'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8') ?></pre>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="border rounded-3 p-3 bg-light">
                            <h3 class="h6 mb-2">Errores</h3>
                            <pre class="debug-pre mb-0"><?= htmlspecialchars(json_encode($resultadoBidireccional['errores'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8') ?></pre>
                        </div>
                    </div>
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
