<?php

declare(strict_types=1);

require_once __DIR__ . '/conexion.php';

const ROL_ALMACEN = 'almacen';
const ROL_EDELVIVES = 'edelvives';

const PERMISO_DASHBOARD = 'dashboard';
const PERMISO_INVENTARIO_CONSULTA = 'inventario_consulta';
const PERMISO_INVENTARIO_EDICION = 'inventario_edicion';
const PERMISO_INVENTARIO_SALIDA = 'inventario_salida';
const PERMISO_ETIQUETAS = 'etiquetas';
const PERMISO_ALBARANES = 'albaranes';
const PERMISO_HISTORICO = 'historico';
const PERMISO_SINCRONIZACIONES = 'sincronizaciones';
const PERMISO_CENTROS_CONSULTA = 'centros_consulta';
const PERMISO_CENTROS_EDICION = 'centros_edicion';
const PERMISO_PEDIDOS = 'pedidos';
const PERMISO_CAMBIAR_PASSWORD = 'cambiar_password';
const PERMISO_USUARIOS = 'usuarios';
const PERMISO_CAMPANAS = 'campanas';

function aliasesRolesAplicacion(): array
{
    return [
        // Compatibilidad con roles legacy en BD: editor = almacen, consultor = edelvives.
        'admin' => ROL_ALMACEN,
        'operaciones' => ROL_ALMACEN,
        'editor' => ROL_ALMACEN,
        'cliente' => ROL_EDELVIVES,
        'consultor' => ROL_EDELVIVES,
        ROL_ALMACEN => ROL_ALMACEN,
        ROL_EDELVIVES => ROL_EDELVIVES,
    ];
}

function normalizarRolAplicacion(?string $rol): string
{
    $rolNormalizado = strtolower(trim((string) $rol));

    if ($rolNormalizado === '') {
        return '';
    }

    return aliasesRolesAplicacion()[$rolNormalizado] ?? '';
}

function permisosPorRol(): array
{
    return [
        ROL_ALMACEN => [
            PERMISO_DASHBOARD,
            PERMISO_INVENTARIO_CONSULTA,
            PERMISO_INVENTARIO_EDICION,
            PERMISO_INVENTARIO_SALIDA,
            PERMISO_ETIQUETAS,
            PERMISO_ALBARANES,
            PERMISO_HISTORICO,
            PERMISO_SINCRONIZACIONES,
            PERMISO_CENTROS_CONSULTA,
            PERMISO_CENTROS_EDICION,
            PERMISO_PEDIDOS,
            PERMISO_CAMBIAR_PASSWORD,
            PERMISO_USUARIOS,
            PERMISO_CAMPANAS,
        ],
        ROL_EDELVIVES => [
            PERMISO_DASHBOARD,
            PERMISO_INVENTARIO_CONSULTA,
            PERMISO_HISTORICO,
            PERMISO_PEDIDOS,
            PERMISO_CAMBIAR_PASSWORD,
        ],
    ];
}

function etiquetaRolUsuario(string $rol): string
{
    return match ($rol) {
        ROL_ALMACEN => 'Almacen',
        ROL_EDELVIVES => 'Edelvives',
        default => '',
    };
}

