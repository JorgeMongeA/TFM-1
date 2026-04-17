<?php

/**
 * Autor: Jorge Monge
 * Trabajo Final de Máster (TFM)
 * UOC - 2026
 */

declare(strict_types=1);

require_once __DIR__ . '/inventario.php';
require_once __DIR__ . '/inventario_sync_bidireccional.php';

function sincronizarHistoricoConGoogleSheets(PDO $pdo, string $scriptUrl, string $token): array
{
    $pendientesHistorico = obtenerHistoricoPendienteParaGoogleSheets($pdo);
    $inventarioActivoSql = obtenerInventarioSql($pdo);

    $respuestaInventario = llamarAppsScriptInventario($scriptUrl, $token, 'get_inventory');
    $respuestaHistorico = llamarAppsScriptInventario($scriptUrl, $token, 'get_history');

    $inventarioSheet = extraerFilasInventarioCompletoDesdeAppsScript($respuestaInventario);
    $historicoSheet = extraerFilasHistoricoDesdeAppsScript($respuestaHistorico);

    $inventarioIndexado = indexarFilasSheetsPorId($inventarioSheet);
    $historicoIndexado = indexarFilasSheetsPorId($historicoSheet);

    $pendientesIds = [];
    $historicoParaInsertar = [];
    $idsYaExistentes = [];

    foreach ($pendientesHistorico as $fila) {
        $id = trim((string) ($fila['id'] ?? ''));
        if ($id === '') {
            continue;
        }

        $pendientesIds[] = $id;

        if (isset($historicoIndexado[$id])) {
            $idsYaExistentes[] = $id;
            continue;
        }

        $historicoParaInsertar[] = $fila;
    }

    $inventarioReconstruido = reconstruirFilasInventarioSheets($inventarioActivoSql, $inventarioIndexado);

    $respuestaHistoricoUpsert = [
        'success' => true,
        'inserted' => 0,
        'existing' => count($idsYaExistentes),
    ];

    if ($historicoParaInsertar !== []) {
        $respuestaHistoricoUpsert = llamarAppsScriptInventario($scriptUrl, $token, 'upsert_history_rows', $historicoParaInsertar);

        if (($respuestaHistoricoUpsert['success'] ?? true) === false) {
            throw new RuntimeException('No se pudo actualizar la pestana historico en Google Sheets.');
        }
    }

    $respuestaInventarioReplace = llamarAppsScriptInventario($scriptUrl, $token, 'replace_inventory_rows', $inventarioReconstruido);
    if (($respuestaInventarioReplace['success'] ?? true) === false) {
        throw new RuntimeException('No se pudo reconstruir la pestana inventario en Google Sheets.');
    }

    if ($pendientesIds !== []) {
        marcarLineasHistoricoSincronizadas($pdo, $pendientesIds);
    }

    $eliminadosInventario = count(array_intersect($pendientesIds, array_keys($inventarioIndexado)));
    $insertadosHistorico = is_numeric($respuestaHistoricoUpsert['inserted'] ?? null)
        ? (int) $respuestaHistoricoUpsert['inserted']
        : count($historicoParaInsertar);

    return [
        'insertados_historico' => $insertadosHistorico,
        'ya_existian_historico' => count($idsYaExistentes),
        'retirados_inventario' => $eliminadosInventario,
        'sincronizados_sql' => count($pendientesIds),
        'reconstruidos_inventario' => count($inventarioReconstruido),
        'pendientes_detectados' => count($pendientesHistorico),
    ];
}

