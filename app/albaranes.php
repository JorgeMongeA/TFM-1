<?php

/**
 * Autor: Jorge Monge
 * Trabajo Final de Máster (TFM)
 * UOC - 2026
 */

declare(strict_types=1);

const ALBARAN_ORIGEN_FIJO = [
    'MÁXIMO SERVICIOS LOGÍSTICOS S.L.U.',
    'Calle Buenos Aires 23',
    'Polígono Ind. Centrovía',
    'La Muela',
    'Zaragoza - España',
];

function destinosAlbaranDisponibles(): array
{
    return [
        'EPL' => [
            'codigo' => 'EPL',
            'nombre' => 'Épila',
            'direccion' => 'Av. Tastueña, 50290 Épila, Zaragoza',
        ],
        'EDV' => [
            'codigo' => 'EDV',
            'nombre' => 'Zaragoza',
            'direccion' => 'N-II, KM 315, 700, 50012 Zaragoza',
        ],
    ];
}

function leerIdsSeleccionadosDesdeRequest(array $source): array
{
    $ids = $source['seleccionados'] ?? [];

    if (!is_array($ids)) {
        $ids = [$ids];
    }

    $ids = array_map(static fn(mixed $valor): int => (int) $valor, $ids);
    $ids = array_filter($ids, static fn(int $valor): bool => $valor > 0);

    return array_values(array_unique($ids));
}

function normalizarTextoDestinoAlbaran(?string $valor): string
{
    $texto = trim((string) $valor);

    if ($texto === '') {
        return '';
    }

    $texto = strtr($texto, [
        'á' => 'a',
        'Á' => 'A',
        'é' => 'e',
        'É' => 'E',
        'í' => 'i',
        'Í' => 'I',
        'ó' => 'o',
        'Ó' => 'O',
        'ú' => 'u',
        'Ú' => 'U',
        'ü' => 'u',
        'Ü' => 'U',
        'ñ' => 'n',
        'Ñ' => 'N',
    ]);
    $texto = strtoupper($texto);

    return preg_replace('/[^A-Z0-9]+/', '', $texto) ?? '';
}

function normalizarDestinoAlbaran(?string $destino): ?string
{
    $destinoNormalizado = normalizarTextoDestinoAlbaran($destino);

    if ($destinoNormalizado === '') {
        return null;
    }

    if (
        str_contains($destinoNormalizado, 'EPL')
        || str_contains($destinoNormalizado, 'EPILA')
        || str_contains($destinoNormalizado, 'TASTUENA')
    ) {
        return 'EPL';
    }

    if (
        str_contains($destinoNormalizado, 'EDV')
        || str_contains($destinoNormalizado, 'NII')
        || str_contains($destinoNormalizado, 'KM315')
        || str_contains($destinoNormalizado, '315700')
    ) {
        return 'EDV';
    }

    return null;
}

function obtenerDestinoAlbaran(string $codigoDestino): array
{
    $destinos = destinosAlbaranDisponibles();

    if (!isset($destinos[$codigoDestino])) {
        throw new RuntimeException('Destino logístico no soportado para albarán.');
    }

    return $destinos[$codigoDestino];
}

function agruparMercanciaPorDestinoAlbaran(array $mercancia): array
{
    $grupos = [];
    $idsSinDestino = [];

    foreach ($mercancia as $fila) {
        $codigoDestino = normalizarDestinoAlbaran((string) ($fila['destino'] ?? ''));

        if ($codigoDestino === null) {
            $idsSinDestino[] = (int) ($fila['id'] ?? 0);
            continue;
        }

        if (!isset($grupos[$codigoDestino])) {
            $grupos[$codigoDestino] = [];
        }

        $grupos[$codigoDestino][] = $fila;
    }

    if ($idsSinDestino !== []) {
        throw new RuntimeException('Hay mercancías sin destino logístico válido para generar el albarán.');
    }

    if ($grupos === []) {
        throw new RuntimeException('No hay mercancía válida para generar el albarán.');
    }

    $gruposOrdenados = [];

    foreach (['EPL', 'EDV'] as $codigoDestino) {
        if (isset($grupos[$codigoDestino])) {
            $gruposOrdenados[$codigoDestino] = $grupos[$codigoDestino];
        }
    }

    return $gruposOrdenados;
}

