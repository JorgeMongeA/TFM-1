<?php

declare(strict_types=1);

const INVENTARIO_ESTADO_ACTIVO = 'activo';
const INVENTARIO_ESTADO_HISTORICO = 'historico';

function columnasInventarioOrdenables(): array
{
    return [
        'id',
        'editorial',
        'colegio',
        'codigo_centro',
        'ubicacion',
        'fecha_entrada',
        'fecha_salida',
        'bultos',
        'destino',
        'orden',
        'indicador_completa',
    ];
}

function columnasHistoricoOrdenables(): array
{
    return [
        'id',
        'numero_albaran',
        'editorial',
        'colegio',
        'codigo_centro',
        'ubicacion',
        'fecha_entrada',
        'fecha_salida',
        'fecha_confirmacion_salida',
        'bultos',
        'destino',
        'orden',
        'usuario_confirmacion',
    ];
}

function columnasInventarioTabla(): array
{
    return [
        'id' => 'ID',
        'editorial' => 'Editorial',
        'colegio' => 'Colegio',
        'codigo_centro' => 'Codigo centro',
        'ubicacion' => 'Ubicacion',
        'fecha_entrada' => 'Fecha entrada',
        'fecha_salida' => 'Fecha salida',
        'bultos' => 'Bultos',
        'destino' => 'Destino',
        'orden' => 'Orden',
        'indicador_completa' => 'Indicador completa',
    ];
}

function columnasHistoricoTabla(): array
{
    return [
        'numero_albaran' => 'Numero albaran',
        'fecha_confirmacion_salida' => 'Fecha confirmacion',
        'usuario_confirmacion' => 'Usuario confirmacion',
        'id' => 'ID',
        'editorial' => 'Editorial',
        'colegio' => 'Colegio',
        'codigo_centro' => 'Codigo centro',
        'ubicacion' => 'Ubicacion',
        'fecha_entrada' => 'Fecha entrada',
        'fecha_salida' => 'Fecha salida',
        'bultos' => 'Bultos',
        'destino' => 'Destino',
        'orden' => 'Orden',
        'indicador_completa' => 'Indicador completa',
    ];
}

function filtrosInventarioPermitidos(): array
{
    return ['editorial', 'colegio', 'codigo_centro', 'destino'];
}

function filtrosHistoricoPermitidos(): array
{
    return ['numero_albaran', 'editorial', 'colegio', 'codigo_centro', 'destino', 'usuario_confirmacion'];
}

function leerFiltrosInventarioDesdeRequest(array $source, ?array $camposPermitidos = null): array
{
    $filtros = [];
    $campos = $camposPermitidos ?? filtrosInventarioPermitidos();

    foreach ($campos as $campo) {
        $filtros[$campo] = trim((string) ($source[$campo] ?? ''));
    }

    return $filtros;
}

function leerOrdenInventarioDesdeRequest(
    array $source,
    ?array $columnasOrdenables = null,
    string $ordenDefault = 'fecha_entrada'
): array {
    $columnas = $columnasOrdenables ?? columnasInventarioOrdenables();
    $ordenar = (string) ($source['ordenar'] ?? $ordenDefault);

    if (!in_array($ordenar, $columnas, true)) {
        $ordenar = $ordenDefault;
    }

    $direccion = strtoupper((string) ($source['direccion'] ?? 'ASC'));
    if (!in_array($direccion, ['ASC', 'DESC'], true)) {
        $direccion = 'ASC';
    }

    return [$ordenar, $direccion];
}

function consultarInventario(PDO $pdo, array $filtros, string $ordenar, string $direccion): array
{
    return consultarInventarioPorEstado($pdo, INVENTARIO_ESTADO_ACTIVO, $filtros, $ordenar, $direccion);
}

function consultarHistorico(PDO $pdo, array $filtros, string $ordenar, string $direccion): array
{
    return consultarInventarioPorEstado($pdo, INVENTARIO_ESTADO_HISTORICO, $filtros, $ordenar, $direccion);
}

