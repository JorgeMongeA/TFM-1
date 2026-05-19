<?php

/**
 * Autor: Jorge Monge
 * Trabajo Final de Master (TFM)
 * UOC - 2026
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/conexion.php';
require_once dirname(__DIR__) . '/app/auth.php';
require_once dirname(__DIR__) . '/app/layout.php';
require_once dirname(__DIR__) . '/app/centros.php';
require_once dirname(__DIR__) . '/app/inventario.php';

require_login();
requierePermiso(PERMISO_INVENTARIO_EDICION);

const DESTINOS_PERMITIDOS = ['EDV', 'EPL'];

function obtenerSiguienteIdInventario(PDO $pdo): string
{
    $stmt = $pdo->query('SELECT MAX(id) + 1 AS siguiente FROM inventario');
    $siguiente = $stmt->fetchColumn();

    if ($siguiente === false || $siguiente === null) {
        return '1';
    }

    return (string) max(1, (int) $siguiente);
}

function construirEtiquetaCentro(array $centro): string
{
    return construirEtiquetaCentroBusqueda($centro);
}

$pdo = null;
$siguienteId = '';
$error = '';
$maxUbicaciones = maximoUbicacionesEntradaInventario();

try {
    $pdo = conectar();
    asegurarCentroDesconocido($pdo);
    $siguienteId = obtenerSiguienteIdInventario($pdo);
} catch (Throwable $e) {
    $siguienteId = '';
    $error = 'No se pudo cargar el siguiente ID disponible.';
}

$datos = [
    'id' => $siguienteId,
    'centro_selector' => '',
    'editorial' => '',
    'colegio' => '',
    'codigo_centro' => '',
    'fecha_entrada' => '',
    'bultos' => '',
    'destino' => '',
    'orden' => '',
];
$lineasUbicacion = datosBaseUbicacionesEntradaInventario($maxUbicaciones);

$required = ['editorial', 'colegio', 'codigo_centro', 'fecha_entrada'];
$labels = [
    'editorial' => 'Editorial',
    'colegio' => 'Centro',
    'codigo_centro' => 'Codigo centro',
    'fecha_entrada' => 'Fecha entrada',
];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    foreach ($datos as $key => $value) {
        $datos[$key] = trim((string) ($_POST[$key] ?? ''));
    }
    $lineasUbicacion = leerLineasUbicacionEntradaInventario($_POST, $maxUbicaciones);

    try {
        if (!$pdo instanceof PDO) {
            $pdo = conectar();
            asegurarCentroDesconocido($pdo);
        }

        if ($datos['id'] === '') {
            $datos['id'] = obtenerSiguienteIdInventario($pdo);
        }
    } catch (Throwable $e) {
        $error = 'No se pudo calcular el siguiente ID disponible.';
    }

    if ($error === '') {
        $centroSeleccionado = null;

        if ($datos['codigo_centro'] !== '') {
            $centroSeleccionado = buscarCentroPorCodigo($pdo, $datos['codigo_centro']);
        }

        $centroSelectorNormalizado = strtoupper($datos['centro_selector']);
        if ($centroSeleccionado === null && (
            strtoupper($datos['centro_selector']) === CENTRO_DESCONOCIDO_NOMBRE
            || $centroSelectorNormalizado === CENTRO_DESCONOCIDO_CODIGO . ' - ' . CENTRO_DESCONOCIDO_NOMBRE
            || (str_contains($centroSelectorNormalizado, CENTRO_DESCONOCIDO_NOMBRE) && str_contains($centroSelectorNormalizado, CENTRO_DESCONOCIDO_CODIGO))
            || strtoupper($datos['colegio']) === CENTRO_DESCONOCIDO_NOMBRE
            || $datos['codigo_centro'] === CENTRO_DESCONOCIDO_CODIGO
        )) {
            $centroSeleccionado = [
                'codigo_centro' => CENTRO_DESCONOCIDO_CODIGO,
                'nombre_centro' => CENTRO_DESCONOCIDO_NOMBRE,
                'destino' => '',
            ];
        }

        if ($centroSeleccionado === null) {
            $error = 'Selecciona un centro valido de la lista.';
        } else {
            $datos['colegio'] = (string) ($centroSeleccionado['nombre_centro'] ?? '');
            $datos['codigo_centro'] = (string) ($centroSeleccionado['codigo_centro'] ?? '');
            $datos['destino'] = normalizarDestinoCentro($centroSeleccionado['destino'] ?? '');
            $datos['centro_selector'] = construirEtiquetaCentro($centroSeleccionado);
        }
    }

    $faltantes = [];
    if ($error === '') {
        foreach ($required as $key) {
            if ($datos[$key] === '') {
                $faltantes[] = $key;
            }
        }

        if ($faltantes !== []) {
            $campos = array_map(static fn(string $key): string => $labels[$key] ?? $key, $faltantes);
            $error = 'Completa los campos obligatorios: ' . implode(', ', $campos) . '.';
        }
    }

    if ($error === '') {
        $idValidado = filter_var($datos['id'], FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        if ($idValidado === false) {
            $error = 'El ID debe ser un numero entero mayor que cero.';
        }
    }

    if ($error === '' && !in_array($datos['destino'], array_merge([''], DESTINOS_PERMITIDOS), true)) {
        $error = 'El destino seleccionado no es valido.';
    }

    if ($error === '') {
        try {
            if (!$pdo instanceof PDO) {
                $pdo = conectar();
            }

            $resultadoUbicaciones = validarLineasUbicacionEntradaInventario($lineasUbicacion, $datos['bultos']);
            $lineasValidadas = $resultadoUbicaciones['lineas'];
            $datos['bultos'] = (string) ($resultadoUbicaciones['total_bultos'] ?? '');

            $resultadoGuardado = crearEntradaInventarioPorUbicaciones($pdo, $datos, $lineasValidadas);
            header('Location: ' . BASE_URL . '/entrada.php?' . http_build_query([
                'guardado' => 1,
                'id' => (string) ($resultadoGuardado['id_inicial'] ?? $datos['id']),
                'ids' => implode(',', array_map('strval', $resultadoGuardado['ids'] ?? [])),
                'total_lineas' => (int) ($resultadoGuardado['total_lineas'] ?? count($lineasValidadas)),
            ]));
            exit;
        } catch (Throwable $e) {
            $mensajeError = trim($e->getMessage());
            $error = $mensajeError !== '' ? $mensajeError : 'No se pudo guardar la entrada. Revisa los datos e intentalo de nuevo.';
        }
    }
}

$guardado = (string) ($_GET['guardado'] ?? '') === '1';
$idGuardado = trim((string) ($_GET['id'] ?? ''));
$idsGuardados = array_values(array_filter(
    array_map(static fn(string $valor): int => (int) trim($valor), explode(',', (string) ($_GET['ids'] ?? ''))),
    static fn(int $valor): bool => $valor > 0
));
$totalLineasGuardadas = count($idsGuardados);

if ($guardado && $totalLineasGuardadas === 0 && $idGuardado !== '') {
    $idsGuardados = [(int) $idGuardado];
    $totalLineasGuardadas = 1;
}

if ($guardado) {
    foreach ($datos as $key => $value) {
        $datos[$key] = $key === 'id' ? $siguienteId : '';
    }
    $lineasUbicacion = datosBaseUbicacionesEntradaInventario($maxUbicaciones);
}

$lineasVisibles = 1;
foreach ($lineasUbicacion as $indice => $lineaUbicacion) {
    if (trim((string) ($lineaUbicacion['ubicacion'] ?? '')) !== '' || trim((string) ($lineaUbicacion['bultos'] ?? '')) !== '') {
        $lineasVisibles = $indice + 1;
    }
}
$lineasVisibles = max(1, min($maxUbicaciones, $lineasVisibles));

$urlEtiquetasGuardadas = '';
if ($guardado) {
    if ($totalLineasGuardadas > 1) {
        $urlEtiquetasGuardadas = BASE_URL . '/etiquetar_pdf.php?' . http_build_query(['seleccionados' => $idsGuardados]);
    } elseif ($idGuardado !== '') {
        $urlEtiquetasGuardadas = BASE_URL . '/etiqueta.php?id=' . rawurlencode($idGuardado);
    }
}

renderAppLayoutStart(
    'Inventario - Entrada',
    'entrada',
    'Inventario - Entrada',
    'Registro de nuevas entradas en almacen'
);
?>
<section class="panel panel-card">
    <?php if ($error !== ''): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <?php if ($guardado): ?>
        <div class="alert alert-success d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
            <div>
                <strong>Entrada guardada correctamente.</strong>
                <?php if ($totalLineasGuardadas > 1): ?>
                    <div>Lineas creadas: <?= htmlspecialchars((string) $totalLineasGuardadas, ENT_QUOTES, 'UTF-8') ?></div>
                    <div>IDs registrados: <?= htmlspecialchars(implode(', ', array_map('strval', $idsGuardados)), ENT_QUOTES, 'UTF-8') ?></div>
                <?php elseif ($idGuardado !== ''): ?>
                    <div>ID registrado: <?= htmlspecialchars($idGuardado, ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif; ?>
            </div>
            <?php if ($urlEtiquetasGuardadas !== ''): ?>
                <a class="btn btn-outline-primary" href="<?= htmlspecialchars($urlEtiquetasGuardadas, ENT_QUOTES, 'UTF-8') ?>">
                    <?= $totalLineasGuardadas > 1 ? 'Generar etiquetas PDF' : 'Generar etiqueta PDF' ?>
                </a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/entrada.php" class="row g-3" autocomplete="off" id="entrada-form">
        <div class="col-12 col-md-6 col-xl-3">
            <label class="form-label" for="id">ID inicial</label>
            <input class="form-control" id="id" name="id" type="number" min="1" value="<?= htmlspecialchars($datos['id'], ENT_QUOTES, 'UTF-8') ?>">
            <div class="form-text">Si anades varias ubicaciones, se usaran IDs consecutivos.</div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <label class="form-label" for="editorial">Editorial *</label>
            <input class="form-control" id="editorial" name="editorial" type="text" required value="<?= htmlspecialchars($datos['editorial'], ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="col-12 col-xl-6">
            <label class="form-label" for="centro_selector">Centro *</label>
            <div class="autocomplete-wrapper">
                <input class="form-control" id="centro_selector" name="centro_selector" type="text" required autocomplete="off" value="<?= htmlspecialchars($datos['centro_selector'], ENT_QUOTES, 'UTF-8') ?>" placeholder="Escribe para buscar un centro">
            </div>
            <div class="form-text">Busca por nombre, codigo o localidad.</div>
        </div>
        <div class="col-12 col-md-6">
            <label class="form-label" for="colegio">Centro seleccionado *</label>
            <input class="form-control bg-light" id="colegio" name="colegio" type="text" required readonly autocomplete="off" value="<?= htmlspecialchars($datos['colegio'], ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="col-12 col-md-6">
            <label class="form-label" for="codigo_centro">Codigo centro *</label>
            <input class="form-control bg-light" id="codigo_centro" name="codigo_centro" type="text" required readonly autocomplete="off" value="<?= htmlspecialchars($datos['codigo_centro'], ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <label class="form-label" for="fecha_entrada">Fecha entrada *</label>
            <input class="form-control" id="fecha_entrada" name="fecha_entrada" type="date" required value="<?= htmlspecialchars($datos['fecha_entrada'], ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <label class="form-label" for="bultos">Bultos totales *</label>
            <input class="form-control bg-light" id="bultos" name="bultos" type="number" min="0" readonly value="<?= htmlspecialchars($datos['bultos'], ENT_QUOTES, 'UTF-8') ?>">
            <div class="form-text">Se calcula automaticamente como suma de las ubicaciones.</div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <label class="form-label" for="destino">Destino</label>
            <select class="form-select" id="destino" name="destino">
                <option value=""<?= $datos['destino'] === '' ? ' selected' : '' ?>>Selecciona destino</option>
                <?php foreach (DESTINOS_PERMITIDOS as $destino): ?>
                    <option value="<?= htmlspecialchars($destino, ENT_QUOTES, 'UTF-8') ?>"<?= $datos['destino'] === $destino ? ' selected' : '' ?>>
                        <?= htmlspecialchars($destino, ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <label class="form-label" for="orden">Numero de orden</label>
            <input class="form-control" id="orden" name="orden" type="text" value="<?= htmlspecialchars($datos['orden'], ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-3">
                        <div>
                            <p class="eyebrow mb-1">Ubicaciones y bultos</p>
                            <p class="mb-0 text-body-secondary">Cada fila genera una linea de inventario independiente.</p>
                        </div>
                        <button class="btn btn-outline-primary btn-sm" type="button" id="agregar-ubicacion">Anadir ubicacion</button>
                    </div>
                    <div class="row g-3" id="ubicaciones-wrapper">
                        <?php foreach ($lineasUbicacion as $indice => $lineaUbicacion): ?>
                            <?php $filaVisible = $indice < $lineasVisibles; ?>
                            <div class="col-12 ubicacion-row<?= $filaVisible ? '' : ' d-none' ?>" data-ubicacion-index="<?= htmlspecialchars((string) $indice, ENT_QUOTES, 'UTF-8') ?>">
                                <div class="row g-2 align-items-end">
                                    <div class="col-12 col-md-7">
                                        <label class="form-label" for="ubicaciones_<?= htmlspecialchars((string) $indice, ENT_QUOTES, 'UTF-8') ?>">Ubicacion <?= htmlspecialchars((string) ($indice + 1), ENT_QUOTES, 'UTF-8') ?></label>
                                        <input
                                            class="form-control"
                                            id="ubicaciones_<?= htmlspecialchars((string) $indice, ENT_QUOTES, 'UTF-8') ?>"
                                            name="ubicaciones[]"
                                            type="text"
                                            value="<?= htmlspecialchars((string) ($lineaUbicacion['ubicacion'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                        >
                                    </div>
                                    <div class="col-12 col-md-3">
                                        <label class="form-label" for="bultos_ubicacion_<?= htmlspecialchars((string) $indice, ENT_QUOTES, 'UTF-8') ?>">Bultos</label>
                                        <input
                                            class="form-control bultos-ubicacion-input"
                                            id="bultos_ubicacion_<?= htmlspecialchars((string) $indice, ENT_QUOTES, 'UTF-8') ?>"
                                            name="bultos_ubicacion[]"
                                            type="number"
                                            min="1"
                                            step="1"
                                            value="<?= htmlspecialchars((string) ($lineaUbicacion['bultos'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                        >
                                    </div>
                                    <div class="col-12 col-md-2 d-grid">
                                        <button class="btn btn-outline-secondary quitar-ubicacion" type="button">Quitar</button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 d-flex flex-wrap gap-2">
            <button class="btn btn-primary mt-0" type="submit">Guardar entrada</button>
            <a class="btn btn-outline-secondary" href="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/inventario_consulta.php">Volver a inventario</a>
        </div>
    </form>
</section>

<script src="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/js/autocomplete.js"></script>
<script>
const centroInicial = <?= json_encode([
    'codigo_centro' => $datos['codigo_centro'],
    'nombre_centro' => $datos['colegio'],
    'localidad' => '',
    'destino' => $datos['destino'],
    'label' => $datos['centro_selector'],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const entradaForm = document.getElementById('entrada-form');
const centroSelectorInput = document.getElementById('centro_selector');
const centroNombreInput = document.getElementById('colegio');
const centroCodigoInput = document.getElementById('codigo_centro');
const centroDestinoInput = document.getElementById('destino');
const agregarUbicacionButton = document.getElementById('agregar-ubicacion');
const ubicacionRows = Array.from(document.querySelectorAll('.ubicacion-row'));
const totalBultosInput = document.getElementById('bultos');

function normalizarDestinoEntrada(destino) {
    return ['EDV', 'EPL'].includes(destino) ? destino : '';
}

function aplicarDestinoCentro(destino) {
    if (!centroDestinoInput) {
        return;
    }

    const destinoNormalizado = normalizarDestinoEntrada(destino);
    centroDestinoInput.value = destinoNormalizado;
    centroDestinoInput.dataset.destinoCentro = destinoNormalizado;
}

function limpiarCentroSeleccionado() {
    if (centroNombreInput) {
        centroNombreInput.value = '';
    }

    if (centroCodigoInput) {
        centroCodigoInput.value = '';
    }

    aplicarDestinoCentro('');
}

function aplicarCentroSeleccionado(centro) {
    if (!centro) {
        limpiarCentroSeleccionado();
        return;
    }

    centroNombreInput.value = centro.nombre_centro || '';
    centroCodigoInput.value = centro.codigo_centro || '';
    aplicarDestinoCentro(centro.destino || '');
}

function renderCentroAutocomplete(centro) {
    const contenido = document.createElement('span');
    const titulo = document.createElement('span');
    const meta = document.createElement('span');

    titulo.className = 'autocomplete-title';
    titulo.textContent = centro.codigo_centro === '000000' ? (centro.label || '') : (centro.nombre_centro || centro.label || '');
    meta.className = 'autocomplete-meta';
    meta.textContent = [centro.codigo_centro, centro.localidad, centro.destino].filter(Boolean).join(' - ');

    contenido.appendChild(titulo);
    if (meta.textContent !== '') {
        contenido.appendChild(meta);
    }

    return contenido;
}

const centroAutocomplete = window.AppAutocomplete?.init({
    inputSelector: '#centro_selector',
    endpoint: <?= json_encode(BASE_URL . '/centros_buscar.php', JSON_UNESCAPED_SLASHES) ?>,
    minChars: 2,
    limit: 20,
    formSelector: '#entrada-form',
    requireSelection: true,
    initialSelected: centroInicial.codigo_centro ? centroInicial : null,
    invalidMessage: 'Selecciona un centro valido de la lista.',
    emptyText: 'No hay centros que coincidan.',
    getInputValue: (centro) => centro.label || '',
    isValidSelection: () => centroCodigoInput.value.trim() !== '',
    onInput: limpiarCentroSeleccionado,
    onSelect: aplicarCentroSeleccionado,
    renderItem: renderCentroAutocomplete,
});

if (centroInicial.codigo_centro) {
    aplicarDestinoCentro(centroInicial.destino || '');
}

if (centroDestinoInput) {
    centroDestinoInput.addEventListener('change', () => {
        if (centroCodigoInput.value.trim() !== '') {
            centroDestinoInput.value = centroDestinoInput.dataset.destinoCentro || '';
        }
    });
}

function filasUbicacionVisibles() {
    return ubicacionRows.filter((row) => !row.classList.contains('d-none'));
}

function actualizarTotalBultos() {
    if (!totalBultosInput) {
        return;
    }

    const total = filasUbicacionVisibles().reduce((acumulado, row) => {
        const inputBultos = row.querySelector('.bultos-ubicacion-input');
        const valor = Number.parseInt(inputBultos?.value || '', 10);

        return acumulado + (Number.isFinite(valor) && valor > 0 ? valor : 0);
    }, 0);

    totalBultosInput.value = total > 0 ? String(total) : '';
}

function actualizarControlesUbicacion() {
    const visibles = filasUbicacionVisibles();

    ubicacionRows.forEach((row) => {
        const botonQuitar = row.querySelector('.quitar-ubicacion');
        if (!botonQuitar) {
            return;
        }

        botonQuitar.disabled = visibles.length <= 1 && !row.classList.contains('d-none');
    });

    if (agregarUbicacionButton) {
        agregarUbicacionButton.disabled = visibles.length >= ubicacionRows.length;
    }
}

function limpiarFilaUbicacion(row) {
    row.querySelectorAll('input').forEach((input) => {
        input.value = '';
    });
}

function mostrarSiguienteUbicacion() {
    const filaOculta = ubicacionRows.find((row) => row.classList.contains('d-none'));
    if (!filaOculta) {
        return;
    }

    filaOculta.classList.remove('d-none');
    filaOculta.querySelector('input')?.focus();
    actualizarControlesUbicacion();
}

ubicacionRows.forEach((row) => {
    row.querySelectorAll('input').forEach((input) => {
        input.addEventListener('input', actualizarTotalBultos);
    });

    row.querySelector('.quitar-ubicacion')?.addEventListener('click', () => {
        const visibles = filasUbicacionVisibles();

        if (visibles.length <= 1) {
            limpiarFilaUbicacion(row);
        } else {
            limpiarFilaUbicacion(row);
            row.classList.add('d-none');
        }

        actualizarTotalBultos();
        actualizarControlesUbicacion();
    });
});

agregarUbicacionButton?.addEventListener('click', mostrarSiguienteUbicacion);
actualizarTotalBultos();
actualizarControlesUbicacion();

if (entradaForm && centroSelectorInput && centroCodigoInput) {
    entradaForm.addEventListener('submit', (event) => {
        actualizarTotalBultos();

        if (centroCodigoInput.value.trim() === '') {
            event.preventDefault();
            centroSelectorInput.setCustomValidity('Selecciona un centro valido de la lista.');
            centroSelectorInput.reportValidity();
            return;
        }

        const centroSeleccionado = centroAutocomplete?.getSelected();
        if (centroSeleccionado) {
            aplicarCentroSeleccionado(centroSeleccionado);
        }

        centroSelectorInput.setCustomValidity('');
    });
}
</script>
<?php renderAppLayoutEnd(); ?>
