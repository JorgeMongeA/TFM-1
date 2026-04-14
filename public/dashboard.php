<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/auth.php';
require_once dirname(__DIR__) . '/app/conexion.php';
require_once dirname(__DIR__) . '/app/actividad.php';
require_once dirname(__DIR__) . '/app/layout.php';

require_login();
requierePermiso(PERMISO_DASHBOARD);

$username = (string) ($_SESSION['username'] ?? $_SESSION['usuario'] ?? '');
$rol = obtenerRolUsuarioActual();
$accesos = [];
$ultimaActividad = [];
$errorActividad = '';

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
    $ultimaActividad = obtenerUltimaActividad($pdo, 8);
} catch (Throwable $e) {
    $errorActividad = 'No se ha podido cargar la actividad reciente.';
}

renderAppLayoutStart(
    'Dashboard',
    'dashboard',
    'Dashboard',
    'Panel de usuario y accesos disponibles segun tu rol'
);
?>
<section class="panel panel-card">
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
                    <p class="eyebrow">Acciones rapidas</p>
                    <div class="d-grid gap-2">
                        <a class="btn btn-primary" href="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/cambiar_password.php">Cambiar contrasena</a>
                        <a class="btn btn-outline-danger" href="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/logout.php">Cerrar sesion</a>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <p class="eyebrow">Navegacion</p>
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
                            <h2 class="section-title">Ultima actividad</h2>
                        </div>
                        <p class="mb-0 text-body-secondary">Ultimos movimientos relevantes del sistema, ordenados de mas reciente a mas antiguo.</p>
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
                                        <?= htmlspecialchars((string) ($actividad['icono'] ?? '•'), ENT_QUOTES, 'UTF-8') ?>
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
<?php renderAppLayoutEnd(); ?>
