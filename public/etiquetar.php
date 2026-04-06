<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/conexion.php';
require_once dirname(__DIR__) . '/app/auth.php';
require_once dirname(__DIR__) . '/app/layout.php';
require_once dirname(__DIR__) . '/app/inventario.php';
require_once dirname(__DIR__) . '/app/albaranes.php';

require_login();

function leerIdsEtiquetasDesdeRequest(array $source): array
{
    $ids = $source['seleccionados'] ?? ($source['id'] ?? []);

    if (!is_array($ids)) {
        $ids = [$ids];
    }

    $ids = array_map(static fn(mixed $valor): int => (int) $valor, $ids);
    $ids = array_values(array_filter($ids, static fn(int $valor): bool => $valor > 0));

    return array_values(array_unique($ids));
}

$filtros = leerFiltrosInventarioDesdeRequest($_GET);
[$ordenar, $direccion] = leerOrdenInventarioDesdeRequest($_GET);
$registros = [];
$errorCarga = '';
$columnasTabla = columnasInventarioTabla();
$seleccionadosIds = leerIdsEtiquetasDesdeRequest($_GET);

try {
    $pdo = conectar();
    $registros = consultarInventario($pdo, $filtros, $ordenar, $direccion);
} catch (Throwable $e) {
    $mensajeError = trim($e->getMessage());
    $errorCarga = $mensajeError !== '' ? $mensajeError : 'No se pudo cargar la mercancia para etiquetar.';
}

renderAppLayoutStart(
    'Inventario - Etiquetar',
    'etiquetar',
    'Etiquetar mercancia',
    'Seleccion de lineas activas para generar etiquetas logisticas en PDF'
);
?>
<section class="panel panel-card">
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" action="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/etiquetar.php" class="row g-3 align-items-end">
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
                <?php foreach ($seleccionadosIds as $idSeleccionado): ?>
                    <input type="hidden" name="seleccionados[]" value="<?= htmlspecialchars((string) $idSeleccionado, ENT_QUOTES, 'UTF-8') ?>">
                <?php endforeach; ?>
                <div class="col-12 d-flex flex-wrap gap-2">
                    <button class="btn btn-primary mt-0" type="submit">Filtrar</button>
                    <a class="btn btn-outline-secondary" href="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/etiquetar.php">Limpiar filtros</a>
                </div>
            </form>
        </div>
    </div>

    <?php if ($errorCarga !== ''): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($errorCarga, ENT_QUOTES, 'UTF-8') ?></div>
    <?php elseif ($registros === []): ?>
        <div class="alert alert-light border mb-0">No se han encontrado lineas activas con los filtros indicados.</div>
    <?php else: ?>
        <form method="GET" action="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/etiquetar_pdf.php" target="_blank" rel="noopener">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
                    <div>
                        <p class="eyebrow mb-1">Generacion directa</p>
                        <p class="mb-0 text-body-secondary">Cada registro seleccionado genera una hoja A4 con 2 etiquetas DIN A5 identicas.</p>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <button class="btn btn-outline-secondary" type="button" id="seleccionar_todo">Seleccionar visibles</button>
                        <button class="btn btn-outline-secondary" type="button" id="limpiar_seleccion">Limpiar seleccion</button>
                        <button class="btn btn-primary" type="submit">Generar etiquetas</button>
                    </div>
                </div>
            </div>

            <div class="table-responsive custom-table-wrap">
                <table class="table table-hover align-middle mb-0 data-table">
                    <thead>
                        <tr>
                            <th scope="col" style="width: 56px;">Sel.</th>
                            <?php foreach ($columnasTabla as $columna => $titulo): ?>
                                <?php
                                $parametrosOrden = array_merge($filtros, ['ordenar' => $columna]);
                                $parametrosOrden['direccion'] = $columna === $ordenar && $direccion === 'ASC' ? 'DESC' : 'ASC';
                                foreach ($seleccionadosIds as $idSeleccionado) {
                                    $parametrosOrden['seleccionados'][] = $idSeleccionado;
                                }
                                $urlOrden = BASE_URL . '/etiquetar.php?' . http_build_query($parametrosOrden);
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
                            <?php $filaId = (int) ($fila['id'] ?? 0); ?>
                            <tr>
                                <td>
                                    <input class="form-check-input etiqueta-checkbox" type="checkbox" name="seleccionados[]" value="<?= htmlspecialchars((string) $filaId, ENT_QUOTES, 'UTF-8') ?>"<?= in_array($filaId, $seleccionadosIds, true) ? ' checked' : '' ?>>
                                </td>
                                <?php foreach (array_keys($columnasTabla) as $columna): ?>
                                    <?php $valor = $fila[$columna] ?? ''; ?>
                                    <td><?= htmlspecialchars((string) ($valor !== null && $valor !== '' ? $valor : '-'), ENT_QUOTES, 'UTF-8') ?></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </form>
    <?php endif; ?>
</section>

<script>
const botonSeleccionarTodo = document.getElementById('seleccionar_todo');
const botonLimpiarSeleccion = document.getElementById('limpiar_seleccion');
const checkboxesEtiquetas = Array.from(document.querySelectorAll('.etiqueta-checkbox'));

if (botonSeleccionarTodo) {
    botonSeleccionarTodo.addEventListener('click', () => {
        checkboxesEtiquetas.forEach((checkbox) => {
            checkbox.checked = true;
        });
    });
}

if (botonLimpiarSeleccion) {
    botonLimpiarSeleccion.addEventListener('click', () => {
        checkboxesEtiquetas.forEach((checkbox) => {
            checkbox.checked = false;
        });
    });
}
</script>
<?php renderAppLayoutEnd(); ?>
