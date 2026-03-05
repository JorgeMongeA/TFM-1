<?php

declare(strict_types=1);

$username = $_SESSION['user']['username'] ?? 'usuario';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - TFM-1</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="card shadow-sm">
            <div class="card-body p-4 d-flex justify-content-between align-items-center">
                <h1 class="h4 m-0">Bienvenido, <?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?></h1>
                <a href="/logout" class="btn btn-outline-danger">Logout</a>
            </div>
        </div>
    </div>
</body>
</html>
