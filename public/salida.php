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
require_once dirname(__DIR__) . '/app/inventario.php';
require_once dirname(__DIR__) . '/app/albaranes.php';
require_once dirname(__DIR__) . '/app/layout.php';

require_login();
requierePermiso(PERMISO_INVENTARIO_SALIDA);

function construirEtiquetaCentroSalida(array $centro): string
{
    $nombre = trim((string) ($centro['nombre_centro'] ?? ''));
    $codigo = trim((string) ($centro['codigo_centro'] ?? ''));

    if ($nombre === '') {
        return $codigo;
    }

    return $nombre . ' (' . $codigo . ')';
}

$centros = [];
$centrosPorCodigo = [];
$centrosPorEtiqueta = [];
$mercancia = [];
$mercanciaSeleccionada = [];
$error = '';
$avisoSeleccion = '';
$centroCodigo = trim((string) ($_GET['codigo_centro'] ?? ''));
$centroSelector = trim((string) ($_GET['centro_selector'] ?? ''));
$seleccionadosIds = leerIdsSeleccionadosDesdeRequest($_GET);

try {
    $pdo = conectar();
    asegurarCentroDesconocido($pdo);
    $centros = cargarCentrosParaSelector($pdo);

    foreach ($centros as $centro) {
        $codigo = trim((string) ($centro['codigo_centro'] ?? ''));
        if ($codigo === '') {
            continue;
        }

        $centrosPorCodigo[$codigo] = $centro;
        $centrosPorEtiqueta[construirEtiquetaCentroSalida($centro)] = $centro;
    }

    if ($centroCodigo === '' && $centroSelector !== '' && isset($centrosPorEtiqueta[$centroSelector])) {
        $centroCodigo = (string) ($centrosPorEtiqueta[$centroSelector]['codigo_centro'] ?? '');
    }

    if ($centroCodigo !== '' && isset($centrosPorCodigo[$centroCodigo])) {
        $centroSelector = construirEtiquetaCentroSalida($centrosPorCodigo[$centroCodigo]);
        $mercancia = consultarInventarioPorCentros($pdo, [$centroCodigo], INVENTARIO_ESTADO_ACTIVO);
    }

    if ($seleccionadosIds !== []) {
        $mercanciaSeleccionada = consultarInventarioPorIds($pdo, $seleccionadosIds, INVENTARIO_ESTADO_ACTIVO);
        $seleccionadosActivos = array_map(static fn(array $fila): int => (int) ($fila['id'] ?? 0), $mercanciaSeleccionada);

        if (count($seleccionadosActivos) !== count($seleccionadosIds)) {
            $avisoSeleccion = 'Algunas lineas ya no estaban activas y se han retirado de la seleccion.';
        }

        $seleccionadosIds = $seleccionadosActivos;
    }
} catch (Throwable $e) {
    $mensajeError = trim($e->getMessage());
    $error = $mensajeError !== '' ? $mensajeError : 'No se pudieron cargar los datos de salida.';
}

$columnasTabla = columnasInventarioTabla();
$centrosJson = [];

foreach ($centros as $centro) {
    $centrosJson[] = [
        'label' => construirEtiquetaCentroSalida($centro),
        'codigo' => (string) ($centro['codigo_centro'] ?? ''),
        'nombre' => (string) ($centro['nombre_centro'] ?? ''),
    ];
}

