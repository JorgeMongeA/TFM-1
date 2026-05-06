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
require_once dirname(__DIR__) . '/app/centros.php';

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
    'ubicacion' => '',
    'fecha_entrada' => '',
    'bultos' => '',
    'destino' => '',
    'orden' => '',
];

$required = ['editorial', 'colegio', 'codigo_centro', 'ubicacion', 'fecha_entrada', 'bultos'];
$labels = [
    'editorial' => 'Editorial',
    'colegio' => 'Centro',
    'codigo_centro' => 'Código centro',
    'ubicacion' => 'Ubicación',
    'fecha_entrada' => 'Fecha entrada',
    'bultos' => 'Bultos',
];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    foreach ($datos as $key => $value) {
        $datos[$key] = trim((string) ($_POST[$key] ?? ''));
    }

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
            $error = 'Selecciona un centro válido de la lista.';
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
            $error = 'El ID debe ser un número entero mayor que cero.';
        }
    }

    if ($error === '' && !in_array($datos['destino'], array_merge([''], DESTINOS_PERMITIDOS), true)) {
        $error = 'El destino seleccionado no es válido.';
    }

    if ($error === '') {
        $sql = 'INSERT INTO inventario (
                    id, editorial, colegio, codigo_centro, ubicacion, fecha_entrada, bultos, destino, `orden`
                ) VALUES (
                    :id, :editorial, :colegio, :codigo_centro, :ubicacion, :fecha_entrada, :bultos, :destino, :orden
                )';

        try {
            if (!$pdo instanceof PDO) {
                $pdo = conectar();
            }

            $stmtExiste = $pdo->prepare('SELECT id FROM inventario WHERE id = :id');
            $stmtExiste->execute([':id' => (int) $datos['id']]);

            if ($stmtExiste->fetch() !== false) {
                $error = 'El ID ya existe en el inventario. Introduce otro valor.';
            } else {
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':id' => (int) $datos['id'],
                    ':editorial' => $datos['editorial'],
                    ':colegio' => $datos['colegio'],
                    ':codigo_centro' => $datos['codigo_centro'],
                    ':ubicacion' => $datos['ubicacion'],
                    ':fecha_entrada' => $datos['fecha_entrada'],
                    ':bultos' => (int) $datos['bultos'],
                    ':destino' => $datos['destino'] !== '' ? $datos['destino'] : null,
                    ':orden' => $datos['orden'] !== '' ? $datos['orden'] : null,
                ]);

                header('Location: ' . BASE_URL . '/entrada.php?guardado=1&id=' . rawurlencode((string) $datos['id']));
                exit;
            }
        } catch (Throwable $e) {
            $error = 'No se pudo guardar la entrada. Revisa los datos e inténtalo de nuevo.';
        }
    }
}

$guardado = (string) ($_GET['guardado'] ?? '') === '1';
$idGuardado = trim((string) ($_GET['id'] ?? ''));

if ($guardado) {
    foreach ($datos as $key => $value) {
        $datos[$key] = $key === 'id' ? $siguienteId : '';
    }
}

renderAppLayoutStart(
    'Inventario - Entrada',
    'entrada',
    'Inventario - Entrada',
    'Registro de nuevas entradas en almacén'
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
                <?php if ($idGuardado !== ''): ?>
                    <div>ID registrado: <?= htmlspecialchars($idGuardado, ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif; ?>
            </div>
            <a class="btn btn-outline-primary" href="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/etiqueta.php?id=<?= rawurlencode($idGuardado) ?>">Generar etiqueta PDF</a>
        </div>
    <?php endif; ?>

    <form method="POST" action="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/entrada.php" class="row g-3" autocomplete="off" id="entrada-form">
        <div class="col-12 col-md-6 col-xl-3">
            <label class="form-label" for="id">ID</label>
            <input class="form-control" id="id" name="id" type="number" min="1" value="<?= htmlspecialchars($datos['id'], ENT_QUOTES, 'UTF-8') ?>">
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
            <label class="form-label" for="codigo_centro">Código centro *</label>
            <input class="form-control bg-light" id="codigo_centro" name="codigo_centro" type="text" required readonly autocomplete="off" value="<?= htmlspecialchars($datos['codigo_centro'], ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <label class="form-label" for="ubicacion">Ubicación *</label>
            <input class="form-control" id="ubicacion" name="ubicacion" type="text" required value="<?= htmlspecialchars($datos['ubicacion'], ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <label class="form-label" for="fecha_entrada">Fecha entrada *</label>
            <input class="form-control" id="fecha_entrada" name="fecha_entrada" type="date" required value="<?= htmlspecialchars($datos['fecha_entrada'], ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <label class="form-label" for="bultos">Bultos *</label>
            <input class="form-control" id="bultos" name="bultos" type="number" min="0" required value="<?= htmlspecialchars($datos['bultos'], ENT_QUOTES, 'UTF-8') ?>">
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
            <label class="form-label" for="orden">Número de orden</label>
            <input class="form-control" id="orden" name="orden" type="text" value="<?= htmlspecialchars($datos['orden'], ENT_QUOTES, 'UTF-8') ?>">
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

if (entradaForm && centroSelectorInput && centroCodigoInput) {
    entradaForm.addEventListener('submit', (event) => {
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
