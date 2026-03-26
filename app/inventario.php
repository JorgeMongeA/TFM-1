<?php

declare(strict_types=1);

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

function columnasInventarioTabla(): array
{
    return [
        'id' => 'ID',
        'editorial' => 'Editorial',
        'colegio' => 'Colegio',
        'codigo_centro' => 'Código centro',
        'ubicacion' => 'Ubicación',
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

function leerFiltrosInventarioDesdeRequest(array $source): array
{
    $filtros = [];

    foreach (filtrosInventarioPermitidos() as $campo) {
        $filtros[$campo] = trim((string) ($source[$campo] ?? ''));
    }

    return $filtros;
}

function leerOrdenInventarioDesdeRequest(array $source): array
{
    $ordenar = (string) ($source['ordenar'] ?? 'fecha_entrada');
    if (!in_array($ordenar, columnasInventarioOrdenables(), true)) {
        $ordenar = 'fecha_entrada';
    }

    $direccion = strtoupper((string) ($source['direccion'] ?? 'ASC'));
    if (!in_array($direccion, ['ASC', 'DESC'], true)) {
        $direccion = 'ASC';
    }

    return [$ordenar, $direccion];
}

function consultarInventario(PDO $pdo, array $filtros, string $ordenar, string $direccion): array
{
    $sql = 'SELECT id, editorial, colegio, codigo_centro, ubicacion, fecha_entrada, fecha_salida, bultos, destino, `orden`, indicador_completa
            FROM inventario';
    $where = [];
    $params = [];

    foreach ($filtros as $campo => $valor) {
        if ($valor === '') {
            continue;
        }

        $where[] = $campo . ' LIKE :' . $campo;
        $params[':' . $campo] = '%' . $valor . '%';
    }

    if ($where !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }

    // Futuro: esta consulta será el punto de integración con la pestaña "inventario" de Google Sheets.
    $sql .= " ORDER BY `{$ordenar}` {$direccion}";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function consultarInventarioPorCentros(PDO $pdo, array $codigosCentro): array
{
    $codigosCentro = array_values(array_filter(array_map('trim', $codigosCentro), static fn(string $valor): bool => $valor !== ''));
    if ($codigosCentro === []) {
        return [];
    }

    $placeholders = [];
    $params = [];

    foreach ($codigosCentro as $indice => $codigoCentro) {
        $placeholder = ':codigo_centro_' . $indice;
        $placeholders[] = $placeholder;
        $params[$placeholder] = $codigoCentro;
    }

    // Futuro: esta consulta alimentará la salida histórica vinculada con la pestaña "historico" de Google Sheets.
    $sql = 'SELECT id, editorial, colegio, codigo_centro, ubicacion, fecha_entrada, fecha_salida, bultos, destino, `orden`, indicador_completa
            FROM inventario
            WHERE codigo_centro IN (' . implode(', ', $placeholders) . ')
            ORDER BY codigo_centro ASC, fecha_entrada DESC, id DESC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function consultarInventarioPorIds(PDO $pdo, array $ids): array
{
    $ids = array_values(array_filter(array_map(static fn(mixed $valor): int => (int) $valor, $ids), static fn(int $valor): bool => $valor > 0));
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

    $sql = 'SELECT id, editorial, colegio, codigo_centro, ubicacion, fecha_entrada, fecha_salida, bultos, destino, `orden`, indicador_completa
            FROM inventario
            WHERE id IN (' . implode(', ', $placeholders) . ')
            ORDER BY codigo_centro ASC, fecha_entrada DESC, id DESC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}
