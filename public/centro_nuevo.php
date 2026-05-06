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
requierePermiso(PERMISO_CENTROS_EDICION);

$datos = [
    'codigo_centro' => '',
    'nombre_centro' => '',
    'ciudad' => '',
    'codigo_congregacion' => '',
    'congregacion' => '',
    'entrada' => '',
    'almacen' => '',
    'destino' => '',
    'tipo' => '',
    'codigo_grupo' => '',
];
$error = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    foreach ($datos as $campo => $valor) {
        $datos[$campo] = trim((string) ($_POST[$campo] ?? ''));
    }

    if ($datos['codigo_centro'] === '' || $datos['nombre_centro'] === '') {
        $error = 'Código centro y nombre centro son obligatorios.';
    } else {
        try {
            $pdo = conectar();

            if (buscarCentroPorCodigo($pdo, $datos['codigo_centro']) !== null) {
                $error = 'Ya existe un centro con ese código.';
            } else {
                guardarCentro($pdo, $datos);
                header('Location: ' . BASE_URL . '/centros_editar.php?mensaje=creado');
                exit;
            }
        } catch (Throwable $e) {
            $mensajeError = trim($e->getMessage());
            $error = $mensajeError !== '' ? $mensajeError : 'No se pudo guardar el centro.';
        }
    }
}

renderAppLayoutStart(
    'Centros - Nuevo',
    'centros_editar',
    'Añadir centro',
    'Formulario base para alta de nuevos centros'
);
?>
<section class="panel panel-card">
    <?php if ($error !== ''): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <form method="POST" action="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/centro_nuevo.php" class="row g-3">
        <div class="col-12 col-md-6">
            <label class="form-label" for="codigo_centro">Código centro *</label>
            <input class="form-control" id="codigo_centro" name="codigo_centro" type="text" required value="<?= htmlspecialchars($datos['codigo_centro'], ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="col-12 col-md-6">
            <label class="form-label" for="nombre_centro">Nombre centro *</label>
            <input class="form-control" id="nombre_centro" name="nombre_centro" type="text" required value="<?= htmlspecialchars($datos['nombre_centro'], ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="col-12 col-md-6">
            <label class="form-label" for="ciudad">Localidad</label>
            <input class="form-control" id="ciudad" name="ciudad" type="text" value="<?= htmlspecialchars($datos['ciudad'], ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="col-12 col-md-3">
            <label class="form-label" for="codigo_congregacion">Codigo congregacion</label>
            <input class="form-control" id="codigo_congregacion" name="codigo_congregacion" type="text" value="<?= htmlspecialchars($datos['codigo_congregacion'], ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="col-12 col-md-3">
            <label class="form-label" for="congregacion">Congregacion</label>
            <input class="form-control" id="congregacion" name="congregacion" type="text" value="<?= htmlspecialchars($datos['congregacion'], ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="col-12 col-md-3">
            <label class="form-label" for="entrada">Entrada</label>
            <input class="form-control" id="entrada" name="entrada" type="text" value="<?= htmlspecialchars($datos['entrada'], ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="col-12 col-md-3">
            <label class="form-label" for="almacen">Almacen</label>
            <input class="form-control" id="almacen" name="almacen" type="text" value="<?= htmlspecialchars($datos['almacen'], ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="col-12 col-md-3">
            <label class="form-label" for="destino">Destino</label>
            <input class="form-control" id="destino" name="destino" type="text" value="<?= htmlspecialchars($datos['destino'], ENT_QUOTES, 'UTF-8') ?>">
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
            <button class="btn btn-primary mt-0" type="submit">Guardar centro</button>
            <a class="btn btn-outline-secondary" href="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/centros_editar.php">Volver</a>
        </div>
    </form>
</section>
<?php renderAppLayoutEnd(); ?>
