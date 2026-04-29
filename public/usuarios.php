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
requierePermiso(PERMISO_USUARIOS, 'No tienes permisos para gestionar usuarios.');

if (!puedeGestionarUsuarios()) {
    renderizarAccesoDenegado('No tienes permisos para gestionar usuarios.');
}

$filtros = [
    'estado' => trim((string) ($_GET['estado'] ?? '')),
    'rol_id' => (int) ($_GET['rol_id'] ?? 0),
    'q' => trim((string) ($_GET['q'] ?? '')),
];
$returnQueryActual = (string) ($_SERVER['QUERY_STRING'] ?? '');
$puedeCambiarPasswordGestion = puedeGestionarUsuarios();
$usuarios = [];
$roles = [];
$error = '';
$mensaje = '';
$solicitudesPendientes = 0;
$flash = $_SESSION['flash_usuarios'] ?? null;
unset($_SESSION['flash_usuarios']);

if (is_array($flash)) {
    $mensaje = trim((string) ($flash['mensaje'] ?? ''));
}

try {
    $pdo = conectar();
    $roles = rolesAsignablesUsuarios($pdo);
    $solicitudesPendientes = contarUsuariosPendientes($pdo);

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $accion = trim((string) ($_POST['accion'] ?? ''));
        $usuarioId = (int) ($_POST['usuario_id'] ?? 0);
        $rolId = (int) ($_POST['rol_id'] ?? 0);
        $admin = obtenerContextoActividadActual();

        if ($accion === 'aprobar') {
            aprobarUsuario($pdo, $usuarioId, $rolId, $admin);
            $_SESSION['flash_usuarios'] = ['mensaje' => 'Usuario aprobado y activado correctamente.'];
        } elseif ($accion === 'rechazar') {
            rechazarUsuario($pdo, $usuarioId, $admin);
            $_SESSION['flash_usuarios'] = ['mensaje' => 'Solicitud rechazada correctamente.'];
        } elseif ($accion === 'actualizar') {
            $activo = (string) ($_POST['activo'] ?? '0') === '1';
            actualizarEstadoYRolUsuario($pdo, $usuarioId, $rolId, $activo, $admin);
            $_SESSION['flash_usuarios'] = ['mensaje' => 'Usuario actualizado correctamente.'];
        }

        $query = trim((string) ($_POST['return_query'] ?? ''));
        header('Location: ' . BASE_URL . '/usuarios.php' . ($query !== '' ? '?' . $query : ''));
        exit;
    }

    $usuarios = listarUsuariosGestion($pdo, $filtros);
} catch (Throwable $e) {
    error_log('[USUARIOS] Error cargando la gestión de usuarios: ' . $e->getMessage());
    $error = 'No se ha podido cargar la gestión de usuarios en este momento.';
}