function obtenerHistoricoPendienteParaGoogleSheets(PDO $pdo): array
{
    $stmt = $pdo->query(
        'SELECT
            i.id,
            i.ubicacion,
            i.destino,
            i.editorial,
            i.fecha_entrada,
            i.codigo_centro,
            i.colegio,
            i.fecha_salida,
            i.`orden`,
            i.bultos,
            COALESCE(a.empresa_recogida, \'MAXIMO SERVICIOS LOGISTICOS S.L.U.\') AS empresa_recogida,
            COALESCE(a.total_bultos, i.bultos, 0) AS total_bultos
         FROM inventario i
         LEFT JOIN albaranes_salida a
           ON a.numero_albaran = i.numero_albaran
         WHERE i.estado = \'historico\'
           AND i.sync_pendiente_historico = 1
         ORDER BY i.fecha_confirmacion_salida ASC, i.id ASC'
    );

    $filas = $stmt->fetchAll();

    return array_map('normalizarFilaHistoricoDestinoSheets', $filas);
}

function extraerFilasInventarioCompletoDesdeAppsScript(array $respuesta): array
{
    $filas = $respuesta['payload'] ?? $respuesta['rows'] ?? [];

    if (!is_array($filas)) {
        throw new RuntimeException('Apps Script no devolvio filas validas de la pestana inventario.');
    }

    return array_map(
        static function (array $row): array {
            $fila = normalizarFilaInventarioBidireccional($row);
            $fila['huecos'] = valorInventarioBidireccional($row['huecos'] ?? null);
            $fila['total_hueco'] = valorInventarioBidireccional($row['total_hueco'] ?? $row['total_hueco_todo'] ?? null);

            return $fila;
        },
        $filas
    );
}

function extraerFilasHistoricoDesdeAppsScript(array $respuesta): array
{
    $filas = $respuesta['payload'] ?? $respuesta['rows'] ?? [];

    if (!is_array($filas)) {
        throw new RuntimeException('Apps Script no devolvio filas validas de la pestana historico.');
    }

    return array_map('normalizarFilaHistoricoSheets', $filas);
}

function indexarFilasSheetsPorId(array $rows): array
{
    $indexado = [];

    foreach ($rows as $fila) {
        $id = trim((string) ($fila['id'] ?? ''));
        if ($id === '' || isset($indexado[$id])) {
            continue;
        }

        $indexado[$id] = $fila;
    }

    return $indexado;
}

function reconstruirFilasInventarioSheets(array $sqlRows, array $sheetIndexado): array
{
    $resultado = [];

    foreach ($sqlRows as $filaSql) {
        $id = trim((string) ($filaSql['id'] ?? ''));
        $filaSheet = $id !== '' && isset($sheetIndexado[$id]) ? $sheetIndexado[$id] : [];

        $resultado[] = [
            'ubicacion' => $filaSql['ubicacion'] ?? null,
            'destino' => $filaSql['destino'] ?? null,
            'id' => $filaSql['id'] ?? null,
            'editorial' => $filaSql['editorial'] ?? null,
            'fecha_entrada' => $filaSql['fecha_entrada'] ?? null,
            'codigo_centro' => $filaSql['codigo_centro'] ?? null,
            'colegio' => $filaSql['colegio'] ?? null,
            'fecha_salida' => $filaSql['fecha_salida'] ?? null,
            'orden' => $filaSql['orden'] ?? null,
            'bultos' => $filaSql['bultos'] ?? null,
            'huecos' => $filaSheet['huecos'] ?? null,
            'total_hueco' => $filaSheet['total_hueco'] ?? null,
        ];
    }

    return $resultado;
}

function normalizarFilaHistoricoDestinoSheets(array $row): array
{
    return [
        'ubicacion' => valorInventarioBidireccional($row['ubicacion'] ?? null),
        'destino' => valorInventarioBidireccional($row['destino'] ?? null),
        'id' => normalizarIdInventarioBidireccional($row['id'] ?? ''),
        'editorial' => valorInventarioBidireccional($row['editorial'] ?? null),
        'fecha_entrada' => valorInventarioBidireccional($row['fecha_entrada'] ?? null),
        'codigo_centro' => valorInventarioBidireccional($row['codigo_centro'] ?? null),
        'colegio' => valorInventarioBidireccional($row['colegio'] ?? null),
        'fecha_salida' => valorInventarioBidireccional($row['fecha_salida'] ?? null),
        'orden' => valorInventarioBidireccional($row['orden'] ?? null),
        'bultos' => valorInventarioBidireccional($row['bultos'] ?? null),
        'empresa_recogida' => valorInventarioBidireccional($row['empresa_recogida'] ?? null) ?? 'MAXIMO SERVICIOS LOGISTICOS S.L.U.',
        'total_bultos' => valorInventarioBidireccional($row['total_bultos'] ?? null) ?? valorInventarioBidireccional($row['bultos'] ?? null),
    ];
}
