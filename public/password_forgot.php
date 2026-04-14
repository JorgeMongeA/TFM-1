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

$mensaje = '';
$error = '';
$identificador = '';

try {
    $pdo = conectar();

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $identificador = trim((string) ($_POST['identificador'] ?? ''));
        if ($identificador === '') {
            $error = 'Indica tu usuario o email.';
        } else {
            $resultado = crearSolicitudRecuperacionPassword($pdo, $identificador);
            $mensaje = (string) ($resultado['mensaje'] ?? 'Si el usuario existe, la solicitud se ha registrado correctamente.');
        }
    }
} catch (Throwable $e) {
    $mensaje = 'Si el usuario existe, la solicitud se ha registrado correctamente.';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar contraseña</title>
    <link rel="stylesheet" href="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/css/estilos.css">
</head>
<body class="auth-body">
    <main class="auth-main">
        <section class="auth-card" style="max-width: 520px;">
            <p class="eyebrow">Recuperación</p>
            <h1>Recuperar contraseña</h1>
            <p class="subtitulo">Introduce tu usuario o email. La solicitud quedará registrada para almacén.</p>

            <?php if ($error !== ''): ?>
                <p class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>

            <?php if ($mensaje !== ''): ?>
                <div class="alert alert-success"><?= htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <form method="POST" action="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/password_forgot.php">
                <label for="identificador">Usuario o email</label>
                <input id="identificador" name="identificador" type="text" required value="<?= htmlspecialchars($identificador, ENT_QUOTES, 'UTF-8') ?>">
                <button class="btn-primary" type="submit">Enviar solicitud</button>
            </form>

            <div class="mt-3">
                <a href="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/login.php">Volver al login</a>
            </div>
        </section>
    </main>
</body>
</html>
