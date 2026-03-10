<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/auth.php';

if (!empty($_SESSION['usuario'])) {
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit;
}

$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $username = (string) ($_POST['username'] ?? '');
    $password = (string) ($_POST['password'] ?? '');

    if (login($username, $password)) {
        header('Location: ' . BASE_URL . '/dashboard.php');
        exit;
    }

    $error = 'Usuario o contrasena incorrectos';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link rel="stylesheet" href="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/css/estilos.css">
</head>
<body>
    <main class="contenedor">
        <section class="tarjeta">
            <h1>Iniciar sesion</h1>
            <p class="subtitulo">Acceso al sistema TFM</p>

            <?php if ($error !== ''): ?>
                <p class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>

            <form method="POST" action="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/login.php">
                <label for="username">Usuario</label>
                <input id="username" name="username" type="text" required>

                <label for="password">Contrasena</label>
                <input id="password" name="password" type="password" required>

                <button type="submit">Entrar</button>
            </form>
        </section>
    </main>
</body>
</html>
