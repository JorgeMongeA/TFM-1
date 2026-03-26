<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/conexion.php';
require_once dirname(__DIR__) . '/app/auth.php';

iniciar_sesion();

if (isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit;
}

$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = trim((string) ($_POST['password'] ?? ''));

    try {
        if (login($username, $password)) {
            header('Location: ' . BASE_URL . '/dashboard.php');
            exit;
        }
        $error = 'Usuario o contraseña incorrectos';
    } catch (RuntimeException $e) {
        $error = 'Error de conexión con la base de datos';
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
            <p class="subtitulo">Sistema de gestión de inventario y operaciones</p>

            <?php if ($error !== ''): ?>
                <p class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>

            <form method="POST" action="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/login.php">
                <label for="username">Usuario</label>
                <input id="username" name="username" type="text" required>

                <label for="password">Contraseña</label>
                <input id="password" name="password" type="password" required>

                <button class="btn-primary" type="submit">Entrar</button>
            </form>
        </section>
    </main>
</body>
</html>
