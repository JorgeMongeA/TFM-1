<?php

/**
 * Autor: Jorge Monge
 * Trabajo Final de Máster (TFM)
 * UOC - 2026
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/conexion.php';
require_once dirname(__DIR__) . '/app/auth.php';

iniciar_sesion();

if (isset($_SESSION['user_id'])) {
    header('Location: ' . urlInicioSegunPermisos());
    exit;
}

$error = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $username = trim((string) ($_POST['username'] ?? $_POST['usuario'] ?? ''));
    $password = (string) ($_POST['password'] ?? $_POST['contrasena'] ?? '');

    try {
        if (login($username, $password)) {
            header('Location: ' . urlInicioSegunPermisos());
            exit;
        }

        $error = 'Usuario o contraseña incorrectos.';
    } catch (RuntimeException $e) {
        $mensajeError = trim($e->getMessage());
        $error = $mensajeError !== '' ? $mensajeError : 'Usuario o contraseña incorrectos.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso a CONGREGACIONES</title>
    <link rel="stylesheet" href="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/css/estilos.css">
</head>
<body class="auth-body">
    <main class="auth-main">
        <section class="auth-card">
            <h1>Acceso a CONGREGACIONES</h1>
            <p class="subtitulo">Sistema de gestion de inventario y operaciones</p>

            <?php if ($error !== ''): ?>
                <p class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>

            <form method="POST" action="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/login.php">
                <label for="username">Usuario</label>
                <input
                    id="username"
                    name="username"
                    type="text"
                    required
                    value="<?= htmlspecialchars((string) ($_POST['username'] ?? $_POST['usuario'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                >

                <label for="password">Contraseña</label>
                <input id="password" name="password" type="password" required>

                <button class="btn-primary" type="submit">Entrar</button>
            </form>

            <div class="mt-3">
                <a href="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/password_forgot.php">¿Has olvidado tu contraseña?</a>
            </div>

            <div class="mt-2">
                <a href="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/usuario_nuevo.php">Solicitar una nueva cuenta</a>
            </div>
        </section>
    </main>
</body>
</html>
