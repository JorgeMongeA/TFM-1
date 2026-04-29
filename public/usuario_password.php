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
require_once dirname(__DIR__) . '/app/usuarios.php';

require_login();

if (!puedeGestionarUsuarios()) {
    renderizarAccesoDenegado('No tienes permisos para cambiar contraseñas de usuarios.');
}

function limpiarReturnQueryUsuarioPassword(string $returnQuery): string
{
    return str_replace(["\r", "\n"], '', ltrim($returnQuery, '?'));
}

function construirUrlUsuariosGestionPassword(string $returnQuery): string
{
    $returnQuery = limpiarReturnQueryUsuarioPassword($returnQuery);
    return BASE_URL . '/usuarios.php' . ($returnQuery !== '' ? '?' . $returnQuery : '');
}

$usuarioId = (int) ($_GET['id'] ?? $_POST['usuario_id'] ?? 0);
$returnQuery = limpiarReturnQueryUsuarioPassword((string) ($_GET['return_query'] ?? $_POST['return_query'] ?? ''));
$usuario = null;
$error = '';

try {
    $pdo = conectar();

    if ($usuarioId <= 0) {
        $error = 'El usuario indicado no existe.';
    } else {
        $usuario = obtenerUsuarioGestionPorId($pdo, $usuarioId);
        if ($usuario === null) {
            $error = 'El usuario indicado no existe.';
        }
    }

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && $error === '' && $usuario !== null) {
        cambiarPasswordUsuarioGestion(
            $pdo,
            $usuarioId,
            (string) ($_POST['password_nueva'] ?? ''),
            (string) ($_POST['password_confirmacion'] ?? ''),
            obtenerContextoActividadActual()
        );

        $_SESSION['flash_usuarios'] = ['mensaje' => 'Contraseña actualizada correctamente.'];
        header('Location: ' . construirUrlUsuariosGestionPassword($returnQuery));
        exit;
    }
} catch (Throwable $e) {
    error_log('[USUARIO_PASSWORD] Error cambiando contraseña gestionada: ' . $e->getMessage());

    $mensajeError = trim($e->getMessage());
    $erroresPublicos = [
        'Completa todos los campos.',
        'La contraseña debe tener al menos 8 caracteres.',
        'Las contraseñas no coinciden.',
        'El usuario indicado no existe.',
        'No tienes permisos para cambiar contraseñas de usuarios.',
    ];

    $error = in_array($mensajeError, $erroresPublicos, true)
        ? $mensajeError
        : 'No se ha podido actualizar la contraseña.';
}

$usuarioNombre = trim((string) ($usuario['username'] ?? ''));
$urlVolver = construirUrlUsuariosGestionPassword($returnQuery);

renderAppLayoutStart(
    'Cambiar contraseña de usuario',
    'usuarios',
    'Cambiar contraseña',
    $usuarioNombre !== '' ? 'Usuario: ' . $usuarioNombre : 'Actualización segura de contraseña'
);
?>
<section class="panel panel-card">
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <?php if ($error !== ''): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <?php if ($usuario !== null): ?>
                <form method="POST" action="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/usuario_password.php" class="row g-3">
                    <input type="hidden" name="usuario_id" value="<?= htmlspecialchars((string) $usuarioId, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="return_query" value="<?= htmlspecialchars($returnQuery, ENT_QUOTES, 'UTF-8') ?>">

                    <div class="col-12 col-md-6">
                        <label class="form-label" for="password_nueva">Nueva contraseña</label>
                        <input class="form-control" id="password_nueva" name="password_nueva" type="password" minlength="8" required autocomplete="new-password">
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="password_confirmacion">Confirmar nueva contraseña</label>
                        <input class="form-control" id="password_confirmacion" name="password_confirmacion" type="password" minlength="8" required autocomplete="new-password">
                    </div>
                    <div class="col-12 d-flex flex-wrap gap-2">
                        <button class="btn btn-primary mt-0" type="submit">Guardar contraseña</button>
                        <a class="btn btn-outline-secondary" href="<?= htmlspecialchars($urlVolver, ENT_QUOTES, 'UTF-8') ?>">Volver a usuarios</a>
                    </div>
                </form>
            <?php else: ?>
                <a class="btn btn-outline-secondary" href="<?= htmlspecialchars($urlVolver, ENT_QUOTES, 'UTF-8') ?>">Volver a usuarios</a>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php renderAppLayoutEnd(); ?>