function formatearFechaAlbaran(?string $fecha): string
{
    $fecha = trim((string) $fecha);

    if ($fecha === '') {
        return '-';
    }

    try {
        return (new DateTimeImmutable($fecha))->format('d/m/Y');
    } catch (Throwable $e) {
        return $fecha;
    }
}

function limpiarTextoPlanoAlbaran(mixed $valor, string $fallback = '-', int $maxLength = 0): string
{
    $texto = trim(strip_tags((string) $valor));
    $texto = preg_replace('/\s+/u', ' ', $texto) ?? '';

    if ($texto === '') {
        return $fallback;
    }

    if ($maxLength > 0 && longitudTextoAlbaran($texto) > $maxLength) {
        $texto = recortarTextoAlbaran($texto, $maxLength - 1) . '...';
    }

    return $texto;
}

function textoMayusculasAlbaran(string $texto): string
{
    if (function_exists('mb_strtoupper')) {
        return mb_strtoupper($texto, 'UTF-8');
    }

    return strtoupper($texto);
}

function longitudTextoAlbaran(string $texto): int
{
    if (function_exists('mb_strlen')) {
        return mb_strlen($texto, 'UTF-8');
    }

    return strlen($texto);
}

function recortarTextoAlbaran(string $texto, int $longitud): string
{
    if ($longitud <= 0) {
        return '';
    }

    if (function_exists('mb_substr')) {
        return mb_substr($texto, 0, $longitud, 'UTF-8');
    }

    return substr($texto, 0, $longitud);
}

function obtenerLineasOrigenAlbaran(): array
{
    return ALBARAN_ORIGEN_FIJO;
}

function obtenerResumenMercanciaAlbaran(array $lineas): array
{
    $totalBultos = 0;

    foreach ($lineas as $linea) {
        $totalBultos += (int) ($linea['bultos'] ?? 0);
    }

    return [
        'lineas' => count($lineas),
        'bultos' => $totalBultos,
    ];
}

function columnasTablaAlbaranSalida(): array
{
    return [
        ['key' => 'id', 'label' => 'ID', 'width' => 16.0, 'max' => 10, 'align' => 'C'],
        ['key' => 'bultos', 'label' => 'Cantidad de bultos', 'width' => 28.0, 'max' => 12, 'align' => 'C'],
        ['key' => 'colegio', 'label' => 'Colegio', 'width' => 50.0, 'max' => 34, 'align' => 'L'],
        ['key' => 'editorial', 'label' => 'Editorial', 'width' => 34.0, 'max' => 24, 'align' => 'L'],
        ['key' => 'fecha_entrada', 'label' => 'Fecha de entrada', 'width' => 26.0, 'max' => 10, 'align' => 'C'],
        ['key' => 'orden', 'label' => 'Orden', 'width' => 28.0, 'max' => 18, 'align' => 'L'],
    ];
}

function construirNombreArchivoAlbaran(DateTimeInterface $fechaGeneracion, ?string $numeroAlbaran = null): string
{
    $identificador = trim((string) $numeroAlbaran);

    if ($identificador !== '') {
        $identificador = preg_replace('/[^A-Z0-9_-]+/i', '_', $identificador) ?? '';
    }

    if ($identificador !== '') {
        return 'albaran_salida_' . $identificador . '.pdf';
    }

    return 'albaran_salida_' . $fechaGeneracion->format('Ymd_His') . '.pdf';
}

function pedidosSoportanStockProcesadoAlbaran(PDO $pdo): bool
{
    static $cache = null;

    if (is_bool($cache)) {
        return $cache;
    }

    try {
        $stmt = $pdo->query('SHOW COLUMNS FROM pedidos');
        $columnas = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
        if (!is_array($columnas)) {
            $cache = false;
            return $cache;
        }

        $cache = in_array('stock_procesado', $columnas, true);
        return $cache;
    } catch (Throwable $e) {
        $cache = false;
        return $cache;
    }
}

