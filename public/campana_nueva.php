<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/conexion.php';
require_once dirname(__DIR__) . '/app/auth.php';
require_once dirname(__DIR__) . '/app/confirmacion_fuerte.php';
require_once dirname(__DIR__) . '/app/sistema_reset.php';

require_login();
requierePermiso(PERMISO_CAMPANAS, 'No tienes permisos para iniciar una nueva campaña.');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit;
}

try {
    $pdo = conectar();
    validarConfirmacionFuerte($pdo, (int) ($_SESSION['user_id'] ?? 0), [
        'confirmado' => true,
        'password_actual' => (string) ($_POST['password_actual'] ?? ''),
        'frase' => '',
    ], [
        'requiere_checkbox' => false,
        'requiere_password' => true,
    ]);

    reiniciarSistemaCompleto($pdo, [
        'user_id' => (int) ($_SESSION['user_id'] ?? 0),
        'username' => (string) ($_SESSION['username'] ?? $_SESSION['usuario'] ?? ''),
    ]);

    $_SESSION['flash_sistema'] = ['ok' => true, 'mensaje' => 'Sistema reiniciado correctamente'];
} catch (Throwable $e) {
    $mensajeError = trim($e->getMessage());
    $_SESSION['flash_sistema'] = [
        'ok' => false,
        'mensaje' => $mensajeError !== '' ? $mensajeError : 'No se ha podido reiniciar el sistema.',
    ];
}

header('Location: ' . BASE_URL . '/dashboard.php');
exit;
