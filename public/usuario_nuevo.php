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

try {
    $pdo = conectar();
    $roles = rolesAsignablesUsuarios($pdo);

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $datos = leerFormularioUsuarioDesdeRequest($_POST);
        crearSolicitudUsuario($pdo, $datos);
        $mensaje = 'Tu solicitud de acceso se ha registrado correctamente. Queda pendiente de aprobacion por almacen.';

        $datos = [
            'username' => '',
            'email' => '',
            'password' => '',
            'password_confirmacion' => '',
            'rol_id' => 0,
        ];
    }
} catch (Throwable $e) {
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