renderAppLayoutStart(
    'Inventario - Salida',
    'salida',
    'Inventario - Salida',
    'Seleccion visual de mercancia para preparar la salida'
);
?>
<section class="panel panel-card">
    <?php if ($error !== ''): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <?php if ($avisoSeleccion !== ''): ?>
        <div class="alert alert-warning"><?= htmlspecialchars($avisoSeleccion, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" action="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/salida.php" class="row g-3 align-items-end" autocomplete="off">
                <div class="col-12 col-lg-8">
                    <label class="form-label" for="centro_selector">Colegio / centro</label>
                    <input class="form-control" id="centro_selector" name="centro_selector" type="text" list="centros_salida" value="<?= htmlspecialchars($centroSelector, ENT_QUOTES, 'UTF-8') ?>" placeholder="Escribe para buscar un centro">
                    <datalist id="centros_salida">
                        <?php foreach ($centros as $centro): ?>
                            <option value="<?= htmlspecialchars(construirEtiquetaCentroSalida($centro), ENT_QUOTES, 'UTF-8') ?>"></option>
                        <?php endforeach; ?>
                    </datalist>
                    <input id="codigo_centro" name="codigo_centro" type="hidden" value="<?= htmlspecialchars($centroCodigo, ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="col-12 col-lg-4 d-flex flex-wrap gap-2">
                    <?php foreach ($seleccionadosIds as $idSeleccionado): ?>
                        <input type="hidden" name="seleccionados[]" value="<?= htmlspecialchars((string) $idSeleccionado, ENT_QUOTES, 'UTF-8') ?>">
                    <?php endforeach; ?>
                    <button class="btn btn-primary mt-0" type="submit">Buscar mercancia</button>
                    <a class="btn btn-outline-secondary" href="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/salida.php">Limpiar</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-3">
                <div>
                    <p class="eyebrow mb-1">Mercancia disponible</p>
                    <p class="mb-0 text-body-secondary">
                        <?= $centroCodigo !== '' ? 'Centro seleccionado: ' . htmlspecialchars($centroSelector, ENT_QUOTES, 'UTF-8') : 'Selecciona un centro para ver la mercancia asociada.' ?>
                    </p>
                </div>
            </div>

            <?php if ($centroCodigo === ''): ?>
                <div class="alert alert-light border mb-0">Busca un centro para cargar la mercancia disponible.</div>
            <?php elseif ($mercancia === []): ?>
                <div class="alert alert-warning mb-0">No hay mercancia activa asociada al centro seleccionado.</div>
            <?php else: ?>
                <div class="table-responsive custom-table-wrap">
                    <table class="table table-hover align-middle mb-0 data-table">
                        <thead>
                            <tr>
                                <?php foreach ($columnasTabla as $titulo): ?>
                                    <th scope="col"><?= htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8') ?></th>
                                <?php endforeach; ?>
                                <th scope="col">Accion</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($mercancia as $fila): ?>
                                <?php $filaId = (int) ($fila['id'] ?? 0); ?>
                                <tr>
                                    <?php foreach (array_keys($columnasTabla) as $columna): ?>
                                        <?php $valor = $fila[$columna] ?? ''; ?>
                                        <td><?= htmlspecialchars((string) ($valor !== null && $valor !== '' ? $valor : '-'), ENT_QUOTES, 'UTF-8') ?></td>
                                    <?php endforeach; ?>
                                    <td>
                                        <?php if (in_array($filaId, $seleccionadosIds, true)): ?>
                                            <span class="badge text-bg-success">Anadida</span>
                                        <?php else: ?>
                                            <form method="GET" action="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/salida.php">
                                                <input type="hidden" name="centro_selector" value="<?= htmlspecialchars($centroSelector, ENT_QUOTES, 'UTF-8') ?>">
                                                <input type="hidden" name="codigo_centro" value="<?= htmlspecialchars($centroCodigo, ENT_QUOTES, 'UTF-8') ?>">
                                                <?php foreach ($seleccionadosIds as $idSeleccionado): ?>
                                                    <input type="hidden" name="seleccionados[]" value="<?= htmlspecialchars((string) $idSeleccionado, ENT_QUOTES, 'UTF-8') ?>">
                                                <?php endforeach; ?>
                                                <input type="hidden" name="seleccionados[]" value="<?= htmlspecialchars((string) $filaId, ENT_QUOTES, 'UTF-8') ?>">
                                                <button class="btn btn-sm btn-outline-primary mt-0" type="submit">Anadir</button>
                                            </form>
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

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-3">
                <div>
                    <p class="eyebrow mb-1">Mercancia seleccionada para salida</p>
                    <p class="mb-0 text-body-secondary">Revisa los articulos anadidos antes de pasar a la confirmacion del albaran.</p>
                </div>
                <form method="GET" action="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/albaran.php" class="d-inline-flex">
                    <input type="hidden" name="tipo" value="salida">
                    <?php foreach ($seleccionadosIds as $idSeleccionado): ?>
                        <input type="hidden" name="seleccionados[]" value="<?= htmlspecialchars((string) $idSeleccionado, ENT_QUOTES, 'UTF-8') ?>">
                    <?php endforeach; ?>
                    <button class="btn btn-outline-primary" type="submit"<?= $mercanciaSeleccionada === [] ? ' disabled' : '' ?>>Revisar albaran</button>
                </form>
            </div>

            <?php if ($mercanciaSeleccionada === []): ?>
                <div class="alert alert-light border mb-0">Todavia no hay mercancia anadida a la seleccion.</div>
            <?php else: ?>
                <div class="table-responsive custom-table-wrap">
                    <table class="table table-hover align-middle mb-0 data-table">
                        <thead>
                            <tr>
                                <?php foreach ($columnasTabla as $titulo): ?>
                                    <th scope="col"><?= htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8') ?></th>
                                <?php endforeach; ?>
                                <th scope="col">Accion</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($mercanciaSeleccionada as $fila): ?>
                                <?php $filaId = (int) ($fila['id'] ?? 0); ?>
                                <tr>
                                    <?php foreach (array_keys($columnasTabla) as $columna): ?>
                                        <?php $valor = $fila[$columna] ?? ''; ?>
                                        <td><?= htmlspecialchars((string) ($valor !== null && $valor !== '' ? $valor : '-'), ENT_QUOTES, 'UTF-8') ?></td>
                                    <?php endforeach; ?>
                                    <td>
                                        <form method="GET" action="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/salida.php">
                                            <input type="hidden" name="centro_selector" value="<?= htmlspecialchars($centroSelector, ENT_QUOTES, 'UTF-8') ?>">
                                            <input type="hidden" name="codigo_centro" value="<?= htmlspecialchars($centroCodigo, ENT_QUOTES, 'UTF-8') ?>">
                                            <?php foreach ($seleccionadosIds as $idSeleccionado): ?>
                                                <?php if ($idSeleccionado !== $filaId): ?>
                                                    <input type="hidden" name="seleccionados[]" value="<?= htmlspecialchars((string) $idSeleccionado, ENT_QUOTES, 'UTF-8') ?>">
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                            <button class="btn btn-sm btn-outline-danger mt-0" type="submit">Quitar</button>
                                        </form>
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

<script>
const centrosSalida = <?= json_encode($centrosJson, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const centroSalidaInput = document.getElementById('centro_selector');
const codigoCentroInput = document.getElementById('codigo_centro');

function sincronizarCentroSalida(valor) {
    const centro = centrosSalida.find((item) => item.label === valor);
    codigoCentroInput.value = centro ? centro.codigo : '';
}

if (centroSalidaInput) {
    sincronizarCentroSalida(centroSalidaInput.value);
    centroSalidaInput.addEventListener('input', (event) => {
        sincronizarCentroSalida(event.target.value);
    });
    centroSalidaInput.addEventListener('change', (event) => {
        sincronizarCentroSalida(event.target.value);
    });
}
</script>
<?php renderAppLayoutEnd(); ?>
