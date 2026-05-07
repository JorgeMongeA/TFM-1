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
$puedeAdministrarCentros = puedeEditarCentros();

try {
    $pdo = conectar();

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        if (!$puedeAdministrarCentros) {
            renderizarAccesoDenegado('No tienes permisos para sincronizar centros.');
        }

        $config = cargarConfiguracion();
        $scriptUrl = obtenerUrlSyncCentrosGoogleSheets($config);

        if ($scriptUrl === '') {
            throw new RuntimeException('Falta la URL de sincronizacion de centros.');
        }

        $resultadoSincronizacion = sincronizarCentrosDesdeAppsScript($pdo, $scriptUrl, obtenerTokenSyncCentrosGoogleSheets());
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
                <form method="GET" action="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/centros.php" class="row g-3 align-items-end" autocomplete="off">
                    <div class="col-12 col-md-6 col-xl-2">
                        <label class="form-label" for="codigo_centro">Código centro</label>
                        <input class="form-control" id="codigo_centro" name="codigo_centro" type="text" autocomplete="off" value="<?= htmlspecialchars($filtros['codigo_centro'], ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-12 col-md-6 col-xl-3">
                        <label class="form-label" for="nombre_centro">Nombre centro</label>
                        <input class="form-control" id="nombre_centro" name="nombre_centro" type="text" autocomplete="off" value="<?= htmlspecialchars($filtros['nombre_centro'], ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-12 col-md-6 col-xl-2">
                        <label class="form-label" for="ciudad">Localidad</label>
                        <input class="form-control" id="ciudad" name="ciudad" type="text" autocomplete="off" value="<?= htmlspecialchars($filtros['ciudad'], ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-12 col-md-6 col-xl-3">
                        <label class="form-label" for="congregacion">Congregación</label>
                        <input class="form-control" id="congregacion" name="congregacion" type="text" autocomplete="off" value="<?= htmlspecialchars($filtros['congregacion'], ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-12 col-md-6 col-xl-2">
                        <label class="form-label" for="destino">Destino</label>
                        <input class="form-control" id="destino" name="destino" type="text" autocomplete="off" value="<?= htmlspecialchars($filtros['destino'], ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-12 d-flex flex-wrap gap-2">
                        <button class="btn btn-primary mt-0" type="submit">Filtrar</button>
                        <a class="btn btn-outline-secondary" href="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/centros.php">Limpiar filtros</a>
                    </div>
                </form>
            </div>
        </div>
        <?php if ($puedeAdministrarCentros): ?>
        <div class="card border-0 shadow-sm sync-card">
            <div class="card-body">
                <p class="eyebrow">Sincronización</p>
                <form method="POST" action="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/centros.php">
                    <button class="btn btn-primary mt-0 w-100" type="submit">Sincronizar centros</button>
                </form>
            </div>
        </div>
        <?php endif; ?>
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
                    <div class="col-6 col-xl-3"><strong>Eliminados:</strong> <?= htmlspecialchars((string) ($resultadoSincronizacion['eliminados'] ?? 0), ENT_QUOTES, 'UTF-8') ?></div>
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
        <div class="centros-scroll-box centros-table-scroll">
            <div class="centros-scroll-inner">
                <table class="table table-hover align-middle mb-0 data-table tabla-centros centros-table" id="tabla-centros">
                    <thead>
                        <tr>
                            <?php foreach ($columnasTabla as $columna => $titulo): ?>
                                <th scope="col" class="centros-sortable" data-columna="<?= htmlspecialchars((string) $columna, ENT_QUOTES, 'UTF-8') ?>" tabindex="0" role="button" aria-sort="none">
                                    <?= htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8') ?>
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
        </div>
    <?php endif; ?>
</section>
<script>
(() => {
    const tabla = document.getElementById('tabla-centros');
    if (!tabla) {
        return;
    }

    const tbody = tabla.tBodies[0];
    if (!tbody) {
        return;
    }

    const headers = Array.from(tabla.querySelectorAll('th.centros-sortable'));
    const collator = new Intl.Collator('es', { numeric: true, sensitivity: 'base' });

    const valorCelda = (fila, indice) => {
        const celda = fila.cells[indice];
        if (!celda) {
            return '';
        }
        return (celda.textContent || '').trim();
    };

    const esNumero = (texto) => /^-?\d+(?:[.,]\d+)?$/.test(texto);

    const comparar = (a, b) => {
        if (esNumero(a) && esNumero(b)) {
            const numeroA = Number(a.replace(',', '.'));
            const numeroB = Number(b.replace(',', '.'));
            if (Number.isFinite(numeroA) && Number.isFinite(numeroB)) {
                if (numeroA < numeroB) return -1;
                if (numeroA > numeroB) return 1;
                return 0;
            }
        }
        return collator.compare(a, b);
    };

    headers.forEach((header, indice) => {
        const ordenar = () => {
            const direccionActual = header.getAttribute('data-direccion') === 'asc' ? 'asc' : 'desc';
            const siguienteDireccion = direccionActual === 'asc' ? 'desc' : 'asc';

            headers.forEach((h) => {
                h.classList.remove('is-sorted-asc', 'is-sorted-desc');
                h.removeAttribute('data-direccion');
                h.setAttribute('aria-sort', 'none');
            });

            header.setAttribute('data-direccion', siguienteDireccion);
            header.classList.add(siguienteDireccion === 'asc' ? 'is-sorted-asc' : 'is-sorted-desc');
            header.setAttribute('aria-sort', siguienteDireccion === 'asc' ? 'ascending' : 'descending');

            const filas = Array.from(tbody.rows);
            filas.sort((filaA, filaB) => {
                const valorA = valorCelda(filaA, indice);
                const valorB = valorCelda(filaB, indice);
                const resultado = comparar(valorA, valorB);
                return siguienteDireccion === 'asc' ? resultado : -resultado;
            });

            const fragmento = document.createDocumentFragment();
            filas.forEach((fila) => fragmento.appendChild(fila));
            tbody.appendChild(fragmento);
        };

        header.addEventListener('click', ordenar);
        header.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                ordenar();
            }
        });
    });
})();
</script>
<?php renderAppLayoutEnd(); ?>
