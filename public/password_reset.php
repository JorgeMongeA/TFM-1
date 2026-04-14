<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/conexion.php';
require_once dirname(__DIR__) . '/app/auth.php';
require_once dirname(__DIR__) . '/app/password_reset.php';

iniciar_sesion();

if (isset($_SESSION['user_id'])) {
    header('Location: ' . urlInicioSegunPermisos());
    exit;
}

$token = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));
$error = '';
$mensaje = '';
$resetValido = null;

try {
    $pdo = conectar();

    if ($token === '') {
        $error = 'El enlace de recuperacion no es valido.';
    } else {
        $resetValido = obtenerResetPasswordValido($pdo, $token);

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $passwordNueva = (string) ($_POST['password_nueva'] ?? '');
            $passwordConfirmacion = (string) ($_POST['password_confirmacion'] ?? '');
            completarResetPassword($pdo, $token, $passwordNueva, $passwordConfirmacion);
            $mensaje = 'La contrasena se ha restablecido correctamente. Ya puedes iniciar sesion.';
            $resetValido = null;
        } elseif ($resetValido === null) {
            $error = 'El enlace de recuperacion no es valido o ha caducado.';
        }
    }
} catch (Throwable $e) {
    $mensajeError = trim($e->getMessage());
    $error = $mensajeError !== '' ? $mensajeError : 'No se ha podido restablecer la contrasena.';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restablecer contrasena</title>
    <link rel="stylesheet" href="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/css/estilos.css">
</head>
<body class="auth-body">
    <main class="auth-main">
        <section class="auth-card" style="max-width: 520px;">
            <p class="eyebrow">Acceso</p>
            <h1>Restablecer contrasena</h1>
            <p class="subtitulo">El enlace es temporal y solo puede utilizarse una vez.</p>

            <?php if ($error !== ''): ?>
                <p class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>

            <?php if ($mensaje !== ''): ?>
                <div class="alert alert-success">
                    <?= htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8') ?>
                    <div class="mt-2">
                        <a href="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/login.php">Ir al login</a>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($mensaje === '' && $resetValido !== null): ?>
                <form method="POST" action="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/password_reset.php">
                    <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">

                    <label for="password_nueva">Nueva contrasena</label>
                    <input id="password_nueva" name="password_nueva" type="password" required>

                    <label for="password_confirmacion">Confirmar nueva contrasena</label>
                    <input id="password_confirmacion" name="password_confirmacion" type="password" required>

                    <button class="btn-primary" type="submit">Guardar nueva contrasena</button>
                </form>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>
