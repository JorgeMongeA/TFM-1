<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/conexion.php';
require_once dirname(__DIR__) . '/app/auth.php';
require_once dirname(__DIR__) . '/app/centros.php';
require_once dirname(__DIR__) . '/app/layout.php';

require_login();

$codigoOriginal = trim((string) ($_GET['codigo_centro'] ?? $_POST['codigo_original'] ?? ''));
$error = '';

try {
    $pdo = conectar();
} catch (Throwable $e) {
    $pdo = null;
    $mensajeError = trim($e->getMessage());
    $error = $mensajeError !== '' ? $mensajeError : 'No se pudo conectar con la base de datos.';
}

if ($codigoOriginal === '') {
    $error = $error !== '' ? $error : 'No se ha indicado el centro a editar.';
}

$datos = [
    'codigo_centro' => '',
    'nombre_centro' => '',
    'ciudad' => '',
    'tipo' => '',
    'codigo_grupo' => '',
];

if ($error === '' && $pdo instanceof PDO && (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET')) {
    $centro = buscarCentroPorCodigo($pdo, $codigoOriginal);
    if ($centro === null) {
        $error = 'El centro solicitado no existe.';
    } else {
        $datos = $centro;
    }
}

if ($error === '' && $pdo instanceof PDO && (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST')) {
    foreach ($datos as $campo => $valor) {
        $datos[$campo] = trim((string) ($_POST[$campo] ?? ''));
    }

    if ($datos['codigo_centro'] === '' || $datos['nombre_centro'] === '') {
        $error = 'Código centro y nombre centro son obligatorios.';
    } else {
        try {
            $centroExistente = buscarCentroPorCodigo($pdo, $datos['codigo_centro']);
            if ($datos['codigo_centro'] !== $codigoOriginal && $centroExistente !== null) {
                $error = 'Ya existe otro centro con ese código.';
            } else {
                guardarCentro($pdo, $datos, $codigoOriginal);
                header('Location: ' . BASE_URL . '/centros_editar.php?mensaje=actualizado');
                exit;
            }
        } catch (Throwable $e) {
            $mensajeError = trim($e->getMessage());
            $error = $mensajeError !== '' ? $mensajeError : 'No se pudo actualizar el centro.';
        }
    }
}

renderAppLayoutStart(
    'Centros - Editar centro',
    'centros_editar',
    'Editar centro',
    'Actualización base de datos de centro'
);
?>
<section class="panel panel-card">
    <?php if ($error !== ''): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <?php if ($error === ''): ?>
        <form method="POST" action="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/centro_editar.php" class="row g-3">
            <input type="hidden" name="codigo_original" value="<?= htmlspecialchars($codigoOriginal, ENT_QUOTES, 'UTF-8') ?>">
            <div class="col-12 col-md-6">
                <label class="form-label" for="codigo_centro">Código centro *</label>
                <input class="form-control" id="codigo_centro" name="codigo_centro" type="text" required value="<?= htmlspecialchars($datos['codigo_centro'], ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label" for="nombre_centro">Nombre centro *</label>
                <input class="form-control" id="nombre_centro" name="nombre_centro" type="text" required value="<?= htmlspecialchars($datos['nombre_centro'], ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label" for="ciudad">Ciudad</label>
                <input class="form-control" id="ciudad" name="ciudad" type="text" value="<?= htmlspecialchars($datos['ciudad'], ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label" for="tipo">Tipo</label>
                <input class="form-control" id="tipo" name="tipo" type="text" value="<?= htmlspecialchars($datos['tipo'], ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label" for="codigo_grupo">Código grupo</label>
                <input class="form-control" id="codigo_grupo" name="codigo_grupo" type="text" value="<?= htmlspecialchars($datos['codigo_grupo'], ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="col-12 d-flex flex-wrap gap-2">
                <button class="btn btn-primary mt-0" type="submit">Guardar cambios</button>
                <a class="btn btn-outline-secondary" href="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/centros_editar.php">Volver</a>
            </div>
        </form>
    <?php endif; ?>
</section>
<?php renderAppLayoutEnd(); ?>
