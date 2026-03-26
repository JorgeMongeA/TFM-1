<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/auth.php';
require_once dirname(__DIR__) . '/app/layout.php';

require_login();

$username = (string) ($_SESSION['username'] ?? '');
$rol = obtenerRolUsuarioActual();

renderAppLayoutStart(
    'Dashboard',
    'dashboard',
    'Dashboard',
    'Panel de usuario y accesos rápidos del sistema'
);
?>
<section class="panel panel-card">
    <div class="row g-3">
        <div class="col-12 col-xl-7">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body">
                    <p class="eyebrow">Usuario autenticado</p>
                    <h2 class="section-title"><?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?></h2>
                    <p class="texto mb-2">Acceso activo al sistema de gestión de inventario y centros.</p>
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
                        <a class="btn btn-outline-danger" href="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/logout.php">Cerrar sesión</a>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <p class="eyebrow">Navegación</p>
                    <div class="dashboard-links">
                        <a class="dashboard-link-card" href="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/inventario_consulta.php">
                            <strong>Inventario</strong>
                            <span>Consulta, entrada, salida y albarán.</span>
                        </a>
                        <a class="dashboard-link-card" href="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/centros.php">
                            <strong>Centros</strong>
                            <span>Consulta, sincronización y edición base.</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<?php renderAppLayoutEnd(); ?>
