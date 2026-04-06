<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/conexion.php';
require_once dirname(__DIR__) . '/app/auth.php';
require_once dirname(__DIR__) . '/app/inventario_sync_historico.php';

require_login();

iniciar_sesion();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Location: ' . BASE_URL . '/inventario_consulta.php');
    exit;
}

try {
    $config = cargarConfiguracion();
    $scriptUrl = obtenerUrlWebAppGoogleSheets($config);
    $token = obtenerTokenSyncGoogleSheets();

    $pdo = conectar();
    $resultado = sincronizarHistoricoConGoogleSheets($pdo, $scriptUrl, $token);

    $_SESSION['flash_sync_historico'] = [
        'ok' => true,
        'resultado' => $resultado,
    ];
} catch (Throwable $e) {
    error_log('[GOOGLE_SYNC] sync_historico.php | ' . $e->getMessage());
    $_SESSION['flash_sync_historico'] = [
        'ok' => false,
        'mensaje' => 'No se ha podido completar la sincronizacion del historico con Google Sheets.',
    ];
}

header('Location: ' . BASE_URL . '/inventario_consulta.php');
exit;
