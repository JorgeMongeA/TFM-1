<?php

/**
 * Autor: Jorge Monge
 * Trabajo Final de Máster (TFM)
 * UOC - 2026
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/conexion.php';
require_once dirname(__DIR__) . '/app/actividad.php';
require_once dirname(__DIR__) . '/app/auth.php';
require_once dirname(__DIR__) . '/app/inventario_sync_historico.php';

require_login();
requierePermiso(PERMISO_SINCRONIZACIONES, 'No tienes permisos para sincronizar el historico.');

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
    $usuario = obtenerContextoActividadActual();

    registrarActividadSistema($pdo, [
        'usuario_id' => isset($usuario['user_id']) ? (int) $usuario['user_id'] : null,
        'usuario' => (string) ($usuario['username'] ?? ''),
        'tipo_evento' => ACTIVIDAD_TIPO_SYNC_HISTORICO,
        'entidad' => 'historico',
        'descripcion' => 'Sincronizacion de historico con Google Sheets',
        'metadata' => [
            'insertados_historico' => (int) ($resultado['insertados_historico'] ?? 0),
            'ya_existian_historico' => (int) ($resultado['ya_existian_historico'] ?? 0),
        ],
    ]);

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
