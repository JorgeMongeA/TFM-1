<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/auth.php';
require_once dirname(__DIR__) . '/app/layout.php';

require_login();
requierePermiso(PERMISO_DASHBOARD);

$username = (string) ($_SESSION['username'] ?? $_SESSION['usuario'] ?? '');
$rol = obtenerRolUsuarioActual();
$accesos = [];

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
    </div>
</section>
<?php renderAppLayoutEnd(); ?>
