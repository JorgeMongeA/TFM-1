<?php

/**
 * Autor: Jorge Monge
 * Trabajo Final de Master (TFM)
 * UOC - 2026
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/conexion.php';
require_once dirname(__DIR__) . '/app/auth.php';
require_once dirname(__DIR__) . '/app/centros.php';

function responderJsonCentros(array $payload, int $statusCode = 200): never
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
        responderJsonCentros([
            'success' => false,
            'message' => 'Sesion no iniciada.',
        ], 401);
    }

    if (!usuarioTienePermiso(PERMISO_INVENTARIO_EDICION) && !usuarioTienePermiso(PERMISO_CENTROS_CONSULTA)) {
        responderJsonCentros([
            'success' => false,
            'message' => 'No tienes permisos para buscar centros.',
        ], 403);
    }

    $q = trim((string) ($_GET['q'] ?? ''));
    $limite = (int) ($_GET['limit'] ?? 20);
    $pdo = conectar();
    $centros = buscarCentrosParaAutocomplete($pdo, $q, $limite);
    $results = [];

    foreach ($centros as $centro) {
        $codigoCentro = (string) ($centro['codigo_centro'] ?? '');
        $nombreCentro = (string) ($centro['nombre_centro'] ?? '');
        $localidad = (string) ($centro['ciudad'] ?? '');
        $destino = normalizarDestinoCentro($centro['destino'] ?? '');

        $results[] = [
            'codigo_centro' => $codigoCentro,
            'nombre_centro' => $nombreCentro,
            'localidad' => $localidad,
            'destino' => $destino,
            'label' => construirEtiquetaCentroBusqueda($centro),
        ];
    }

    responderJsonCentros([
        'success' => true,
        'results' => $results,
    ]);
} catch (Throwable $e) {
    responderJsonCentros([
        'success' => false,
        'message' => 'No se pudieron buscar centros.',
    ], 500);
}
