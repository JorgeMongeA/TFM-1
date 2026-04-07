<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';

function obtenerDatosUsuarioActual(): array
{
    return [
        'username' => (string) ($_SESSION['username'] ?? $_SESSION['usuario'] ?? ''),
        'rol' => obtenerRolUsuario(),
        'rol_label' => obtenerEtiquetaRolUsuario(),
    ];
}

function obtenerRolUsuarioActual(): string
{
    return obtenerEtiquetaRolUsuario();
}

function menuLateral(): array
{
    return [
        [
            'label' => 'Dashboard',
            'href' => BASE_URL . '/dashboard.php',
            'key' => 'dashboard',
            'permission' => PERMISO_DASHBOARD,
        ],
        [
            'label' => 'Inventario',
            'key' => 'inventario',
            'children' => [
                [
                    'label' => 'Consulta',
                    'href' => BASE_URL . '/inventario_consulta.php',
                    'key' => 'inventario_consulta',
                    'permission' => PERMISO_INVENTARIO_CONSULTA,
                ],
                [
                    'label' => 'Entrada',
                    'href' => BASE_URL . '/entrada.php',
                    'key' => 'entrada',
                    'permission' => PERMISO_INVENTARIO_EDICION,
                ],
                [
                    'label' => 'Salida',
                    'href' => BASE_URL . '/salida.php',
                    'key' => 'salida',
                    'permission' => PERMISO_INVENTARIO_SALIDA,
                ],
                [
                    'label' => 'Historico',
                    'href' => BASE_URL . '/historico.php',
                    'key' => 'historico',
                    'permission' => PERMISO_HISTORICO,
                ],
                [
                    'label' => 'Etiquetar',
                    'href' => BASE_URL . '/etiquetar.php',
                    'key' => 'etiquetar',
                    'permission' => PERMISO_ETIQUETAS,
                ],
                [
                    'label' => 'Albaran',
                    'href' => BASE_URL . '/albaran.php',
                    'key' => 'albaran',
                    'permission' => PERMISO_ALBARANES,
                ],
            ],
        ],
        [
            'label' => 'Pedidos',
            'href' => BASE_URL . '/pedidos.php',
            'key' => 'pedidos',
            'permission' => PERMISO_PEDIDOS,
        ],
        [
            'label' => 'Centros',
            'key' => 'centros',
            'children' => [
                [
                    'label' => 'Consulta',
                    'href' => BASE_URL . '/centros.php',
                    'key' => 'centros_consulta',
                    'permission' => PERMISO_CENTROS_CONSULTA,
                ],
                [
                    'label' => 'Editar',
                    'href' => BASE_URL . '/centros_editar.php',
                    'key' => 'centros_editar',
                    'permission' => PERMISO_CENTROS_EDICION,
                ],
            ],
        ],
    ];
}

function itemMenuVisible(array $item): bool
{
    $permission = trim((string) ($item['permission'] ?? ''));

    if ($permission !== '' && !usuarioTienePermiso($permission)) {
        return false;
    }

    return true;
}

function filtrarMenuPorPermisos(array $items): array
{
    $menuFiltrado = [];

    foreach ($items as $item) {
        $itemFiltrado = $item;

        if (isset($itemFiltrado['children']) && is_array($itemFiltrado['children'])) {
            $itemFiltrado['children'] = filtrarMenuPorPermisos($itemFiltrado['children']);
        }

        if (!itemMenuVisible($itemFiltrado)) {
            continue;
        }

        if (isset($itemFiltrado['children']) && $itemFiltrado['children'] === []) {
            continue;
        }

        $menuFiltrado[] = $itemFiltrado;
    }

    return $menuFiltrado;
}

function itemMenuActivo(array $item, string $activeKey): bool
{
    if (($item['key'] ?? '') === $activeKey) {
        return true;
    }

    foreach (($item['children'] ?? []) as $child) {
        if (($child['key'] ?? '') === $activeKey) {
            return true;
        }
    }

    return false;
}

function renderAppHeader(string $title): void
{
    ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/css/estilos.css">
</head>
<body class="app-body">
    <?php
}

function renderAppFooter(): void
{
    ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
    <?php
}