function resolverDestinoPedidoAlbaran(array $lineas): array
{
    if ($lineas === []) {
        return [
            'codigo' => null,
            'etiqueta' => '-',
            'estado' => 'sin_lineas',
            'imprimible' => false,
            'mensaje' => 'El pedido no tiene lineas.',
        ];
    }

    $codigosDestino = [];
    $lineasSinDestinoValido = 0;

    foreach ($lineas as $linea) {
        $codigo = normalizarDestinoAlbaran((string) ($linea['destino'] ?? ''));

        if ($codigo === null) {
            $lineasSinDestinoValido++;
            continue;
        }

        $codigosDestino[$codigo] = true;
    }

    if (count($codigosDestino) > 1) {
        return [
            'codigo' => null,
            'etiqueta' => 'Mixto',
            'estado' => 'mixto',
            'imprimible' => false,
            'mensaje' => 'El pedido contiene destinos mixtos (EDV/EPL).',
        ];
    }

    if (count($codigosDestino) === 0) {
        return [
            'codigo' => null,
            'etiqueta' => 'Sin destino',
            'estado' => 'sin_destino',
            'imprimible' => false,
            'mensaje' => 'El pedido no tiene destino logistico valido.',
        ];
    }

    if ($lineasSinDestinoValido > 0) {
        return [
            'codigo' => null,
            'etiqueta' => 'Invalido',
            'estado' => 'destino_invalido',
            'imprimible' => false,
            'mensaje' => 'Hay lineas del pedido sin destino logistico valido.',
        ];
    }

    $codigoUnico = array_key_first($codigosDestino);
    if (!is_string($codigoUnico) || $codigoUnico === '') {
        return [
            'codigo' => null,
            'etiqueta' => 'Sin destino',
            'estado' => 'sin_destino',
            'imprimible' => false,
            'mensaje' => 'El pedido no tiene destino logistico valido.',
        ];
    }

    return [
        'codigo' => $codigoUnico,
        'etiqueta' => $codigoUnico,
        'estado' => 'ok',
        'imprimible' => true,
        'mensaje' => '',
    ];
}

function consultarLineasPedidoParaAlbaran(PDO $pdo, int $pedidoId): array
{
    if ($pedidoId <= 0) {
        return [];
    }

    $stmt = $pdo->prepare(
        'SELECT id, pedido_id, inventario_id, editorial, colegio, codigo_centro, ubicacion, fecha_entrada, bultos, destino, `orden`, indicador_completa
         FROM pedido_lineas
         WHERE pedido_id = :pedido_id
         ORDER BY id ASC'
    );
    $stmt->execute([':pedido_id' => $pedidoId]);

    return $stmt->fetchAll();
}

function consultarPedidoDisponibleParaAlbaranPorId(PDO $pdo, int $pedidoId): ?array
{
    if ($pedidoId <= 0) {
        return null;
    }

    $sql = 'SELECT id, codigo_pedido, usuario_creacion, estado, total_lineas, total_bultos, fecha_creacion, fecha_ultima_gestion';
    if (pedidosSoportanStockProcesadoAlbaran($pdo)) {
        $sql .= ', stock_procesado, fecha_stock_procesado';
    } else {
        $sql .= ', 0 AS stock_procesado, NULL AS fecha_stock_procesado';
    }

    $sql .= '
        FROM pedidos
        WHERE id = :id
          AND estado IN (\'preparado\', \'completado\')';

    if (pedidosSoportanStockProcesadoAlbaran($pdo)) {
        $sql .= ' AND stock_procesado = 1';
    }

    $sql .= ' LIMIT 1';

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $pedidoId]);
    $pedido = $stmt->fetch();

    if (!is_array($pedido)) {
        return null;
    }

    $lineas = consultarLineasPedidoParaAlbaran($pdo, $pedidoId);
    $destino = resolverDestinoPedidoAlbaran($lineas);

    $pedido['lineas_pedido'] = $lineas;
    $pedido['lineas_pedido_total'] = count($lineas);
    $pedido['destino_codigo'] = $destino['codigo'];
    $pedido['destino_etiqueta'] = $destino['etiqueta'];
    $pedido['destino_estado'] = $destino['estado'];
    $pedido['destino_imprimible'] = $destino['imprimible'];
    $pedido['destino_mensaje'] = $destino['mensaje'];

    return $pedido;
}

