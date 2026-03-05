<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/auth.php';
require_login();

$username = (string) ($_SESSION['user'] ?? '');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link rel="stylesheet" href="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/css/estilos.css">
</head>
<body>
    <main class="contenedor">
        <section class="tarjeta">
            <h1>Dashboard</h1>
            <p>Bienvenido, <?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?></p>
            <a class="boton-secundario" href="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/logout.php">Cerrar sesion</a>
        </section>
    </main>
</body>
</html>