function consultarInventarioPorEstado(PDO $pdo, string $estado, array $filtros, string $ordenar, string $direccion): array
{
    $estado = normalizarEstadoInventario($estado);
    $sql = 'SELECT id, editorial, colegio, codigo_centro, ubicacion, fecha_entrada, fecha_salida, bultos, destino, `orden`, indicador_completa, estado, fecha_confirmacion_salida, usuario_confirmacion, numero_albaran, sync_pendiente_historico, fecha_sync_historico
            FROM inventario
            WHERE estado = :estado';
    $params = [':estado' => $estado];
    $camposFiltro = $estado === INVENTARIO_ESTADO_HISTORICO ? filtrosHistoricoPermitidos() : filtrosInventarioPermitidos();

    foreach ($camposFiltro as $campo) {
        $valor = trim((string) ($filtros[$campo] ?? ''));

        if ($valor === '') {
            continue;
        }

        $sql .= ' AND ' . $campo . ' LIKE :' . $campo;
        $params[':' . $campo] = '%' . $valor . '%';
    }

    $sql .= " ORDER BY `{$ordenar}` {$direccion}";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function consultarInventarioPorCentros(PDO $pdo, array $codigosCentro, string $estado = INVENTARIO_ESTADO_ACTIVO): array
{
    $estado = normalizarEstadoInventario($estado);
    $codigosCentro = array_values(array_filter(array_map('trim', $codigosCentro), static fn(string $valor): bool => $valor !== ''));
    if ($codigosCentro === []) {
        return [];
    }

    $placeholders = [];
    $params = [':estado' => $estado];

    foreach ($codigosCentro as $indice => $codigoCentro) {
        $placeholder = ':codigo_centro_' . $indice;
        $placeholders[] = $placeholder;
        $params[$placeholder] = $codigoCentro;
    }

    $sql = 'SELECT id, editorial, colegio, codigo_centro, ubicacion, fecha_entrada, fecha_salida, bultos, destino, `orden`, indicador_completa, estado, fecha_confirmacion_salida, usuario_confirmacion, numero_albaran
            FROM inventario
            WHERE estado = :estado
              AND codigo_centro IN (' . implode(', ', $placeholders) . ')
            ORDER BY codigo_centro ASC, fecha_entrada DESC, id DESC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function consultarInventarioPorIds(PDO $pdo, array $ids, ?string $estado = INVENTARIO_ESTADO_ACTIVO): array
{
    $ids = array_values(array_filter(
        array_map(static fn(mixed $valor): int => (int) $valor, $ids),
        static fn(int $valor): bool => $valor > 0
    ));

    if ($ids === []) {
        return [];
    }

    $placeholders = [];
    $params = [];

    foreach ($ids as $indice => $id) {
        $placeholder = ':id_' . $indice;
        $placeholders[] = $placeholder;
        $params[$placeholder] = $id;
    }

    $sql = 'SELECT id, editorial, colegio, codigo_centro, ubicacion, fecha_entrada, fecha_salida, bultos, destino, `orden`, indicador_completa, estado, fecha_confirmacion_salida, usuario_confirmacion, numero_albaran, sync_pendiente_historico, fecha_sync_historico
            FROM inventario
            WHERE id IN (' . implode(', ', $placeholders) . ')';

    if ($estado !== null) {
        $params[':estado'] = normalizarEstadoInventario($estado);
        $sql .= ' AND estado = :estado';
    }

    $sql .= ' ORDER BY codigo_centro ASC, fecha_entrada DESC, id DESC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function consultarHistoricoPorNumeroAlbaran(PDO $pdo, string $numeroAlbaran): array
{
    $numeroAlbaran = trim($numeroAlbaran);
    if ($numeroAlbaran === '') {
        return [];
    }

    $stmt = $pdo->prepare(
        'SELECT id, editorial, colegio, codigo_centro, ubicacion, fecha_entrada, fecha_salida, bultos, destino, `orden`, indicador_completa, estado, fecha_confirmacion_salida, usuario_confirmacion, numero_albaran, sync_pendiente_historico, fecha_sync_historico
         FROM inventario
         WHERE estado = :estado
           AND numero_albaran = :numero_albaran
         ORDER BY id ASC'
    );
    $stmt->execute([
        ':estado' => INVENTARIO_ESTADO_HISTORICO,
        ':numero_albaran' => $numeroAlbaran,
    ]);

    return $stmt->fetchAll();
}

function obtenerLineasHistoricoPendientesSheets(PDO $pdo): array
{
    $stmt = $pdo->query(
        'SELECT id, editorial, colegio, codigo_centro, ubicacion, fecha_entrada, fecha_salida, bultos, destino, `orden`, indicador_completa, estado, fecha_confirmacion_salida, usuario_confirmacion, numero_albaran, sync_pendiente_historico, fecha_sync_historico
         FROM inventario
         WHERE estado = \'historico\'
           AND sync_pendiente_historico = 1
         ORDER BY fecha_confirmacion_salida ASC, id ASC'
    );

    return $stmt->fetchAll();
}

function marcarLineasHistoricoSincronizadas(PDO $pdo, array $ids, ?DateTimeInterface $fechaSincronizacion = null): int
{
    $ids = array_values(array_filter(
        array_map(static fn(mixed $valor): int => (int) $valor, $ids),
        static fn(int $valor): bool => $valor > 0
    ));

    if ($ids === []) {
        return 0;
    }

    $placeholders = [];
    $params = [
        ':fecha_sync_historico' => ($fechaSincronizacion ?? new DateTimeImmutable('now', new DateTimeZone('Europe/Madrid')))->format('Y-m-d H:i:s'),
    ];

    foreach ($ids as $indice => $id) {
        $placeholder = ':id_' . $indice;
        $placeholders[] = $placeholder;
        $params[$placeholder] = $id;
    }

    $stmt = $pdo->prepare(
        'UPDATE inventario
         SET sync_pendiente_historico = 0,
             fecha_sync_historico = :fecha_sync_historico
         WHERE estado = \'historico\'
           AND id IN (' . implode(', ', $placeholders) . ')'
    );
    $stmt->execute($params);

    return $stmt->rowCount();
}

function normalizarEstadoInventario(string $estado): string
{
    return $estado === INVENTARIO_ESTADO_HISTORICO
        ? INVENTARIO_ESTADO_HISTORICO
        : INVENTARIO_ESTADO_ACTIVO;
}
