<?php

/**
 * Autor: Jorge Monge
 * Trabajo Final de Master (TFM)
 * UOC - 2026
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/conexion.php';
require_once dirname(__DIR__) . '/app/auth.php';
require_once dirname(__DIR__) . '/app/inventario.php';

function responderJsonInventarioAutocomplete(array $payload, int $statusCode = 200): never
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    echo $json !== false ? $json : '{"success":false,"message":"No se pudo generar la respuesta JSON."}';
    exit;
}

try {
    iniciar_sesion();

    if (!isset($_SESSION['user_id'])) {
        responderJsonInventarioAutocomplete([
            'success' => false,
            'message' => 'Sesion no iniciada.',
        ], 401);
    }

    if (!usuarioTienePermiso(PERMISO_INVENTARIO_CONSULTA) && !usuarioTienePermiso(PERMISO_PEDIDOS)) {
        responderJsonInventarioAutocomplete([
            'success' => false,
            'message' => 'No tienes permisos para consultar el inventario.',
        ], 403);
    }

    $campo = trim((string) ($_GET['campo'] ?? ''));
    if (!array_key_exists($campo, camposInventarioAutocompletePermitidos())) {
        responderJsonInventarioAutocomplete([
            'success' => false,
            'message' => 'Campo no permitido.',
            'results' => [],
        ], 400);
    }

    $q = trim((string) ($_GET['q'] ?? ''));
    $limite = (int) ($_GET['limit'] ?? 10);
    $pdo = conectar();
    $valores = buscarValoresInventarioActivoAutocomplete($pdo, $campo, $q, $limite);
    $results = [];

    foreach ($valores as $fila) {
        $value = trim((string) ($fila['value'] ?? ''));
        if ($value === '') {
            continue;
        }

        $total = (int) ($fila['total'] ?? 0);
        $results[] = [
            'value' => $value,
            'label' => $value,
            'meta' => $total === 1 ? '1 linea activa' : $total . ' lineas activas',
        ];
    }

    responderJsonInventarioAutocomplete([
        'success' => true,
        'results' => $results,
    ]);
} catch (Throwable $e) {
    responderJsonInventarioAutocomplete([
        'success' => false,
        'message' => 'No se pudieron buscar sugerencias.',
        'results' => [],
    ], 500);
}
