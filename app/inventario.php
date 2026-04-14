<?php

declare(strict_types=1);

require_once __DIR__ . '/actividad.php';

const INVENTARIO_ESTADO_ACTIVO = 'activo';
const INVENTARIO_ESTADO_HISTORICO = 'historico';
const INVENTARIO_ESTADO_ANULADO = 'anulado';

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

function consultarInventarioPorId(PDO $pdo, int $id): ?array
{
    if ($id <= 0) {
        return null;
    }

    $columnas = [
        'id',
        'editorial',
        'colegio',
        'codigo_centro',
        'ubicacion',
        'fecha_entrada',
        'fecha_salida',
        'bultos',
        'destino',
        '`orden`',
        'indicador_completa',
        'estado',
        'fecha_confirmacion_salida',
        'usuario_confirmacion',
        'numero_albaran',
        'sync_pendiente_historico',
        'fecha_sync_historico',
    ];

    if (inventarioSoportaAnulacion($pdo)) {
        $columnas[] = 'usuario_anulacion_id';
        $columnas[] = 'usuario_anulacion';
        $columnas[] = 'fecha_anulacion';
        $columnas[] = 'motivo_anulacion';
    }

    $stmt = $pdo->prepare(
        'SELECT ' . implode(', ', $columnas) . '
         FROM inventario
         WHERE id = :id
         LIMIT 1'
    );
    $stmt->execute([':id' => $id]);
    $fila = $stmt->fetch();

    return is_array($fila) ? $fila : null;
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

function obtenerBloqueosAnulacionInventario(PDO $pdo, int $inventarioId): array
{
    if ($inventarioId <= 0) {
        return [];
    }

    $bloqueos = [];

    $stmtAlbaran = $pdo->prepare(
        'SELECT a.numero_albaran
         FROM albaranes_salida_lineas l
         INNER JOIN albaranes_salida a ON a.id = l.albaran_id
         WHERE l.inventario_id = :inventario_id
         ORDER BY a.fecha_confirmacion DESC
         LIMIT 3'
    );
    $stmtAlbaran->execute([':inventario_id' => $inventarioId]);
    $albaranes = array_values(array_filter(array_map(
        static fn(mixed $valor): string => trim((string) $valor),
        $stmtAlbaran->fetchAll(PDO::FETCH_COLUMN)
    )));

    if ($albaranes !== []) {
        $bloqueos[] = 'La entrada ya forma parte de un albaran confirmado (' . implode(', ', $albaranes) . ').';
    }

    $stmtPedido = $pdo->prepare(
        'SELECT p.codigo_pedido
         FROM pedido_lineas pl
         INNER JOIN pedidos p ON p.id = pl.pedido_id
         WHERE pl.inventario_id = :inventario_id
         ORDER BY p.fecha_creacion DESC
         LIMIT 3'
    );
    $stmtPedido->execute([':inventario_id' => $inventarioId]);
    $pedidos = array_values(array_filter(array_map(
        static fn(mixed $valor): string => trim((string) $valor),
        $stmtPedido->fetchAll(PDO::FETCH_COLUMN)
    )));

    if ($pedidos !== []) {
        $bloqueos[] = 'La entrada ya esta vinculada a pedidos (' . implode(', ', $pedidos) . ').';
    }

    return $bloqueos;
}

function anularEntradaInventario(PDO $pdo, int $inventarioId, array $usuario, string $motivo): array
{
    $motivo = trim($motivo);
    if ($inventarioId <= 0) {
        throw new RuntimeException('La entrada indicada no es valida.');
    }

    if (!inventarioSoportaAnulacion($pdo)) {
        throw new RuntimeException('La anulacion de stock no esta disponible hasta aplicar la migracion de base de datos en produccion.');
    }

    if ($motivo === '' || strlen($motivo) < 8) {
        throw new RuntimeException('Indica un motivo de anulacion claro para dejar trazabilidad.');
    }

    $usuarioId = isset($usuario['user_id']) && (int) $usuario['user_id'] > 0 ? (int) $usuario['user_id'] : null;
    $username = trim((string) ($usuario['username'] ?? ''));
    $fechaAnulacion = new DateTimeImmutable('now', new DateTimeZone('Europe/Madrid'));

    try {
        $pdo->beginTransaction();

        $inventario = consultarInventarioPorId($pdo, $inventarioId);
        if ($inventario === null) {
            throw new RuntimeException('La entrada de inventario solicitada no existe.');
        }

        $estado = (string) ($inventario['estado'] ?? '');
        if ($estado === INVENTARIO_ESTADO_HISTORICO) {
            throw new RuntimeException('No se puede anular una entrada que ya esta en historico.');
        }

        if ($estado === INVENTARIO_ESTADO_ANULADO) {
            throw new RuntimeException('La entrada ya estaba anulada anteriormente.');
        }

        if ($estado !== INVENTARIO_ESTADO_ACTIVO) {
            throw new RuntimeException('La entrada no esta disponible para anulacion.');
        }

        $bloqueos = obtenerBloqueosAnulacionInventario($pdo, $inventarioId);
        if ($bloqueos !== []) {
            throw new RuntimeException(implode(' ', $bloqueos));
        }

        $stmt = $pdo->prepare(
            'UPDATE inventario
             SET estado = :estado,
                 usuario_anulacion_id = :usuario_anulacion_id,
                 usuario_anulacion = :usuario_anulacion,
                 fecha_anulacion = :fecha_anulacion,
                 motivo_anulacion = :motivo_anulacion
             WHERE id = :id
               AND estado = :estado_actual'
        );
        $stmt->execute([
            ':estado' => INVENTARIO_ESTADO_ANULADO,
            ':usuario_anulacion_id' => $usuarioId,
            ':usuario_anulacion' => $username !== '' ? $username : null,
            ':fecha_anulacion' => $fechaAnulacion->format('Y-m-d H:i:s'),
            ':motivo_anulacion' => $motivo,
            ':id' => $inventarioId,
            ':estado_actual' => INVENTARIO_ESTADO_ACTIVO,
        ]);

        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException('No se ha podido anular la entrada en este momento.');
        }

        registrarActividadSistema($pdo, [
            'usuario_id' => $usuarioId,
            'usuario' => $username,
            'tipo_evento' => ACTIVIDAD_TIPO_INVENTARIO_ANULADO,
            'entidad' => 'inventario',
            'entidad_id' => $inventarioId,
            'entidad_codigo' => (string) $inventarioId,
            'descripcion' => 'Anulacion de entrada de inventario ID ' . $inventarioId,
            'metadata' => [
                'motivo' => $motivo,
                'editorial' => (string) ($inventario['editorial'] ?? ''),
                'colegio' => (string) ($inventario['colegio'] ?? ''),
                'codigo_centro' => (string) ($inventario['codigo_centro'] ?? ''),
            ],
            'fecha_evento' => $fechaAnulacion,
        ]);

        $pdo->commit();

        $inventario['estado'] = INVENTARIO_ESTADO_ANULADO;
        $inventario['usuario_anulacion_id'] = $usuarioId;
        $inventario['usuario_anulacion'] = $username;
        $inventario['fecha_anulacion'] = $fechaAnulacion->format('Y-m-d H:i:s');
        $inventario['motivo_anulacion'] = $motivo;

        return $inventario;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        if ($e instanceof RuntimeException) {
            throw $e;
        }

        throw new RuntimeException('No se ha podido anular la entrada de inventario.', 0, $e);
    }
}

function normalizarEstadoInventario(string $estado): string
{
    return match ($estado) {
        INVENTARIO_ESTADO_HISTORICO => INVENTARIO_ESTADO_HISTORICO,
        INVENTARIO_ESTADO_ANULADO => INVENTARIO_ESTADO_ANULADO,
        default => INVENTARIO_ESTADO_ACTIVO,
    };
}

function inventarioSoportaAnulacion(PDO $pdo): bool
{
    static $cache = null;

    if (is_bool($cache)) {
        return $cache;
    }

    $columnasNecesarias = [
        'usuario_anulacion_id',
        'usuario_anulacion',
        'fecha_anulacion',
        'motivo_anulacion',
    ];

    try {
        $stmt = $pdo->query('SHOW COLUMNS FROM inventario');
        $columnas = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
        if (!is_array($columnas)) {
            $cache = false;
            return $cache;
        }

        foreach ($columnasNecesarias as $columna) {
            if (!in_array($columna, $columnas, true)) {
                $cache = false;
                return $cache;
            }
        }

        $cache = true;
        return $cache;
    } catch (Throwable $e) {
        $cache = false;
        return $cache;
    }
}