function renderAppLayoutStart(string $title, string $activeKey, ?string $pageTitle = null, ?string $pageSubtitle = null): void
{
    $user = obtenerDatosUsuarioActual();
    $rol = obtenerRolUsuarioActual();
    $menu = filtrarMenuPorPermisos(menuLateral());

    renderAppHeader($title);
    ?>
    <div class="app-shell">
        <aside class="sidebar border-end">
            <div class="sidebar-brand">
                <p class="sidebar-kicker">Sistema interno</p>
                <h2>CONGREGACIONES</h2>
            </div>

            <div class="sidebar-user card border-0 shadow-sm">
                <div class="card-body">
                    <p class="sidebar-user-label">Usuario</p>
                    <p class="sidebar-user-name"><?= htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8') ?></p>
                    <?php if ($rol !== ''): ?>
                        <p class="sidebar-user-role"><?= htmlspecialchars($rol, ENT_QUOTES, 'UTF-8') ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <nav class="sidebar-nav" aria-label="Navegacion principal">
                <?php foreach ($menu as $item): ?>
                    <?php $activo = itemMenuActivo($item, $activeKey); ?>
                    <div class="sidebar-group">
                        <?php if (isset($item['href'])): ?>
                            <a class="sidebar-link<?= $activo ? ' active' : '' ?>" href="<?= htmlspecialchars((string) $item['href'], ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars((string) $item['label'], ENT_QUOTES, 'UTF-8') ?>
                            </a>
                        <?php else: ?>
                            <p class="sidebar-section<?= $activo ? ' active' : '' ?>"><?= htmlspecialchars((string) $item['label'], ENT_QUOTES, 'UTF-8') ?></p>
                        <?php endif; ?>

                        <?php foreach (($item['children'] ?? []) as $child): ?>
                            <a class="sidebar-sublink<?= ($child['key'] ?? '') === $activeKey ? ' active' : '' ?>"
                               href="<?= htmlspecialchars((string) $child['href'], ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars((string) $child['label'], ENT_QUOTES, 'UTF-8') ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </nav>
        </aside>

        <div class="content-shell">
            <nav class="topbar navbar navbar-expand-lg d-lg-none bg-white border-bottom">
                <div class="container-fluid px-3">
                    <span class="navbar-brand mb-0 h1">CONGREGACIONES</span>
                    <button class="navbar-toggler" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileSidebar" aria-controls="mobileSidebar" aria-label="Abrir navegacion">
                        <span class="navbar-toggler-icon"></span>
                    </button>
                </div>
            </nav>

            <div class="offcanvas offcanvas-start" tabindex="-1" id="mobileSidebar" aria-labelledby="mobileSidebarLabel">
                <div class="offcanvas-header">
                    <div>
                        <p class="sidebar-kicker mb-1">Sistema interno</p>
                        <h5 class="offcanvas-title" id="mobileSidebarLabel">CONGREGACIONES</h5>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Cerrar"></button>
                </div>
                <div class="offcanvas-body">
                    <div class="mobile-user mb-4">
                        <p class="sidebar-user-label">Usuario</p>
                        <p class="sidebar-user-name"><?= htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8') ?></p>
                        <?php if ($rol !== ''): ?>
                            <p class="sidebar-user-role"><?= htmlspecialchars($rol, ENT_QUOTES, 'UTF-8') ?></p>
                        <?php endif; ?>
                    </div>

                    <nav class="sidebar-nav" aria-label="Navegacion movil">
                        <?php foreach ($menu as $item): ?>
                            <?php $activo = itemMenuActivo($item, $activeKey); ?>
                            <div class="sidebar-group">
                                <?php if (isset($item['href'])): ?>
                                    <a class="sidebar-link<?= $activo ? ' active' : '' ?>" href="<?= htmlspecialchars((string) $item['href'], ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars((string) $item['label'], ENT_QUOTES, 'UTF-8') ?>
                                    </a>
                                <?php else: ?>
                                    <p class="sidebar-section<?= $activo ? ' active' : '' ?>"><?= htmlspecialchars((string) $item['label'], ENT_QUOTES, 'UTF-8') ?></p>
                                <?php endif; ?>

                                <?php foreach (($item['children'] ?? []) as $child): ?>
                                    <a class="sidebar-sublink<?= ($child['key'] ?? '') === $activeKey ? ' active' : '' ?>"
                                       href="<?= htmlspecialchars((string) $child['href'], ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars((string) $child['label'], ENT_QUOTES, 'UTF-8') ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    </nav>
                </div>
            </div>

            <main class="app-main">
                <div class="page-header d-flex flex-column flex-md-row justify-content-between align-items-md-start gap-3">
                    <div>
                        <h1><?= htmlspecialchars($pageTitle ?? $title, ENT_QUOTES, 'UTF-8') ?></h1>
                        <?php if ($pageSubtitle !== null && $pageSubtitle !== ''): ?>
                            <p class="subtitulo mb-0"><?= htmlspecialchars($pageSubtitle, ENT_QUOTES, 'UTF-8') ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="page-header-actions d-flex flex-wrap gap-2">
                        <?php if (puedeCambiarPassword()): ?>
                            <a class="btn btn-outline-primary" href="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/cambiar_password.php">Cambiar contrasena</a>
                        <?php endif; ?>
                        <a class="btn btn-outline-danger" href="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/logout.php">Cerrar sesion</a>
                    </div>
                </div>
    <?php
}

function renderAppLayoutEnd(): void
{
    ?>
            </main>
        </div>
    </div>
    <?php
    renderAppFooter();
}