function iniciar_sesion(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

function validarPasswordLogin(string $passwordEntrada, string $passwordPersistida): bool
{
    $info = password_get_info($passwordPersistida);
    $algo = (string) ($info['algoName'] ?? 'unknown');

    if ($algo !== '' && $algo !== 'unknown') {
        return password_verify($passwordEntrada, $passwordPersistida);
    }

    return hash_equals($passwordPersistida, $passwordEntrada);
}

function login(string $username, string $password): bool
{
    iniciar_sesion();

    $username = trim($username);

    if ($username === '' || $password === '') {
        return false;
    }

    $pdo = conectar();
    $columnasExtras = '';
    if (usuariosSoportanControlAcceso($pdo)) {
        $columnasExtras = ', u.activo, u.aprobado';
    }

    $stmt = $pdo->prepare(
        'SELECT u.id, u.username, u.password, u.rol_id' . $columnasExtras . ', r.nombre AS rol_nombre
         FROM usuarios u
         LEFT JOIN roles r ON r.id = u.rol_id
         WHERE u.username = :username
         LIMIT 1'
    );
    $stmt->execute(['username' => $username]);
    $user = $stmt->fetch();

    if (!$user) {
        return false;
    }

    $passwordPersistida = (string) ($user['password'] ?? '');
    if (!validarPasswordLogin($password, $passwordPersistida)) {
        return false;
    }

    if ((int) ($user['aprobado'] ?? 1) !== 1) {
        throw new RuntimeException('Tu cuenta esta pendiente de aprobacion.');
    }

    if ((int) ($user['activo'] ?? 1) !== 1) {
        throw new RuntimeException('Tu cuenta esta desactivada.');
    }

    $rol = normalizarRolAplicacion((string) ($user['rol_nombre'] ?? ''));
    if ($rol === '') {
        return false;
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['username'] = (string) $user['username'];
    $_SESSION['usuario'] = (string) $user['username'];
    $_SESSION['rol_id'] = (int) ($user['rol_id'] ?? 0);
    $_SESSION['rol'] = $rol;

    return true;
}

function require_login(): void
{
    iniciar_sesion();

    if (!isset($_SESSION['user_id'])) {
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
}

function obtenerRolUsuario(): string
{
    iniciar_sesion();

    $rol = normalizarRolAplicacion((string) ($_SESSION['rol'] ?? ''));
    if ($rol !== '') {
        return $rol;
    }

    $rolId = (int) ($_SESSION['rol_id'] ?? 0);
    if ($rolId <= 0) {
        return '';
    }

    try {
        $pdo = conectar();
        $stmt = $pdo->prepare('SELECT nombre FROM roles WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $rolId]);
        $rol = normalizarRolAplicacion((string) ($stmt->fetchColumn() ?: ''));

        if ($rol !== '') {
            $_SESSION['rol'] = $rol;
        }

        return $rol;
    } catch (Throwable $e) {
        return '';
    }
}

function obtenerEtiquetaRolUsuario(): string
{
    return etiquetaRolUsuario(obtenerRolUsuario());
}

function usuarioEsAlmacen(): bool
{
    return obtenerRolUsuario() === ROL_ALMACEN;
}

function usuarioEsEdelvives(): bool
{
    return obtenerRolUsuario() === ROL_EDELVIVES;
}

function usuarioTienePermiso(string $permiso): bool
{
    $rol = obtenerRolUsuario();
    $permisosRol = permisosPorRol()[$rol] ?? [];

    return in_array($permiso, $permisosRol, true);
}

function puedeVerDashboard(): bool
{
    return usuarioTienePermiso(PERMISO_DASHBOARD);
}

function puedeVerInventario(): bool
{
    return usuarioTienePermiso(PERMISO_INVENTARIO_CONSULTA);
}

function puedeEditarInventario(): bool
{
    return usuarioTienePermiso(PERMISO_INVENTARIO_EDICION);
}

function puedeGestionarSalidas(): bool
{
    return usuarioTienePermiso(PERMISO_INVENTARIO_SALIDA);
}

function puedeGenerarEtiquetas(): bool
{
    return usuarioTienePermiso(PERMISO_ETIQUETAS);
}

function puedeGestionarAlbaranes(): bool
{
    return usuarioTienePermiso(PERMISO_ALBARANES);
}

function puedeAccederHistorico(): bool
{
    return usuarioTienePermiso(PERMISO_HISTORICO);
}

function puedeSincronizar(): bool
{
    return usuarioTienePermiso(PERMISO_SINCRONIZACIONES);
}

function puedeAccederCentros(): bool
{
    return usuarioTienePermiso(PERMISO_CENTROS_CONSULTA);
}

function puedeEditarCentros(): bool
{
    return usuarioTienePermiso(PERMISO_CENTROS_EDICION);
}

function puedeAccederPedidos(): bool
{
    return usuarioTienePermiso(PERMISO_PEDIDOS);
}

function puedeCrearPedidos(): bool
{
    return puedeAccederPedidos() && usuarioEsEdelvives();
}

function puedeGestionarPedidos(): bool
{
    return puedeAccederPedidos() && usuarioEsAlmacen();
}

function puedeCambiarPassword(): bool
{
    return usuarioTienePermiso(PERMISO_CAMBIAR_PASSWORD);
}

function puedeGestionarUsuarios(): bool
{
    return usuarioTienePermiso(PERMISO_USUARIOS) && usuarioEsAlmacen();
}

function puedePrepararCampanas(): bool
{
    return usuarioTienePermiso(PERMISO_CAMPANAS) && usuarioEsAlmacen();
}

function urlInicioSegunPermisos(): string
{
    if (puedeVerDashboard()) {
        return BASE_URL . '/dashboard.php';
    }

    if (puedeVerInventario()) {
        return BASE_URL . '/inventario_consulta.php';
    }

    return BASE_URL . '/logout.php';
}

function renderizarAccesoDenegado(string $mensaje = 'No tienes permisos para acceder a esta seccion.'): void
{
    iniciar_sesion();
    http_response_code(403);

    $username = trim((string) ($_SESSION['username'] ?? $_SESSION['usuario'] ?? ''));
    $rol = obtenerEtiquetaRolUsuario();
    $urlVolver = urlInicioSegunPermisos();
    ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso denegado</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/css/estilos.css">
</head>
<body class="auth-body">
    <main class="auth-main">
        <section class="auth-card">
            <p class="eyebrow">Acceso restringido</p>
            <h1 class="mb-2">No tienes permisos</h1>
            <p class="subtitulo mb-4"><?= htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8') ?></p>

            <?php if ($username !== ''): ?>
                <div class="alert alert-light border">
                    <strong>Usuario:</strong> <?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?><br>
                    <strong>Rol:</strong> <?= htmlspecialchars($rol !== '' ? $rol : 'No definido', ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <div class="d-grid gap-2">
                <a class="btn btn-primary" href="<?= htmlspecialchars($urlVolver, ENT_QUOTES, 'UTF-8') ?>">Volver</a>
                <a class="btn btn-outline-secondary" href="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/logout.php">Cerrar sesion</a>
            </div>
        </section>
    </main>
</body>
</html>
    <?php
    exit;
}

function requiereRol(string ...$roles): void
{
    require_login();

    $rolesPermitidos = array_values(array_filter(array_map(
        static fn(string $rol): string => normalizarRolAplicacion($rol),
        $roles
    )));

    if ($rolesPermitidos === []) {
        return;
    }

    if (!in_array(obtenerRolUsuario(), $rolesPermitidos, true)) {
        renderizarAccesoDenegado();
    }
}

function requierePermiso(string $permiso, string $mensaje = 'No tienes permisos para acceder a esta seccion.'): void
{
    require_login();

    if (!usuarioTienePermiso($permiso)) {
        renderizarAccesoDenegado($mensaje);
    }
}

function logout(): void
{
    iniciar_sesion();
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

function usuariosSoportanControlAcceso(PDO $pdo): bool
{
    static $cache = null;

    if (is_bool($cache)) {
        return $cache;
    }

    try {
        $stmt = $pdo->query('SHOW COLUMNS FROM usuarios');
        $columnas = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
        if (!is_array($columnas)) {
            $cache = false;
            return $cache;
        }

        $cache = in_array('activo', $columnas, true) && in_array('aprobado', $columnas, true);
        return $cache;
    } catch (Throwable $e) {
        $cache = false;
        return $cache;
    }
}
