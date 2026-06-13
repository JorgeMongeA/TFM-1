<?php

/**
 * Autor: Jorge Monge
 * Trabajo Final de Máster (TFM)
 * UOC - 2026
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/conexion.php';
require_once dirname(__DIR__) . '/app/auth.php';
require_once dirname(__DIR__) . '/app/inventario.php';

require_login();
requierePermiso(PERMISO_INVENTARIO_EDICION);

header('Content-Type: application/json; charset=UTF-8');

$codigoCentro = trim((string) ($_GET['codigo_centro'] ?? ''));
$colegio = trim((string) ($_GET['colegio'] ?? ''));

if ($codigoCentro === '') {
    echo json_encode([
        'ok' => true,
        'has_suggestion' => false,
        'message' => 'No hay ubicaciones sugeridas disponibles',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    $pdo = conectar();
    $sugerencia = obtenerUbicacionSugeridaEntrada($pdo, $codigoCentro, $colegio);

    if ($sugerencia === null) {
        echo json_encode([
            'ok' => true,
            'has_suggestion' => false,
            'message' => 'No hay ubicaciones sugeridas disponibles',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    echo json_encode([
        'ok' => true,
        'has_suggestion' => true,
        'ubicacion' => (string) ($sugerencia['ubicacion'] ?? ''),
        'message' => 'Ubicación sugerida disponible',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'has_suggestion' => false,
        'message' => 'No hay ubicaciones sugeridas disponibles',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
