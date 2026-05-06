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
    $nombre = trim((string) ($centro['nombre_centro'] ?? ''));
    $codigo = trim((string) ($centro['codigo_centro'] ?? ''));

    if ($nombre === '') {
        return $codigo;
    }

    return $nombre . ' (' . $codigo . ')';
}

$pdo = null;
$siguienteId = '';
$centros = [];
$error = '';

try {
    $pdo = conectar();
    asegurarCentroDesconocido($pdo);
    $siguienteId = obtenerSiguienteIdInventario($pdo);
    $centros = cargarCentrosParaSelector($pdo);
} catch (Throwable $e) {
    $siguienteId = '';
    $error = 'No se pudieron cargar los centros o el siguiente ID disponible.';
}

$centrosPorCodigo = [];
$centrosPorEtiqueta = [];

foreach ($centros as $centro) {
    $codigoCentro = trim((string) ($centro['codigo_centro'] ?? ''));
    if ($codigoCentro === '') {
        continue;
    }

    $centrosPorCodigo[$codigoCentro] = $centro;
    $centrosPorEtiqueta[construirEtiquetaCentro($centro)] = $centro;
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

        if ($datos['codigo_centro'] !== '' && isset($centrosPorCodigo[$datos['codigo_centro']])) {
            $centroSeleccionado = $centrosPorCodigo[$datos['codigo_centro']];
        } elseif ($datos['centro_selector'] !== '' && isset($centrosPorEtiqueta[$datos['centro_selector']])) {
            $centroSeleccionado = $centrosPorEtiqueta[$datos['centro_selector']];
        } elseif (
            strtoupper($datos['centro_selector']) === CENTRO_DESCONOCIDO_NOMBRE
            || strtoupper($datos['colegio']) === CENTRO_DESCONOCIDO_NOMBRE
            || $datos['codigo_centro'] === CENTRO_DESCONOCIDO_CODIGO
        ) {
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

$centrosJson = [];
foreach ($centros as $centro) {
    $centrosJson[] = [
        'label' => construirEtiquetaCentro($centro),
        'codigo' => (string) ($centro['codigo_centro'] ?? ''),
        'nombre' => (string) ($centro['nombre_centro'] ?? ''),
        'destino' => normalizarDestinoCentro($centro['destino'] ?? ''),
    ];
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

    <form method="POST" action="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/entrada.php" class="row g-3" autocomplete="off">
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
            <input class="form-control" id="centro_selector" name="centro_selector" type="text" list="centros_disponibles" required value="<?= htmlspecialchars($datos['centro_selector'], ENT_QUOTES, 'UTF-8') ?>" placeholder="Escribe para buscar un centro">
            <datalist id="centros_disponibles">
                <?php foreach ($centros as $centro): ?>
                    <option value="<?= htmlspecialchars(construirEtiquetaCentro($centro), ENT_QUOTES, 'UTF-8') ?>"></option>
                <?php endforeach; ?>
            </datalist>
            <div class="form-text">Puedes buscar por nombre. Incluye la opción DESCONOCIDO (000000).</div>
        </div>
        <div class="col-12 col-md-6">
            <label class="form-label" for="colegio">Centro seleccionado *</label>
            <input class="form-control bg-light" id="colegio" name="colegio" type="text" required readonly value="<?= htmlspecialchars($datos['colegio'], ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="col-12 col-md-6">
            <label class="form-label" for="codigo_centro">Código centro *</label>
            <input class="form-control bg-light" id="codigo_centro" name="codigo_centro" type="text" required readonly value="<?= htmlspecialchars($datos['codigo_centro'], ENT_QUOTES, 'UTF-8') ?>">
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

<script>
const centrosEntrada = <?= json_encode($centrosJson, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const centroSelectorInput = document.getElementById('centro_selector');
const centroNombreInput = document.getElementById('colegio');
const centroCodigoInput = document.getElementById('codigo_centro');
const centroDestinoInput = document.getElementById('destino');

function aplicarCentroSeleccionado(valor) {
    const centro = centrosEntrada.find((item) => item.label === valor);

    if (!centro) {
        centroNombreInput.value = '';
        centroCodigoInput.value = '';
        if (centroDestinoInput) {
            centroDestinoInput.value = '';
        }
        return;
    }

    centroNombreInput.value = centro.nombre;
    centroCodigoInput.value = centro.codigo;
    if (centroDestinoInput) {
        centroDestinoInput.value = ['EDV', 'EPL'].includes(centro.destino) ? centro.destino : '';
    }
}

if (centroSelectorInput) {
    aplicarCentroSeleccionado(centroSelectorInput.value);
    centroSelectorInput.addEventListener('input', (event) => {
        aplicarCentroSeleccionado(event.target.value);
    });
    centroSelectorInput.addEventListener('change', (event) => {
        aplicarCentroSeleccionado(event.target.value);
    });
}
</script>
<?php renderAppLayoutEnd(); ?>
