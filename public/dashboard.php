<?php

/**
 * Autor: Jorge Monge
 * Trabajo Final de Máster (TFM)
 * UOC - 2026
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/auth.php';
require_once dirname(__DIR__) . '/app/conexion.php';
require_once dirname(__DIR__) . '/app/actividad.php';
require_once dirname(__DIR__) . '/app/layout.php';
require_once dirname(__DIR__) . '/app/notificaciones.php';
require_once dirname(__DIR__) . '/app/usuarios.php';

require_login();
requierePermiso(PERMISO_DASHBOARD);

$username = (string) ($_SESSION['username'] ?? $_SESSION['usuario'] ?? '');
$rol = obtenerRolUsuarioActual();
$accesos = [];
$ultimaActividad = [];
$errorActividad = '';
$solicitudesPendientes = 0;
$notificaciones = [];
$notificacionesNoLeidas = 0;
$flashSistema = $_SESSION['flash_sistema'] ?? null;
unset($_SESSION['flash_sistema']);

if (is_array($flashSistema) && trim((string) ($flashSistema['mensaje'] ?? '')) !== '') {
    $mensajeSistema = trim((string) $flashSistema['mensaje']);
    $mensajeSistemaOk = (bool) ($flashSistema['ok'] ?? true);
} else {
    $mensajeSistema = '';
    $mensajeSistemaOk = true;
}

if (puedeVerInventario()) {
    $accesos[] = [
        'titulo' => 'Inventario',
        'descripcion' => puedeEditarInventario()
            ? 'Consulta, entrada, salida, etiquetado y trazabilidad operativa.'
            : 'Consulta de stock disponible con filtros de inventario.',
        'href' => BASE_URL . '/inventario_consulta.php',
    ];
}

if (puedeAccederHistorico()) {
    $accesos[] = [
        'titulo' => 'Historico',
        'descripcion' => 'Consulta de mercancia confirmada y trazabilidad historica.',
        'href' => BASE_URL . '/historico.php',
    ];
}

if (puedeAccederPedidos()) {
    $accesos[] = [
        'titulo' => 'Pedidos',
        'descripcion' => puedeGestionarPedidos()
            ? 'Recepcion, revision, preparacion e impresion de pedidos internos.'
            : 'Creacion y seguimiento de solicitudes internas de mercancia.',
        'href' => BASE_URL . '/pedidos.php',
    ];
}

if (puedeAccederCentros()) {
    $accesos[] = [
        'titulo' => 'Centros',
        'descripcion' => puedeEditarCentros()
            ? 'Consulta, sincronizacion y mantenimiento de centros.'
            : 'Consulta de centros disponibles.',
        'href' => BASE_URL . '/centros.php',
    ];
}

try {
    $pdo = conectar();

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && usuarioEsAlmacen()) {
        $accion = trim((string) ($_POST['accion'] ?? ''));
        if ($accion === 'marcar_notificacion_leida') {
            marcarNotificacionLeida($pdo, (int) ($_POST['notificacion_id'] ?? 0), USUARIO_CIRCUITO_SOLICITUDES_USERNAME);
            header('Location: ' . BASE_URL . '/dashboard.php');
            exit;
        }
    }

    $ultimaActividad = obtenerUltimaActividad($pdo, 20);
    if (puedeGestionarUsuarios()) {
        $solicitudesPendientes = contarUsuariosPendientes($pdo);
        $notificaciones = listarNotificacionesUsuario($pdo, USUARIO_CIRCUITO_SOLICITUDES_USERNAME, 10);
        $notificacionesNoLeidas = contarNotificacionesNoLeidas($pdo, USUARIO_CIRCUITO_SOLICITUDES_USERNAME);

    }
} catch (Throwable $e) {
    $errorActividad = 'No se ha podido cargar la actividad reciente.';
    error_log('[DASHBOARD] Error cargando notificaciones o actividad: ' . $e->getMessage());
}

renderAppLayoutStart(
    'Dashboard',
    'dashboard',
    'Dashboard',
    'Panel de usuario y accesos disponibles según tu rol'
);
?>
<section class="panel panel-card">
    <?php if ($mensajeSistema !== ''): ?>
        <div class="alert <?= $mensajeSistemaOk ? 'alert-success' : 'alert-danger' ?>"><?= htmlspecialchars($mensajeSistema, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <div class="row g-3">
        <div class="col-12 col-xl-7">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body">
                    <p class="eyebrow">Usuario autenticado</p>
                    <h2 class="section-title"><?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?></h2>
                    <p class="texto mb-2">Acceso activo al sistema con permisos ajustados por rol.</p>
                    <p class="mb-0 text-body-secondary">
                        <?= $rol !== '' ? htmlspecialchars($rol, ENT_QUOTES, 'UTF-8') : 'Rol no disponible' ?>
                    </p>
                </div>
            </div>
        </div>
        <div class="col-12 col-xl-5">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body">
                    <p class="eyebrow">Acciones rápidas</p>
                    <div class="d-grid gap-2">
                        <a class="btn btn-primary" href="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/cambiar_password.php">Cambiar contraseña</a>
                        <?php if (puedeGestionarUsuarios()): ?>
                            <a class="btn btn-outline-primary" href="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/usuarios.php?estado=pendiente">
                                Solicitudes pendientes<?= $solicitudesPendientes > 0 ? ' (' . htmlspecialchars((string) $solicitudesPendientes, ENT_QUOTES, 'UTF-8') . ')' : '' ?>
                            </a>
                        <?php endif; ?>
                        <?php if (puedePrepararCampanas()): ?>
                            <button class="btn btn-outline-dark" type="button" data-bs-toggle="modal" data-bs-target="#modalIniciarCampana">Iniciar nueva campaña</button>
                        <?php endif; ?>
                        <a class="btn btn-outline-danger" href="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/logout.php">Cerrar sesión</a>
                    </div>
                </div>
            </div>
        </div>
        <?php if (usuarioEsAlmacen()): ?>
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <p class="eyebrow mb-1">Interno</p>
                                <h2 class="section-title mb-0">Notificaciones</h2>
                            </div>
                            <span class="badge text-bg-dark"><?= htmlspecialchars((string) $notificacionesNoLeidas, ENT_QUOTES, 'UTF-8') ?> sin leer</span>
                        </div>

                        <?php if ($notificaciones === []): ?>
                            <div class="alert alert-light border mb-0">No hay notificaciones.</div>
                        <?php else: ?>
                            <div class="d-flex flex-column gap-2">
                                <?php foreach ($notificaciones as $notificacion): ?>
                                    <div class="border rounded-3 p-3 d-flex flex-column flex-lg-row justify-content-between gap-3">
                                        <div>
                                            <div class="d-flex flex-wrap gap-2 mb-1">
                                                <span class="badge <?= (int) ($notificacion['leida'] ?? 0) === 1 ? 'text-bg-secondary' : 'text-bg-primary' ?>">
                                                    <?= (int) ($notificacion['leida'] ?? 0) === 1 ? 'Leída' : 'Nueva' ?>
                                                </span>
                                                <span class="text-body-secondary"><?= htmlspecialchars((string) ($notificacion['fecha'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                                            </div>
                                            <p class="mb-0"><?= htmlspecialchars((string) ($notificacion['mensaje'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                                        </div>
                                        <?php if ((int) ($notificacion['leida'] ?? 0) !== 1): ?>
                                            <form method="POST" action="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/dashboard.php">
                                                <input type="hidden" name="accion" value="marcar_notificacion_leida">
                                                <input type="hidden" name="notificacion_id" value="<?= htmlspecialchars((string) ($notificacion['id'] ?? 0), ENT_QUOTES, 'UTF-8') ?>">
                                                <button class="btn btn-sm btn-outline-secondary" type="submit">Marcar leída</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <p class="eyebrow">Navegación</p>
                    <div class="dashboard-links">
                        <?php foreach ($accesos as $acceso): ?>
                            <a class="dashboard-link-card" href="<?= htmlspecialchars((string) $acceso['href'], ENT_QUOTES, 'UTF-8') ?>">
                                <strong><?= htmlspecialchars((string) $acceso['titulo'], ENT_QUOTES, 'UTF-8') ?></strong>
                                <span><?= htmlspecialchars((string) $acceso['descripcion'], ENT_QUOTES, 'UTF-8') ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12">
            <div class="card border-0 shadow-sm activity-card">
                <div class="card-body">
                    <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-2 mb-3">
                        <div>
                            <p class="eyebrow mb-1">Actividad reciente</p>
                            <h2 class="section-title">Última actividad</h2>
                        </div>
                        <p class="mb-0 text-body-secondary">Últimos 20 movimientos relevantes del sistema, ordenados de más reciente a más antiguo.</p>
                    </div>

                    <?php if ($errorActividad !== ''): ?>
                        <div class="alert alert-light border mb-0"><?= htmlspecialchars($errorActividad, ENT_QUOTES, 'UTF-8') ?></div>
                    <?php elseif ($ultimaActividad === []): ?>
                        <div class="alert alert-light border mb-0">Todavia no hay actividad registrada para mostrar.</div>
                    <?php else: ?>
                        <div class="activity-feed">
                            <?php foreach ($ultimaActividad as $actividad): ?>
                                <article class="activity-item">
                                    <div class="activity-icon activity-icon--<?= htmlspecialchars((string) ($actividad['tipo_evento'] ?? 'sistema'), ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars((string) ($actividad['icono'] ?? '*'), ENT_QUOTES, 'UTF-8') ?>
                                    </div>
                                    <div class="activity-content">
                                        <div class="d-flex flex-column flex-xl-row align-items-xl-center gap-2 mb-1">
                                            <div class="d-flex flex-wrap align-items-center gap-2">
                                                <span class="activity-time"><?= htmlspecialchars((string) ($actividad['tiempo_relativo'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                                                <span class="badge <?= htmlspecialchars((string) ($actividad['badge'] ?? 'text-bg-secondary'), ENT_QUOTES, 'UTF-8') ?>">
                                                    <?= htmlspecialchars(actividadEtiqueta((string) ($actividad['tipo_evento'] ?? '')), ENT_QUOTES, 'UTF-8') ?>
                                                </span>
                                            </div>
                                            <span class="activity-date"><?= htmlspecialchars((string) ($actividad['fecha_evento_legible'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></span>
                                        </div>
                                        <p class="activity-text mb-1">
                                            <?= htmlspecialchars((string) ($actividad['descripcion'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                        </p>
                                        <div class="activity-meta">
                                            <span>Usuario <?= htmlspecialchars((string) (($actividad['usuario'] ?? '') !== '' ? $actividad['usuario'] : 'sistema'), ENT_QUOTES, 'UTF-8') ?></span>
                                            <?php if (((string) ($actividad['entidad_codigo'] ?? '')) !== ''): ?>
                                                <span>Referencia <?= htmlspecialchars((string) $actividad['entidad_codigo'], ENT_QUOTES, 'UTF-8') ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>
<?php if (puedePrepararCampanas()): ?>
    <div class="modal fade" id="modalIniciarCampana" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/campana_nueva.php">
                    <div class="modal-header">
                        <h2 class="modal-title fs-5">Iniciar nueva campaña</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <p>Esto eliminará todos los datos del sistema. Introduce tu contraseña para confirmar.</p>
                        <label class="form-label" for="password_actual">Contraseña</label>
                        <input class="form-control" id="password_actual" name="password_actual" type="password" required>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-danger">Confirmar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>
<?php renderAppLayoutEnd(); ?>
