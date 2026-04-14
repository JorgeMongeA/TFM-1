<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/conexion.php';
require_once dirname(__DIR__) . '/app/auth.php';
require_once dirname(__DIR__) . '/app/usuarios.php';

iniciar_sesion();

if (isset($_SESSION['user_id'])) {
    header('Location: ' . urlInicioSegunPermisos());
    exit;
}

$error = '';
$mensaje = '';
$datos = [
    'username' => '',
    'email' => '',
    'password' => '',
    'password_confirmacion' => '',
    'rol_id' => 0,
];
$roles = [];
$debugAlta = [
    'post_recibido' => false,
    'validacion_username' => null,
    'validacion_email' => null,
    'validacion_password' => null,
    'rol_solicitado' => null,
    'intento_insert' => false,
    'insert_correcto' => false,
    'id_insertado' => '',
    'sql_exception' => '',
    'valores_guardados' => [
        'activo' => '',
        'aprobado' => '',
        'rechazado' => '',
        'rol_id' => '',
    ],
    'columnas_usuarios' => [],
];

try {
    $pdo = conectar();
    $roles = rolesAsignablesUsuarios($pdo);
    $debugAlta['columnas_usuarios'] = detalleColumnasUsuarios($pdo);

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $debugAlta['post_recibido'] = true;
        $datos = leerFormularioUsuarioDesdeRequest($_POST);
        $debugAlta['rol_solicitado'] = $datos['rol_id'];
        error_log('[USUARIOS] POST usuario_nuevo.php => ' . json_encode([
            'username' => $datos['username'],
            'email' => $datos['email'],
            'rol_id' => $datos['rol_id'],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $diagnosticoValidacion = diagnosticoValidacionNuevoUsuario($pdo, $datos);
        $debugAlta['validacion_username'] = (bool) ($diagnosticoValidacion['username']['ok'] ?? false);
        $debugAlta['validacion_email'] = (bool) ($diagnosticoValidacion['email']['ok'] ?? false);
        $debugAlta['validacion_password'] = (bool) ($diagnosticoValidacion['password']['ok'] ?? false);

        if (($diagnosticoValidacion['errores'] ?? []) === []) {
            $debugAlta['intento_insert'] = true;

            try {
                $usuarioCreado = crearSolicitudUsuario($pdo, $datos);
                $debugAlta['insert_correcto'] = true;
                $debugAlta['id_insertado'] = (string) ($usuarioCreado['id'] ?? '');
                $debugAlta['valores_guardados'] = [
                    'activo' => (string) ($usuarioCreado['activo'] ?? ''),
                    'aprobado' => (string) ($usuarioCreado['aprobado'] ?? ''),
                    'rechazado' => (string) ($usuarioCreado['rechazado'] ?? ''),
                    'rol_id' => (string) ($usuarioCreado['rol_id'] ?? ''),
                ];
                $mensaje = 'Tu solicitud de acceso se ha registrado correctamente. Queda pendiente de aprobacion por almacen.';
            } catch (Throwable $e) {
                $debugAlta['insert_correcto'] = false;
                $debugAlta['sql_exception'] = $e->getMessage();
                error_log('[USUARIOS] usuario_nuevo.php insert error => ' . $e->getMessage());
                throw $e;
            }
        } else {
            $error = implode(' ', $diagnosticoValidacion['errores']);
        }
    }
} catch (Throwable $e) {
    error_log('[USUARIOS] usuario_nuevo.php error => ' . $e->getMessage());
    $mensajeError = trim($e->getMessage());
    $error = $mensajeError !== '' ? $mensajeError : 'No se ha podido registrar la solicitud de usuario.';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Solicitar acceso</title>
    <link rel="stylesheet" href="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/css/estilos.css">
</head>
<body class="auth-body">
    <main class="auth-main">
        <section class="auth-card" style="max-width: 560px;">
            <p class="eyebrow">Nueva cuenta</p>
            <h1>Solicitar acceso</h1>
            <p class="subtitulo">Tu cuenta quedará pendiente de aprobación por almacén.</p>

            <?php if ($error !== ''): ?>
                <p class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>

            <?php if ($mensaje !== ''): ?>
                <div class="alert alert-success"><?= htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <?php if ($debugAlta['post_recibido'] === true): ?>
                <div class="alert alert-warning text-start">
                    <strong>Diagnostico temporal de alta</strong><br>
                    POST recibido: <?= $debugAlta['post_recibido'] ? 'si' : 'no' ?><br>
                    Validacion username: <?= $debugAlta['validacion_username'] === null ? '-' : ($debugAlta['validacion_username'] ? 'ok' : 'fallo') ?><br>
                    Validacion email: <?= $debugAlta['validacion_email'] === null ? '-' : ($debugAlta['validacion_email'] ? 'ok' : 'fallo') ?><br>
                    Validacion password: <?= $debugAlta['validacion_password'] === null ? '-' : ($debugAlta['validacion_password'] ? 'ok' : 'fallo') ?><br>
                    Rol solicitado recibido: <?= htmlspecialchars((string) ($debugAlta['rol_solicitado'] ?? ''), ENT_QUOTES, 'UTF-8') ?><br>
                    Intento de insert ejecutado: <?= $debugAlta['intento_insert'] ? 'si' : 'no' ?><br>
                    Insert correcto: <?= $debugAlta['insert_correcto'] ? 'si' : 'no' ?><br>
                    ID insertado: <?= htmlspecialchars((string) ($debugAlta['id_insertado'] ?? ''), ENT_QUOTES, 'UTF-8') ?><br>
                    Mensaje exacto excepcion SQL: <?= htmlspecialchars((string) ($debugAlta['sql_exception'] ?? ''), ENT_QUOTES, 'UTF-8') ?><br>
                    Valores finales guardados:
                    activo=<?= htmlspecialchars((string) ($debugAlta['valores_guardados']['activo'] ?? ''), ENT_QUOTES, 'UTF-8') ?>,
                    aprobado=<?= htmlspecialchars((string) ($debugAlta['valores_guardados']['aprobado'] ?? ''), ENT_QUOTES, 'UTF-8') ?>,
                    rechazado=<?= htmlspecialchars((string) ($debugAlta['valores_guardados']['rechazado'] ?? ''), ENT_QUOTES, 'UTF-8') ?>,
                    rol_id=<?= htmlspecialchars((string) ($debugAlta['valores_guardados']['rol_id'] ?? ''), ENT_QUOTES, 'UTF-8') ?><br>
                    Columnas reales en usuarios:
                    <?= htmlspecialchars(implode(', ', array_map(static fn(array $columna): string => (string) ($columna['Field'] ?? ''), $debugAlta['columnas_usuarios'])), ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/usuario_nuevo.php" class="row g-3">
                <div class="col-12 col-md-6">
                    <label for="username">Usuario</label>
                    <input id="username" name="username" type="text" required value="<?= htmlspecialchars($datos['username'], ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="col-12 col-md-6">
                    <label for="email">Email</label>
                    <input id="email" name="email" type="email" required value="<?= htmlspecialchars($datos['email'], ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="col-12">
                    <label for="rol_id">Rol solicitado</label>
                    <select id="rol_id" name="rol_id" required>
                        <option value="">Selecciona un rol</option>
                        <?php foreach ($roles as $rol): ?>
                            <option value="<?= htmlspecialchars((string) ($rol['id'] ?? 0), ENT_QUOTES, 'UTF-8') ?>"<?= (int) $datos['rol_id'] === (int) ($rol['id'] ?? 0) ? ' selected' : '' ?>>
                                <?= htmlspecialchars(etiquetaRolUsuario(normalizarRolAplicacion((string) ($rol['nombre'] ?? ''))), ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-md-6">
                    <label for="password">Contraseña</label>
                    <input id="password" name="password" type="password" required>
                </div>
                <div class="col-12 col-md-6">
                    <label for="password_confirmacion">Confirmar contraseña</label>
                    <input id="password_confirmacion" name="password_confirmacion" type="password" required>
                </div>
                <div class="col-12 d-flex flex-wrap gap-2">
                    <button class="btn-primary" type="submit">Enviar solicitud</button>
                    <a class="btn btn-outline-secondary" href="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/login.php">Volver al login</a>
                </div>
            </form>
        </section>
    </main>
</body>
</html>