function consultarPedidosDisponiblesParaAlbaran(PDO $pdo, int $limite = 200): array
{
    $limite = max(1, min(500, $limite));
    $usaStockProcesado = pedidosSoportanStockProcesadoAlbaran($pdo);

    $sql = 'SELECT p.id,
                   p.codigo_pedido,
                   p.usuario_creacion,
                   p.estado,
                   p.total_lineas,
                   p.total_bultos,
                   p.fecha_creacion,
                   p.fecha_ultima_gestion';

    if ($usaStockProcesado) {
        $sql .= ', p.stock_procesado, p.fecha_stock_procesado';
    } else {
        $sql .= ', 0 AS stock_procesado, NULL AS fecha_stock_procesado';
    }

    $sql .= ',
            (
                SELECT COUNT(*)
                FROM inventario i
                WHERE i.estado = \'historico\'
                  AND i.numero_albaran = p.codigo_pedido
            ) AS lineas_historico,
            (
                SELECT COUNT(*)
                FROM albaranes_salida a
                WHERE a.numero_albaran = p.codigo_pedido
            ) AS albaranes_confirmados
        FROM pedidos p
        WHERE p.estado IN (\'preparado\', \'completado\')';

    if ($usaStockProcesado) {
        $sql .= ' AND p.stock_procesado = 1';
    }

    $sql .= '
        ORDER BY COALESCE(p.fecha_stock_procesado, p.fecha_ultima_gestion, p.fecha_creacion) DESC, p.id DESC
        LIMIT :limite';

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
    $stmt->execute();
    $pedidos = $stmt->fetchAll();

    if (!is_array($pedidos) || $pedidos === []) {
        return [];
    }

    $pedidoIds = array_values(array_filter(array_map(
        static fn(array $fila): int => (int) ($fila['id'] ?? 0),
        $pedidos
    ), static fn(int $id): bool => $id > 0));

    if ($pedidoIds === []) {
        return [];
    }

    $placeholders = [];
    $params = [];
    foreach ($pedidoIds as $indice => $pedidoId) {
        $placeholder = ':pedido_id_' . $indice;
        $placeholders[] = $placeholder;
        $params[$placeholder] = $pedidoId;
    }

    $stmtLineas = $pdo->prepare(
        'SELECT pedido_id, id, destino
         FROM pedido_lineas
         WHERE pedido_id IN (' . implode(', ', $placeholders) . ')
         ORDER BY pedido_id ASC, id ASC'
    );
    $stmtLineas->execute($params);
    $lineas = $stmtLineas->fetchAll();

    $lineasPorPedido = [];
    foreach ($lineas as $linea) {
        $pedidoId = (int) ($linea['pedido_id'] ?? 0);
        if ($pedidoId <= 0) {
            continue;
        }

        if (!isset($lineasPorPedido[$pedidoId])) {
            $lineasPorPedido[$pedidoId] = [];
        }

        $lineasPorPedido[$pedidoId][] = $linea;
    }

    foreach ($pedidos as &$pedido) {
        $pedidoId = (int) ($pedido['id'] ?? 0);
        $lineasPedido = $lineasPorPedido[$pedidoId] ?? [];
        $destino = resolverDestinoPedidoAlbaran($lineasPedido);

        $pedido['lineas_pedido_total'] = count($lineasPedido);
        $pedido['destino_codigo'] = $destino['codigo'];
        $pedido['destino_etiqueta'] = $destino['etiqueta'];
        $pedido['destino_estado'] = $destino['estado'];
        $pedido['destino_imprimible'] = $destino['imprimible'];
        $pedido['destino_mensaje'] = $destino['mensaje'];
        $pedido['albaran_generado'] = ((int) ($pedido['lineas_historico'] ?? 0) > 0) || ((int) ($pedido['albaranes_confirmados'] ?? 0) > 0);
    }
    unset($pedido);

    return $pedidos;
}
