<?php

/**
 * Autor: Jorge Monge
 * Trabajo Final de MÃ¡ster (TFM)
 * UOC - 2026
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/conexion.php';
require_once dirname(__DIR__) . '/app/auth.php';
require_once dirname(__DIR__) . '/app/layout.php';
require_once dirname(__DIR__) . '/app/inventario.php';

require_login();
requierePermiso(PERMISO_INVENTARIO_EDICION, 'No tienes permisos para editar lineas de inventario.');

const INVENTARIO_DESTINOS_EDITABLES = ['EDV', 'EPL'];

function tokenEdicionInventario(): string
{
    iniciar_sesion();

    if (!isset($_SESSION['inventario_edicion_token']) || !is_string($_SESSION['inventario_edicion_token'])) {
        $_SESSION['inventario_edicion_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['inventario_edicion_token'];
}

function validarTokenEdicionInventario(string $token): bool
{
    iniciar_sesion();
    $tokenSesion = $_SESSION['inventario_edicion_token'] ?? '';

    return is_string($tokenSesion) && $tokenSesion !== '' && hash_equals($tokenSesion, $token);
}

$inventarioId = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$returnQuery = trim((string) ($_GET['return_query'] ?? $_POST['return_query'] ?? ''));
$error = '';
$datos = datosBaseEdicionInventario();
$csrfEdicion = tokenEdicionInventario();

try {
    $pdo = conectar();
} catch (Throwable $e) {
    $pdo = null;
    $mensajeError = trim($e->getMessage());
    $error = $mensajeError !== '' ? $mensajeError : 'No se pudo conectar con la base de datos.';
}

if ($inventarioId <= 0) {
    $error = $error !== '' ? $error : 'No se ha indicado una linea de inventario valida.';
}

if ($error === '' && $pdo instanceof PDO && (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET')) {
    $inventario = consultarInventarioPorId($pdo, $inventarioId);
    if ($inventario === null) {
        $error = 'La linea de inventario solicitada no existe.';
    } elseif ((string) ($inventario['estado'] ?? '') !== INVENTARIO_ESTADO_ACTIVO) {
        $error = 'Solo se pueden editar lineas activas de inventario.';
    } else {
        $datos = poblarDatosEdicionInventarioDesdeFila($inventario);
    }
}

if ($error === '' && $pdo instanceof PDO && (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST')) {
    $token = trim((string) ($_POST['csrf_token'] ?? ''));
    if (!validarTokenEdicionInventario($token)) {
        $error = 'La sesion de edicion ha caducado. Vuelve a intentarlo.';
    } else {
        try {
            $datos = validarDatosEdicionInventario($_POST, INVENTARIO_DESTINOS_EDITABLES);
            actualizarLineaInventario($pdo, $inventarioId, $datos);

            $_SESSION['flash_inventario'] = [
                'tipo' => 'success',
                'mensaje' => 'Linea de inventario actualizada correctamente.',
            ];

            $urlRedireccion = BASE_URL . '/inventario_consulta.php' . ($returnQuery !== '' ? '?' . $returnQuery : '');
            header('Location: ' . $urlRedireccion);
            exit;
        } catch (Throwable $e) {
            $datos = array_merge($datos, array_map(
                static fn(mixed $valor): string => trim((string) $valor),
                array_intersect_key($_POST, $datos)
            ));
            $mensajeError = trim($e->getMessage());
            $error = $mensajeError !== '' ? $mensajeError : 'No se pudo actualizar la linea de inventario.';
        }
    }
}

$urlVolver = BASE_URL . '/inventario_consulta.php' . ($returnQuery !== '' ? '?' . $returnQuery : '');

renderAppLayoutStart(
    'Inventario - Editar linea',
    'inventario_consulta',
    'Editar linea de inventario',
    'Actualizacion operativa de stock activo'
);
?>
<section class="panel panel-card">
    <?php if ($error !== ''): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <?php if ($error === '' || $datos['id'] !== ''): ?>
        <form method="POST" action="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/inventario_editar.php" class="row g-3" autocomplete="off">
            <input type="hidden" name="id" value="<?= htmlspecialchars((string) $inventarioId, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfEdicion, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="return_query" value="<?= htmlspecialchars($returnQuery, ENT_QUOTES, 'UTF-8') ?>">

            <div class="col-12 col-md-4 col-xl-2">
                <label class="form-label" for="id_mostrar">ID</label>
                <input class="form-control bg-light" id="id_mostrar" type="text" readonly value="<?= htmlspecialchars($datos['id'], ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="col-12 col-md-8 col-xl-4">
                <label class="form-label" for="editorial">Editorial *</label>
                <input class="form-control" id="editorial" name="editorial" type="text" required value="<?= htmlspecialchars($datos['editorial'], ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="col-12 col-xl-6">
                <label class="form-label" for="colegio">Colegio *</label>
                <input class="form-control" id="colegio" name="colegio" type="text" required value="<?= htmlspecialchars($datos['colegio'], ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="col-12 col-md-6 col-xl-3">
                <label class="form-label" for="codigo_centro">Codigo centro *</label>
                <input class="form-control" id="codigo_centro" name="codigo_centro" type="text" required value="<?= htmlspecialchars($datos['codigo_centro'], ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="col-12 col-md-6 col-xl-3">
                <label class="form-label" for="ubicacion">Ubicacion *</label>
                <input class="form-control" id="ubicacion" name="ubicacion" type="text" required value="<?= htmlspecialchars($datos['ubicacion'], ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="col-12 col-md-6 col-xl-2">
                <label class="form-label" for="fecha_entrada">Fecha entrada *</label>
                <input class="form-control" id="fecha_entrada" name="fecha_entrada" type="date" required value="<?= htmlspecialchars($datos['fecha_entrada'], ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="col-12 col-md-6 col-xl-2">
                <label class="form-label" for="bultos">Bultos *</label>
                <input class="form-control" id="bultos" name="bultos" type="number" min="0" required value="<?= htmlspecialchars($datos['bultos'], ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="col-12 col-md-6 col-xl-2">
                <label class="form-label" for="destino">Destino</label>
                <select class="form-select" id="destino" name="destino">
                    <option value=""<?= $datos['destino'] === '' ? ' selected' : '' ?>>Sin destino</option>
                    <?php foreach (INVENTARIO_DESTINOS_EDITABLES as $destino): ?>
                        <option value="<?= htmlspecialchars($destino, ENT_QUOTES, 'UTF-8') ?>"<?= $datos['destino'] === $destino ? ' selected' : '' ?>>
                            <?= htmlspecialchars($destino, ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-6 col-xl-3">
                <label class="form-label" for="orden">Orden</label>
                <input class="form-control" id="orden" name="orden" type="text" value="<?= htmlspecialchars($datos['orden'], ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="col-12 d-flex flex-wrap gap-2">
                <button class="btn btn-primary mt-0" type="submit">Guardar cambios</button>
                <a class="btn btn-outline-secondary" href="<?= htmlspecialchars($urlVolver, ENT_QUOTES, 'UTF-8') ?>">Cancelar</a>
            </div>
        </form>
    <?php endif; ?>
</section>
<?php renderAppLayoutEnd(); ?>
