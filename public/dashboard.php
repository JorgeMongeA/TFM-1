<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/auth.php';
require_login();

$username = (string) ($_SESSION['username'] ?? '');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link rel="stylesheet" href="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/css/estilos.css">
</head>
<body class="app-body">
    <header class="topbar">
        <div class="topbar-inner">
            <p class="brand">CONGREGACIONES</p>
            <nav class="main-nav">
                <a class="nav-link activo" href="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/dashboard.php">Dashboard</a>
                <a class="nav-link" href="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/inventario.php">Inventario</a>
                <a class="nav-link" href="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/entrada.php">Nueva entrada</a>
                <a class="nav-link salir" href="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/logout.php">Cerrar sesión</a>
            </nav>
            <p class="topbar-user">Usuario: <strong><?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?></strong></p>
        </div>
    </header>

    <main class="app-main">
        <section class="panel">
            <h1>Dashboard</h1>
            <p class="subtitulo">Panel principal de control y seguimiento</p>
            <p class="texto">Bienvenido, <strong><?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?></strong></p>
        </section>
    </main>
</body>
</html>