renderAppLayoutStart(
    'Gestion de usuarios',
    'usuarios',
    'Gestion de usuarios',
    'Aprobacion, activacion y control de accesos del sistema'
);
?>
<section class="panel panel-card">
    <?php if ($mensaje !== ''): ?>
        <div class="alert alert-success"><?= htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <div class="alert alert-info">
        Las solicitudes pendientes de aprobación se gestionan desde esta pantalla. Pendientes actuales: <?= htmlspecialchars((string) $solicitudesPendientes, ENT_QUOTES, 'UTF-8') ?>.
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <div class="d-flex flex-column flex-lg-row align-items-lg-end justify-content-between gap-3">
                <form method="GET" action="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/usuarios.php" class="row g-3 align-items-end flex-grow-1">
                    <div class="col-12 col-md-4">
                        <label class="form-label" for="q">Buscar</label>
                        <input class="form-control" id="q" name="q" type="text" value="<?= htmlspecialchars($filtros['q'], ENT_QUOTES, 'UTF-8') ?>" placeholder="Usuario o email">
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label" for="estado">Estado</label>
                        <select class="form-select" id="estado" name="estado">
                            <option value="">Todos</option>
                            <option value="<?= htmlspecialchars(USUARIO_ESTADO_PENDIENTE, ENT_QUOTES, 'UTF-8') ?>"<?= $filtros['estado'] === USUARIO_ESTADO_PENDIENTE ? ' selected' : '' ?>>Pendiente</option>
                            <option value="<?= htmlspecialchars(USUARIO_ESTADO_ACTIVO, ENT_QUOTES, 'UTF-8') ?>"<?= $filtros['estado'] === USUARIO_ESTADO_ACTIVO ? ' selected' : '' ?>>Activo</option>
                            <option value="<?= htmlspecialchars(USUARIO_ESTADO_DESACTIVADO, ENT_QUOTES, 'UTF-8') ?>"<?= $filtros['estado'] === USUARIO_ESTADO_DESACTIVADO ? ' selected' : '' ?>>Desactivado</option>
                            <option value="<?= htmlspecialchars(USUARIO_ESTADO_RECHAZADO, ENT_QUOTES, 'UTF-8') ?>"<?= $filtros['estado'] === USUARIO_ESTADO_RECHAZADO ? ' selected' : '' ?>>Rechazado</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label" for="rol_id">Rol</label>
                        <select class="form-select" id="rol_id" name="rol_id">
                            <option value="">Todos</option>
                            <?php foreach ($roles as $rol): ?>
                                <option value="<?= htmlspecialchars((string) ($rol['id'] ?? 0), ENT_QUOTES, 'UTF-8') ?>"<?= $filtros['rol_id'] === (int) ($rol['id'] ?? 0) ? ' selected' : '' ?>>
                                    <?= htmlspecialchars(etiquetaRolUsuario(normalizarRolAplicacion((string) ($rol['nombre'] ?? ''))), ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12 col-md-2 d-flex gap-2">
                        <button class="btn btn-primary mt-0" type="submit">Filtrar</button>
                        <a class="btn btn-outline-secondary" href="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/usuarios.php">Limpiar</a>
                    </div>
                </form>
                <a class="btn btn-outline-primary" href="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/usuario_nuevo.php" target="_blank" rel="noopener">Abrir alta publica</a>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <?php if ($usuarios === []): ?>
                <div class="alert alert-light border mb-0">No hay usuarios que coincidan con los filtros actuales.</div>
            <?php else: ?>
                <div class="table-responsive custom-table-wrap">
                    <table class="table table-hover align-middle mb-0 data-table">
                        <thead>
                            <tr>
                                <th>Usuario</th>
                                <th>Email</th>
                                <th>Rol</th>
                                <th>Estado</th>
                                <th>Aprobado por</th>
                                <th>Fecha aprobacion</th>
                                <th>Creado</th>
                                <th>Gestion</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($usuarios as $usuario): ?>
                                <?php
                                $usuarioIdGestion = (int) ($usuario['id'] ?? 0);
                                $urlCambioPassword = BASE_URL . '/usuario_password.php?id=' . rawurlencode((string) $usuarioIdGestion);
                                if ($returnQueryActual !== '') {
                                    $urlCambioPassword .= '&return_query=' . rawurlencode($returnQueryActual);
                                }
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars((string) ($usuario['username'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars((string) (($usuario['email'] ?? '') !== '' ? $usuario['email'] : '-'), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars((string) (($usuario['rol_label'] ?? '') !== '' ? $usuario['rol_label'] : '-'), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td>
                                        <span class="badge <?= htmlspecialchars((string) ($usuario['estado_badge'] ?? 'text-bg-secondary'), ENT_QUOTES, 'UTF-8') ?>">
                                            <?= htmlspecialchars(etiquetaEstadoGestionUsuario((string) ($usuario['estado_gestion'] ?? '')), ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars((string) (($usuario['aprobado_por_username'] ?? '') !== '' ? $usuario['aprobado_por_username'] : '-'), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars((string) (($usuario['fecha_aprobacion'] ?? '') !== '' ? $usuario['fecha_aprobacion'] : '-'), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars((string) (($usuario['creado_en'] ?? '') !== '' ? $usuario['creado_en'] : '-'), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td>
                                        <?php if (usuarioEstaPendiente($usuario)): ?>
                                            <form method="POST" action="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/usuarios.php" class="d-flex flex-column gap-2">
                                                <input type="hidden" name="accion" value="aprobar">
                                                <input type="hidden" name="usuario_id" value="<?= htmlspecialchars((string) ($usuario['id'] ?? 0), ENT_QUOTES, 'UTF-8') ?>">
                                                <input type="hidden" name="return_query" value="<?= htmlspecialchars((string) ($_SERVER['QUERY_STRING'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                                <select class="form-select form-select-sm" name="rol_id">
                                                    <?php foreach ($roles as $rol): ?>
                                                        <option value="<?= htmlspecialchars((string) ($rol['id'] ?? 0), ENT_QUOTES, 'UTF-8') ?>"<?= (int) ($usuario['rol_id_asignable'] ?? 0) === (int) ($rol['id'] ?? 0) ? ' selected' : '' ?>>
                                                            <?= htmlspecialchars(etiquetaRolUsuario(normalizarRolAplicacion((string) ($rol['nombre'] ?? ''))), ENT_QUOTES, 'UTF-8') ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <button class="btn btn-sm btn-primary" type="submit">Aprobar y activar</button>
                                            </form>
                                            <form method="POST" action="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/usuarios.php" class="mt-2">
                                                <input type="hidden" name="accion" value="rechazar">
                                                <input type="hidden" name="usuario_id" value="<?= htmlspecialchars((string) ($usuario['id'] ?? 0), ENT_QUOTES, 'UTF-8') ?>">
                                                <input type="hidden" name="return_query" value="<?= htmlspecialchars((string) ($_SERVER['QUERY_STRING'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                                <button class="btn btn-sm btn-outline-danger w-100" type="submit">Rechazar</button>
                                            </form>
                                            <?php if ($puedeCambiarPasswordGestion): ?>
                                                <a class="btn btn-sm btn-outline-secondary w-100 mt-2" href="<?= htmlspecialchars($urlCambioPassword, ENT_QUOTES, 'UTF-8') ?>">Cambiar contraseña</a>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <form method="POST" action="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/usuarios.php" class="d-flex flex-column gap-2">
                                                <input type="hidden" name="accion" value="actualizar">
                                                <input type="hidden" name="usuario_id" value="<?= htmlspecialchars((string) ($usuario['id'] ?? 0), ENT_QUOTES, 'UTF-8') ?>">
                                                <input type="hidden" name="return_query" value="<?= htmlspecialchars((string) ($_SERVER['QUERY_STRING'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                                <select class="form-select form-select-sm" name="rol_id">
                                                    <?php foreach ($roles as $rol): ?>
                                                        <option value="<?= htmlspecialchars((string) ($rol['id'] ?? 0), ENT_QUOTES, 'UTF-8') ?>"<?= (int) ($usuario['rol_id_asignable'] ?? 0) === (int) ($rol['id'] ?? 0) ? ' selected' : '' ?>>
                                                            <?= htmlspecialchars(etiquetaRolUsuario(normalizarRolAplicacion((string) ($rol['nombre'] ?? ''))), ENT_QUOTES, 'UTF-8') ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <select class="form-select form-select-sm" name="activo">
                                                    <option value="1"<?= (int) ($usuario['activo'] ?? 0) === 1 ? ' selected' : '' ?>>Activo</option>
                                                    <option value="0"<?= (int) ($usuario['activo'] ?? 0) !== 1 ? ' selected' : '' ?>>Desactivado</option>
                                                </select>
                                                <button class="btn btn-sm btn-outline-primary" type="submit">Guardar</button>
                                            </form>
                                            <?php if ($puedeCambiarPasswordGestion): ?>
                                                <a class="btn btn-sm btn-outline-secondary w-100 mt-2" href="<?= htmlspecialchars($urlCambioPassword, ENT_QUOTES, 'UTF-8') ?>">Cambiar contraseña</a>
                                            <?php endif; ?>
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
</section>
<?php renderAppLayoutEnd(); ?>
