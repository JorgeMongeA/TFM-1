<?php

/**
 * Autor: Jorge Monge
 * Trabajo Final de Máster (TFM)
 * UOC - 2026
 */

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/inventario.php';
require_once __DIR__ . '/salidas.php';
require_once __DIR__ . '/actividad.php';

const PEDIDO_ESTADO_PENDIENTE = 'pendiente';
const PEDIDO_ESTADO_EN_PREPARACION = 'en_preparacion';
const PEDIDO_ESTADO_PREPARADO = 'preparado';
const PEDIDO_ESTADO_COMPLETADO = 'completado';
const PEDIDO_ESTADO_CANCELADO = 'cancelado';

function estadosPedidoDisponibles(): array
{
    return [
        PEDIDO_ESTADO_PENDIENTE => 'Pendiente',
        PEDIDO_ESTADO_EN_PREPARACION => 'En preparacion',
        PEDIDO_ESTADO_PREPARADO => 'Preparado',
        PEDIDO_ESTADO_COMPLETADO => 'Completado',
        PEDIDO_ESTADO_CANCELADO => 'Cancelado',
    ];
}

function normalizarEstadoPedido(?string $estado): string
{
    $estado = trim((string) $estado);

    return array_key_exists($estado, estadosPedidoDisponibles())
        ? $estado
        : PEDIDO_ESTADO_PENDIENTE;
}

function etiquetaEstadoPedido(string $estado): string
{
    return estadosPedidoDisponibles()[normalizarEstadoPedido($estado)];
}

function claseEstadoPedido(string $estado): string
{
    return match (normalizarEstadoPedido($estado)) {
        PEDIDO_ESTADO_EN_PREPARACION => 'text-bg-warning',
        PEDIDO_ESTADO_PREPARADO => 'text-bg-info',
        PEDIDO_ESTADO_COMPLETADO => 'text-bg-success',
        PEDIDO_ESTADO_CANCELADO => 'text-bg-dark',
        default => 'text-bg-secondary',
    };
}

function obtenerUsuarioPedidoActual(): array
{
    return [
        'user_id' => isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null,
        'username' => trim((string) ($_SESSION['username'] ?? $_SESSION['usuario'] ?? '')),
        'rol' => obtenerRolUsuario(),
    ];
}

function leerFiltrosListadoPedidosDesdeRequest(array $source): array
{
    return [
        'codigo_pedido' => trim((string) ($source['codigo_pedido'] ?? '')),
        'estado' => trim((string) ($source['estado'] ?? '')),
        'usuario_creacion' => trim((string) ($source['usuario_creacion'] ?? '')),
    ];
}

function camposPedidosAutocompletePermitidos(): array
{
    return [
        'codigo_pedido' => 'Codigo pedido',
        'usuario_creacion' => 'Solicitante',
    ];
}

function buscarValoresPedidosAutocomplete(PDO $pdo, string $campo, string $texto, int $limite = 10, ?int $usuarioId = null): array
{
    $camposPermitidos = camposPedidosAutocompletePermitidos();
    if (!array_key_exists($campo, $camposPermitidos)) {
        throw new InvalidArgumentException('Campo de autocomplete no permitido.');
    }

    $texto = trim($texto);
    $limite = max(1, min(20, $limite));
    $sql = 'SELECT ' . $campo . ' AS value, COUNT(*) AS total
            FROM pedidos
            WHERE ' . $campo . ' IS NOT NULL
              AND TRIM(' . $campo . ') <> \'\'';
    $params = [];

    if ($usuarioId !== null && $usuarioId > 0) {
        $sql .= ' AND usuario_creacion_id = :usuario_creacion_id';
        $params[':usuario_creacion_id'] = $usuarioId;
    }

    if ($texto !== '') {
        $sql .= ' AND ' . $campo . ' LIKE :texto';
        $params[':texto'] = '%' . $texto . '%';
    }

    $sql .= ' GROUP BY ' . $campo;

    if ($texto !== '') {
        $sql .= ' ORDER BY CASE WHEN ' . $campo . ' LIKE :prefijo THEN 0 ELSE 1 END ASC, ' . $campo . ' ASC';
        $params[':prefijo'] = $texto . '%';
    } else {
        $sql .= ' ORDER BY ' . $campo . ' ASC';
    }

    $sql .= ' LIMIT :limite';

    $stmt = $pdo->prepare($sql);
    foreach ($params as $param => $valor) {
        $stmt->bindValue($param, $valor);
    }
    $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

function normalizarIdsPedido(array $ids): array
{
    $ids = array_map(static fn(mixed $valor): int => (int) $valor, $ids);
    $ids = array_values(array_filter($ids, static fn(int $valor): bool => $valor > 0));

    return array_values(array_unique($ids));
}

function estadosPedidoBloqueantesDuplicidad(): array
{
    return [
        PEDIDO_ESTADO_PENDIENTE,
        PEDIDO_ESTADO_EN_PREPARACION,
        PEDIDO_ESTADO_PREPARADO,
    ];
}

function obtenerLineasComprometidasPorInventarioIds(PDO $pdo, array $inventarioIds, ?int $pedidoIdExcluir = null): array
{
    $inventarioIds = normalizarIdsPedido($inventarioIds);
    if ($inventarioIds === []) {
        return [];
    }

    $placeholdersIds = [];
    $params = [];

    foreach ($inventarioIds as $indice => $inventarioId) {
        $placeholder = ':inventario_id_' . $indice;
        $placeholdersIds[] = $placeholder;
        $params[$placeholder] = $inventarioId;
    }

    $placeholdersEstados = [];
    foreach (estadosPedidoBloqueantesDuplicidad() as $indiceEstado => $estadoBloqueante) {
        $placeholder = ':estado_' . $indiceEstado;
        $placeholdersEstados[] = $placeholder;
        $params[$placeholder] = $estadoBloqueante;
    }

    $filtroExclusionPedido = '';
    if ($pedidoIdExcluir !== null && $pedidoIdExcluir > 0) {
        $filtroExclusionPedido = ' AND p.id <> :pedido_id_excluir';
        $params[':pedido_id_excluir'] = $pedidoIdExcluir;
    }

    $stmt = $pdo->prepare(
        'SELECT pl.inventario_id, p.id AS pedido_id, p.codigo_pedido, p.estado, p.fecha_creacion
         FROM pedido_lineas pl
         INNER JOIN pedidos p ON p.id = pl.pedido_id
         WHERE pl.inventario_id IN (' . implode(', ', $placeholdersIds) . ')
           AND p.estado IN (' . implode(', ', $placeholdersEstados) . ')
           ' . $filtroExclusionPedido . '
         ORDER BY p.fecha_creacion DESC, p.id DESC'
    );
    $stmt->execute($params);
    $filas = $stmt->fetchAll();

    $comprometidas = [];
    foreach ($filas as $fila) {
        $inventarioId = (int) ($fila['inventario_id'] ?? 0);
        if ($inventarioId <= 0 || isset($comprometidas[$inventarioId])) {
            continue;
        }

        $comprometidas[$inventarioId] = [
            'inventario_id' => $inventarioId,
            'pedido_id' => (int) ($fila['pedido_id'] ?? 0),
            'codigo_pedido' => trim((string) ($fila['codigo_pedido'] ?? '')),
            'estado' => normalizarEstadoPedido((string) ($fila['estado'] ?? PEDIDO_ESTADO_PENDIENTE)),
            'fecha_creacion' => trim((string) ($fila['fecha_creacion'] ?? '')),
        ];
    }

    return $comprometidas;
}

function construirMensajeLineasComprometidas(array $lineasComprometidas): string
{
    if ($lineasComprometidas === []) {
        return '';
    }

    $detalles = [];
    foreach ($lineasComprometidas as $linea) {
        $inventarioId = (int) ($linea['inventario_id'] ?? 0);
        $codigoPedido = trim((string) ($linea['codigo_pedido'] ?? ''));
        $estado = normalizarEstadoPedido((string) ($linea['estado'] ?? PEDIDO_ESTADO_PENDIENTE));
        $detalles[] = sprintf(
            'ID %d (%s, %s)',
            $inventarioId,
            $codigoPedido !== '' ? $codigoPedido : 'pedido en curso',
            strtolower(etiquetaEstadoPedido($estado))
        );

        if (count($detalles) >= 5) {
            break;
        }
    }

    return 'Algunas lineas ya estan incluidas en pedidos en curso y no se pueden volver a solicitar: ' . implode(', ', $detalles) . '.';
}

function pedidosSoportanStockProcesado(PDO $pdo): bool
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

        $cache = in_array('stock_procesado', $columnas, true) && in_array('fecha_stock_procesado', $columnas, true);
        return $cache;
    } catch (Throwable $e) {
        $cache = false;
        return $cache;
    }
}

function pedidosSoportanEstadoCancelado(PDO $pdo): bool
{
    static $cache = null;

    if (is_bool($cache)) {
        return $cache;
    }

    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM pedidos LIKE 'estado'");
        $fila = $stmt->fetch();
        if (!is_array($fila)) {
            $cache = false;
            return $cache;
        }

        $tipo = strtolower(trim((string) ($fila['Type'] ?? '')));
        $cache = str_contains($tipo, "'cancelado'");
        return $cache;
    } catch (Throwable $e) {
        $cache = false;
        return $cache;
    }
}

function columnasStockProcesadoSelect(PDO $pdo): string
{
    if (pedidosSoportanStockProcesado($pdo)) {
        return 'stock_procesado, fecha_stock_procesado';
    }

    return '0 AS stock_procesado, NULL AS fecha_stock_procesado';
}

function consultarPedidos(PDO $pdo, array $filtros = [], ?int $usuarioId = null): array
{
    $columnasStock = columnasStockProcesadoSelect($pdo);
    $sql = 'SELECT id, codigo_pedido, usuario_creacion_id, usuario_creacion, estado, observaciones, total_lineas, total_bultos,
                   usuario_gestion_id, usuario_gestion, fecha_creacion, fecha_ultima_gestion,
                   ' . $columnasStock . '
            FROM pedidos
            WHERE 1 = 1';
    $params = [];

    if ($usuarioId !== null && $usuarioId > 0) {
        $sql .= ' AND usuario_creacion_id = :usuario_creacion_id';
        $params[':usuario_creacion_id'] = $usuarioId;
    }

    $codigoPedido = trim((string) ($filtros['codigo_pedido'] ?? ''));
    if ($codigoPedido !== '') {
        $sql .= ' AND codigo_pedido LIKE :codigo_pedido';
        $params[':codigo_pedido'] = '%' . $codigoPedido . '%';
    }

    $estado = trim((string) ($filtros['estado'] ?? ''));
    if ($estado !== '' && array_key_exists($estado, estadosPedidoDisponibles())) {
        $sql .= ' AND estado = :estado';
        $params[':estado'] = $estado;
    }

    $usuarioCreacion = trim((string) ($filtros['usuario_creacion'] ?? ''));
    if ($usuarioCreacion !== '') {
        $sql .= ' AND usuario_creacion LIKE :usuario_creacion';
        $params[':usuario_creacion'] = '%' . $usuarioCreacion . '%';
    }

    $sql .= ' ORDER BY fecha_creacion DESC, id DESC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function consultarPedidoPorId(PDO $pdo, int $pedidoId): ?array
{
    if ($pedidoId <= 0) {
        return null;
    }

    $columnasStock = columnasStockProcesadoSelect($pdo);
    $stmt = $pdo->prepare(
        'SELECT id, codigo_pedido, usuario_creacion_id, usuario_creacion, estado, observaciones, total_lineas, total_bultos,
                usuario_gestion_id, usuario_gestion, fecha_creacion, fecha_ultima_gestion,
                ' . $columnasStock . '
         FROM pedidos
         WHERE id = :id
         LIMIT 1'
    );
    $stmt->execute([':id' => $pedidoId]);
    $pedido = $stmt->fetch();

    return is_array($pedido) ? $pedido : null;
}

function consultarLineasPedido(PDO $pdo, int $pedidoId): array
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

function usuarioPuedeVerPedido(array $pedido, array $usuario): bool
{
    if (puedeGestionarPedidos()) {
        return true;
    }

    $usuarioId = isset($usuario['user_id']) ? (int) $usuario['user_id'] : 0;
    if ($usuarioId > 0 && (int) ($pedido['usuario_creacion_id'] ?? 0) === $usuarioId) {
        return true;
    }

    $username = trim((string) ($usuario['username'] ?? ''));
    return $username !== '' && trim((string) ($pedido['usuario_creacion'] ?? '')) === $username;
}

function pedidoPerteneceAUsuario(array $pedido, array $usuario): bool
{
    $usuarioId = isset($usuario['user_id']) ? (int) $usuario['user_id'] : 0;
    if ($usuarioId > 0 && (int) ($pedido['usuario_creacion_id'] ?? 0) === $usuarioId) {
        return true;
    }

    $username = trim((string) ($usuario['username'] ?? ''));
    return $username !== '' && trim((string) ($pedido['usuario_creacion'] ?? '')) === $username;
}

function pedidoEstaEnCreacion(array $pedido): bool
{
    return normalizarEstadoPedido((string) ($pedido['estado'] ?? PEDIDO_ESTADO_PENDIENTE)) === PEDIDO_ESTADO_PENDIENTE;
}

function usuarioPuedeEditarOCancelarPedidoEnCreacion(array $pedido, array $usuario): bool
{
    if (!puedeCrearPedidos()) {
        return false;
    }

    if (!pedidoEstaEnCreacion($pedido)) {
        return false;
    }

    return pedidoPerteneceAUsuario($pedido, $usuario);
}

function generarCodigoPedidoDesdeId(int $pedidoId, DateTimeInterface $fechaCreacion): string
{
    return 'PED-' . $fechaCreacion->format('Ymd') . '-' . str_pad((string) $pedidoId, 6, '0', STR_PAD_LEFT);
}

function resumirLineasPedido(array $lineas): array
{
    $totalBultos = 0;

    foreach ($lineas as $linea) {
        $totalBultos += (int) ($linea['bultos'] ?? 0);
    }

    return [
        'total_lineas' => count($lineas),
        'total_bultos' => $totalBultos,
    ];
}

function crearPedido(PDO $pdo, array $inventarioIds, array $usuario, string $observaciones = ''): array
{
    if (!puedeCrearPedidos()) {
        throw new RuntimeException('No tienes permisos para crear pedidos.');
    }

    $inventarioIds = normalizarIdsPedido($inventarioIds);
    if ($inventarioIds === []) {
        throw new RuntimeException('Selecciona al menos una linea de inventario para crear el pedido.');
    }

    $usuarioId = isset($usuario['user_id']) && (int) $usuario['user_id'] > 0 ? (int) $usuario['user_id'] : null;
    $username = trim((string) ($usuario['username'] ?? ''));
    if ($username === '') {
        throw new RuntimeException('No se ha podido identificar al usuario creador del pedido.');
    }

    $observaciones = trim($observaciones);
    $fechaCreacion = new DateTimeImmutable('now', new DateTimeZone('Europe/Madrid'));

    try {
        $pdo->beginTransaction();

        $lineasComprometidas = obtenerLineasComprometidasPorInventarioIds($pdo, $inventarioIds);
        if ($lineasComprometidas !== []) {
            throw new RuntimeException(construirMensajeLineasComprometidas($lineasComprometidas));
        }

        $lineasInventario = consultarInventarioPorIds($pdo, $inventarioIds, INVENTARIO_ESTADO_ACTIVO);
        $idsEncontrados = array_map(static fn(array $fila): int => (int) ($fila['id'] ?? 0), $lineasInventario);
        $idsNoEncontrados = array_values(array_diff($inventarioIds, $idsEncontrados));

        if ($idsNoEncontrados !== []) {
            throw new RuntimeException('Algunas lineas seleccionadas ya no estan disponibles en inventario activo.');
        }

        $resumen = resumirLineasPedido($lineasInventario);
        $codigoTemporal = 'TMP-PED-' . $fechaCreacion->format('YmdHis') . '-' . bin2hex(random_bytes(4));

        $stmtPedido = $pdo->prepare(
            'INSERT INTO pedidos (
                codigo_pedido,
                usuario_creacion_id,
                usuario_creacion,
                estado,
                observaciones,
                total_lineas,
                total_bultos,
                fecha_creacion,
                fecha_ultima_gestion
             ) VALUES (
                :codigo_pedido,
                :usuario_creacion_id,
                :usuario_creacion,
                :estado,
                :observaciones,
                :total_lineas,
                :total_bultos,
                :fecha_creacion,
                NULL
             )'
        );
        $stmtPedido->execute([
            ':codigo_pedido' => $codigoTemporal,
            ':usuario_creacion_id' => $usuarioId,
            ':usuario_creacion' => $username,
            ':estado' => PEDIDO_ESTADO_PENDIENTE,
            ':observaciones' => $observaciones !== '' ? $observaciones : null,
            ':total_lineas' => $resumen['total_lineas'],
            ':total_bultos' => $resumen['total_bultos'],
            ':fecha_creacion' => $fechaCreacion->format('Y-m-d H:i:s'),
        ]);

        $pedidoId = (int) $pdo->lastInsertId();
        $codigoPedido = generarCodigoPedidoDesdeId($pedidoId, $fechaCreacion);

        $stmtCodigo = $pdo->prepare('UPDATE pedidos SET codigo_pedido = :codigo_pedido WHERE id = :id');
        $stmtCodigo->execute([
            ':codigo_pedido' => $codigoPedido,
            ':id' => $pedidoId,
        ]);

        $stmtLinea = $pdo->prepare(
            'INSERT INTO pedido_lineas (
                pedido_id,
                inventario_id,
                editorial,
                colegio,
                codigo_centro,
                ubicacion,
                fecha_entrada,
                bultos,
                destino,
                `orden`,
                indicador_completa
             ) VALUES (
                :pedido_id,
                :inventario_id,
                :editorial,
                :colegio,
                :codigo_centro,
                :ubicacion,
                :fecha_entrada,
                :bultos,
                :destino,
                :orden,
                :indicador_completa
             )'
        );

        foreach ($lineasInventario as $linea) {
            $stmtLinea->execute([
                ':pedido_id' => $pedidoId,
                ':inventario_id' => (int) ($linea['id'] ?? 0),
                ':editorial' => (string) ($linea['editorial'] ?? ''),
                ':colegio' => (string) ($linea['colegio'] ?? ''),
                ':codigo_centro' => (string) ($linea['codigo_centro'] ?? ''),
                ':ubicacion' => (string) ($linea['ubicacion'] ?? ''),
                ':fecha_entrada' => ($linea['fecha_entrada'] ?? null) !== '' ? $linea['fecha_entrada'] : null,
                ':bultos' => (int) ($linea['bultos'] ?? 0),
                ':destino' => ($linea['destino'] ?? null) !== '' ? $linea['destino'] : null,
                ':orden' => ($linea['orden'] ?? null) !== '' ? $linea['orden'] : null,
                ':indicador_completa' => ($linea['indicador_completa'] ?? null) !== '' ? $linea['indicador_completa'] : null,
            ]);
        }

        $pdo->commit();

        $pedido = consultarPedidoPorId($pdo, $pedidoId);
        if ($pedido === null) {
            throw new RuntimeException('No se ha podido recuperar el pedido creado.');
        }

        registrarEventoPedido($pdo, $pedidoId, [
            'tipo_evento' => PEDIDO_EVENTO_CREADO,
            'estado_nuevo' => PEDIDO_ESTADO_PENDIENTE,
            'usuario_id' => $usuarioId,
            'usuario' => $username,
            'descripcion' => 'Pedido creado por ' . $username . '.',
            'metadata' => [
                'codigo_pedido' => $codigoPedido,
                'total_lineas' => (int) ($resumen['total_lineas'] ?? 0),
                'total_bultos' => (int) ($resumen['total_bultos'] ?? 0),
            ],
            'fecha_evento' => $fechaCreacion,
        ]);

        registrarActividadSistema($pdo, [
            'usuario_id' => $usuarioId,
            'usuario' => $username,
            'tipo_evento' => ACTIVIDAD_TIPO_PEDIDO_CREADO,
            'entidad' => 'pedido',
            'entidad_id' => $pedidoId,
            'entidad_codigo' => $codigoPedido,
            'descripcion' => 'Creacion de pedido ' . $codigoPedido,
            'metadata' => [
                'total_lineas' => (int) ($resumen['total_lineas'] ?? 0),
                'total_bultos' => (int) ($resumen['total_bultos'] ?? 0),
            ],
            'fecha_evento' => $fechaCreacion,
        ]);

        return [
            'pedido' => $pedido,
            'lineas' => $lineasInventario,
            'email' => intentarNotificarPedidoPorEmail($pedido, $lineasInventario),
        ];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        if ($e instanceof RuntimeException) {
            throw $e;
        }

        throw new RuntimeException('No se ha podido crear el pedido en este momento.', 0, $e);
    }
}

function actualizarLineasPedidoEnCreacionPorEdelvives(
    PDO $pdo,
    int $pedidoId,
    array $inventarioIdsAgregar,
    array $inventarioIdsQuitar,
    array $usuario,
    ?string $observaciones = null
): array {
    if (!puedeCrearPedidos()) {
        throw new RuntimeException('No tienes permisos para modificar pedidos.');
    }

    $inventarioIdsAgregar = normalizarIdsPedido($inventarioIdsAgregar);
    $inventarioIdsQuitar = normalizarIdsPedido($inventarioIdsQuitar);
    $usuarioId = isset($usuario['user_id']) && (int) $usuario['user_id'] > 0 ? (int) $usuario['user_id'] : null;
    $username = trim((string) ($usuario['username'] ?? ''));
    $fechaGestion = new DateTimeImmutable('now', new DateTimeZone('Europe/Madrid'));

    try {
        $pdo->beginTransaction();

        $pedido = bloquearPedidoPorId($pdo, $pedidoId);
        if ($pedido === null) {
            throw new RuntimeException('El pedido solicitado no existe.');
        }

        if (!usuarioPuedeEditarOCancelarPedidoEnCreacion($pedido, $usuario)) {
            throw new RuntimeException('Solo puedes modificar tus pedidos en estado pendiente.');
        }

        $inventarioActualPedido = obtenerIdsInventarioPedido($pdo, $pedidoId);
        $inventarioActualMapa = array_fill_keys($inventarioActualPedido, true);

        $inventarioIdsQuitar = array_values(array_filter(
            $inventarioIdsQuitar,
            static fn(int $inventarioId): bool => isset($inventarioActualMapa[$inventarioId])
        ));

        $inventarioIdsAgregar = array_values(array_filter(
            $inventarioIdsAgregar,
            static fn(int $inventarioId): bool => !isset($inventarioActualMapa[$inventarioId])
        ));

        if ($inventarioIdsAgregar !== []) {
            $lineasComprometidas = obtenerLineasComprometidasPorInventarioIds($pdo, $inventarioIdsAgregar, $pedidoId);
            if ($lineasComprometidas !== []) {
                throw new RuntimeException(construirMensajeLineasComprometidas($lineasComprometidas));
            }

            $lineasInventarioAgregar = consultarInventarioPorIds($pdo, $inventarioIdsAgregar, INVENTARIO_ESTADO_ACTIVO);
            $idsEncontrados = array_map(static fn(array $fila): int => (int) ($fila['id'] ?? 0), $lineasInventarioAgregar);
            $idsNoEncontrados = array_values(array_diff($inventarioIdsAgregar, $idsEncontrados));

            if ($idsNoEncontrados !== []) {
                throw new RuntimeException('Algunas lineas que intentas anadir ya no estan disponibles en inventario activo.');
            }

            $stmtLinea = $pdo->prepare(
                'INSERT INTO pedido_lineas (
                    pedido_id,
                    inventario_id,
                    editorial,
                    colegio,
                    codigo_centro,
                    ubicacion,
                    fecha_entrada,
                    bultos,
                    destino,
                    `orden`,
                    indicador_completa
                 ) VALUES (
                    :pedido_id,
                    :inventario_id,
                    :editorial,
                    :colegio,
                    :codigo_centro,
                    :ubicacion,
                    :fecha_entrada,
                    :bultos,
                    :destino,
                    :orden,
                    :indicador_completa
                 )'
            );

            foreach ($lineasInventarioAgregar as $linea) {
                $stmtLinea->execute([
                    ':pedido_id' => $pedidoId,
                    ':inventario_id' => (int) ($linea['id'] ?? 0),
                    ':editorial' => (string) ($linea['editorial'] ?? ''),
                    ':colegio' => (string) ($linea['colegio'] ?? ''),
                    ':codigo_centro' => (string) ($linea['codigo_centro'] ?? ''),
                    ':ubicacion' => (string) ($linea['ubicacion'] ?? ''),
                    ':fecha_entrada' => ($linea['fecha_entrada'] ?? null) !== '' ? $linea['fecha_entrada'] : null,
                    ':bultos' => (int) ($linea['bultos'] ?? 0),
                    ':destino' => ($linea['destino'] ?? null) !== '' ? $linea['destino'] : null,
                    ':orden' => ($linea['orden'] ?? null) !== '' ? $linea['orden'] : null,
                    ':indicador_completa' => ($linea['indicador_completa'] ?? null) !== '' ? $linea['indicador_completa'] : null,
                ]);
            }
        }

        if ($inventarioIdsQuitar !== []) {
            $placeholders = [];
            $params = [':pedido_id' => $pedidoId];

            foreach ($inventarioIdsQuitar as $indice => $inventarioId) {
                $placeholder = ':inventario_id_' . $indice;
                $placeholders[] = $placeholder;
                $params[$placeholder] = $inventarioId;
            }

            $stmtQuitar = $pdo->prepare(
                'DELETE FROM pedido_lineas
                 WHERE pedido_id = :pedido_id
                   AND inventario_id IN (' . implode(', ', $placeholders) . ')'
            );
            $stmtQuitar->execute($params);
        }

        $stmtResumen = $pdo->prepare(
            'SELECT COUNT(*) AS total_lineas, COALESCE(SUM(bultos), 0) AS total_bultos
             FROM pedido_lineas
             WHERE pedido_id = :pedido_id'
        );
        $stmtResumen->execute([':pedido_id' => $pedidoId]);
        $resumen = $stmtResumen->fetch();

        $totalLineas = (int) ($resumen['total_lineas'] ?? 0);
        $totalBultos = (int) ($resumen['total_bultos'] ?? 0);

        if ($totalLineas <= 0) {
            throw new RuntimeException('El pedido no puede quedarse sin lineas. Quita menos lineas o cancelalo.');
        }

        $sqlActualizacionPedido = 'UPDATE pedidos
             SET total_lineas = :total_lineas,
                 total_bultos = :total_bultos,
                 usuario_gestion_id = :usuario_gestion_id,
                 usuario_gestion = :usuario_gestion,
                 fecha_ultima_gestion = :fecha_ultima_gestion';
        $paramsActualizacionPedido = [
            ':total_lineas' => $totalLineas,
            ':total_bultos' => $totalBultos,
            ':usuario_gestion_id' => $usuarioId,
            ':usuario_gestion' => $username !== '' ? $username : null,
            ':fecha_ultima_gestion' => $fechaGestion->format('Y-m-d H:i:s'),
            ':id' => $pedidoId,
        ];

        if ($observaciones !== null) {
            $sqlActualizacionPedido .= ',
                 observaciones = :observaciones';
            $observacionesLimpias = trim($observaciones);
            $paramsActualizacionPedido[':observaciones'] = $observacionesLimpias !== '' ? $observacionesLimpias : null;
        }

        $sqlActualizacionPedido .= '
             WHERE id = :id';

        $stmtActualizacion = $pdo->prepare($sqlActualizacionPedido);
        $stmtActualizacion->execute($paramsActualizacionPedido);

        $codigoPedido = trim((string) ($pedido['codigo_pedido'] ?? ''));
        registrarEventoPedido($pdo, $pedidoId, [
            'tipo_evento' => PEDIDO_EVENTO_CAMBIO_ESTADO,
            'estado_anterior' => PEDIDO_ESTADO_PENDIENTE,
            'estado_nuevo' => PEDIDO_ESTADO_PENDIENTE,
            'usuario_id' => $usuarioId,
            'usuario' => $username,
            'descripcion' => sprintf(
                'Pedido modificado por %s. Anadidas: %d. Quitadas: %d.',
                $username !== '' ? $username : 'solicitante',
                count($inventarioIdsAgregar),
                count($inventarioIdsQuitar)
            ),
            'metadata' => [
                'codigo_pedido' => $codigoPedido,
                'lineas_anadidas' => count($inventarioIdsAgregar),
                'lineas_quitadas' => count($inventarioIdsQuitar),
                'total_lineas' => $totalLineas,
                'total_bultos' => $totalBultos,
            ],
            'fecha_evento' => $fechaGestion,
        ]);

        registrarActividadSistema($pdo, [
            'usuario_id' => $usuarioId,
            'usuario' => $username,
            'tipo_evento' => ACTIVIDAD_TIPO_PEDIDO_ESTADO,
            'entidad' => 'pedido',
            'entidad_id' => $pedidoId,
            'entidad_codigo' => $codigoPedido !== '' ? $codigoPedido : null,
            'descripcion' => 'Modificacion de pedido en creacion ' . ($codigoPedido !== '' ? $codigoPedido : '#' . $pedidoId),
            'metadata' => [
                'estado' => PEDIDO_ESTADO_PENDIENTE,
                'lineas_anadidas' => count($inventarioIdsAgregar),
                'lineas_quitadas' => count($inventarioIdsQuitar),
                'total_lineas' => $totalLineas,
                'total_bultos' => $totalBultos,
            ],
            'fecha_evento' => $fechaGestion,
        ]);

        $pdo->commit();

        $pedidoActualizado = consultarPedidoPorId($pdo, $pedidoId);
        if ($pedidoActualizado === null) {
            throw new RuntimeException('No se ha podido recuperar el pedido modificado.');
        }

        return [
            'pedido' => $pedidoActualizado,
            'lineas_anadidas' => count($inventarioIdsAgregar),
            'lineas_quitadas' => count($inventarioIdsQuitar),
        ];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        if ($e instanceof RuntimeException) {
            throw $e;
        }

        throw new RuntimeException('No se ha podido modificar el pedido en este momento.', 0, $e);
    }
}

function cancelarPedidoEnCreacionPorEdelvives(PDO $pdo, int $pedidoId, array $usuario): array
{
    if (!puedeCrearPedidos()) {
        throw new RuntimeException('No tienes permisos para cancelar pedidos.');
    }

    $usuarioId = isset($usuario['user_id']) && (int) $usuario['user_id'] > 0 ? (int) $usuario['user_id'] : null;
    $username = trim((string) ($usuario['username'] ?? ''));
    $fechaGestion = new DateTimeImmutable('now', new DateTimeZone('Europe/Madrid'));

    try {
        $pdo->beginTransaction();

        $pedido = bloquearPedidoPorId($pdo, $pedidoId);
        if ($pedido === null) {
            throw new RuntimeException('El pedido solicitado no existe.');
        }

        if (!usuarioPuedeEditarOCancelarPedidoEnCreacion($pedido, $usuario)) {
            throw new RuntimeException('Solo puedes cancelar tus pedidos en estado pendiente.');
        }

        $idsInventario = obtenerIdsInventarioPedido($pdo, $pedidoId);
        $codigoPedido = trim((string) ($pedido['codigo_pedido'] ?? ''));
        $soportaCancelado = pedidosSoportanEstadoCancelado($pdo);

        if ($soportaCancelado) {
            $stmtCancelar = $pdo->prepare(
                'UPDATE pedidos
                 SET estado = :estado,
                     usuario_gestion_id = :usuario_gestion_id,
                     usuario_gestion = :usuario_gestion,
                     fecha_ultima_gestion = :fecha_ultima_gestion
                 WHERE id = :id'
            );
            $stmtCancelar->execute([
                ':estado' => PEDIDO_ESTADO_CANCELADO,
                ':usuario_gestion_id' => $usuarioId,
                ':usuario_gestion' => $username !== '' ? $username : null,
                ':fecha_ultima_gestion' => $fechaGestion->format('Y-m-d H:i:s'),
                ':id' => $pedidoId,
            ]);

            registrarEventoPedido($pdo, $pedidoId, [
                'tipo_evento' => PEDIDO_EVENTO_CAMBIO_ESTADO,
                'estado_anterior' => PEDIDO_ESTADO_PENDIENTE,
                'estado_nuevo' => PEDIDO_ESTADO_CANCELADO,
                'usuario_id' => $usuarioId,
                'usuario' => $username,
                'descripcion' => sprintf(
                    'Pedido cancelado por %s.',
                    $username !== '' ? $username : 'solicitante'
                ),
                'metadata' => [
                    'codigo_pedido' => $codigoPedido,
                    'lineas_liberadas' => count($idsInventario),
                ],
                'fecha_evento' => $fechaGestion,
            ]);
        } else {
            $stmtEliminarLineas = $pdo->prepare('DELETE FROM pedido_lineas WHERE pedido_id = :pedido_id');
            $stmtEliminarLineas->execute([':pedido_id' => $pedidoId]);

            $stmtEliminarPedido = $pdo->prepare('DELETE FROM pedidos WHERE id = :id LIMIT 1');
            $stmtEliminarPedido->execute([':id' => $pedidoId]);

            if ($stmtEliminarPedido->rowCount() !== 1) {
                throw new RuntimeException('No se ha podido cancelar el pedido en este momento.');
            }
        }

        registrarActividadSistema($pdo, [
            'usuario_id' => $usuarioId,
            'usuario' => $username,
            'tipo_evento' => ACTIVIDAD_TIPO_PEDIDO_ESTADO,
            'entidad' => 'pedido',
            'entidad_id' => $pedidoId,
            'entidad_codigo' => $codigoPedido !== '' ? $codigoPedido : null,
            'descripcion' => 'Cancelacion de pedido en creacion ' . ($codigoPedido !== '' ? $codigoPedido : '#' . $pedidoId),
            'metadata' => [
                'estado_nuevo' => PEDIDO_ESTADO_CANCELADO,
                'lineas_liberadas' => count($idsInventario),
                'cancelacion_fisica' => !$soportaCancelado,
            ],
            'fecha_evento' => $fechaGestion,
        ]);

        $pdo->commit();

        return [
            'codigo_pedido' => $codigoPedido,
            'lineas_liberadas' => count($idsInventario),
            'cancelacion_fisica' => !$soportaCancelado,
        ];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        if ($e instanceof RuntimeException) {
            throw $e;
        }

        throw new RuntimeException('No se ha podido cancelar el pedido en este momento.', 0, $e);
    }
}

function estadoPedidoProcesaStock(string $estado): bool
{
    $estado = normalizarEstadoPedido($estado);

    return in_array($estado, [PEDIDO_ESTADO_PREPARADO, PEDIDO_ESTADO_COMPLETADO], true);
}

function bloquearPedidoPorId(PDO $pdo, int $pedidoId): ?array
{
    if ($pedidoId <= 0) {
        return null;
    }

    $columnasStock = columnasStockProcesadoSelect($pdo);
    $stmt = $pdo->prepare(
        'SELECT id, codigo_pedido, usuario_creacion_id, usuario_creacion, estado, observaciones, total_lineas, total_bultos,
                usuario_gestion_id, usuario_gestion, fecha_creacion, fecha_ultima_gestion, ' . $columnasStock . '
         FROM pedidos
         WHERE id = :id
         LIMIT 1
         FOR UPDATE'
    );
    $stmt->execute([':id' => $pedidoId]);
    $pedido = $stmt->fetch();

    return is_array($pedido) ? $pedido : null;
}

function obtenerIdsInventarioPedido(PDO $pdo, int $pedidoId): array
{
    if ($pedidoId <= 0) {
        return [];
    }

    $stmt = $pdo->prepare(
        'SELECT inventario_id
         FROM pedido_lineas
         WHERE pedido_id = :pedido_id
         ORDER BY id ASC
         FOR UPDATE'
    );
    $stmt->execute([':pedido_id' => $pedidoId]);

    return normalizarIdsPedido($stmt->fetchAll(PDO::FETCH_COLUMN));
}

function construirNumeroSalidaPedido(array $pedido, int $albaranId, DateTimeInterface $fechaConfirmacion): string
{
    $codigoPedido = trim((string) ($pedido['codigo_pedido'] ?? ''));
    if ($codigoPedido !== '') {
        return substr($codigoPedido, 0, 50);
    }

    return 'PED-' . $fechaConfirmacion->format('Ymd') . '-' . str_pad((string) $albaranId, 6, '0', STR_PAD_LEFT);
}

function procesarStockPedido(
    PDO $pdo,
    array $pedido,
    string $estadoFinal,
    array $usuario,
    DateTimeImmutable $fechaGestion
): array {
    if ((int) ($pedido['stock_procesado'] ?? 0) === 1) {
        return [
            'movido_stock' => false,
            'solo_historico' => true,
            'numero_salida' => trim((string) ($pedido['codigo_pedido'] ?? '')),
            'lineas_procesadas' => 0,
            'lineas_ya_historico' => 0,
        ];
    }

    $pedidoId = (int) ($pedido['id'] ?? 0);
    $inventarioIds = obtenerIdsInventarioPedido($pdo, $pedidoId);
    if ($inventarioIds === []) {
        throw new RuntimeException('El pedido no contiene lineas para procesar stock.');
    }

    $lineasInventario = bloquearLineasInventarioParaConfirmacion($pdo, $inventarioIds);
    $lineasPorId = [];
    foreach ($lineasInventario as $lineaInventario) {
        $lineasPorId[(int) ($lineaInventario['id'] ?? 0)] = $lineaInventario;
    }

    $idsNoEncontrados = array_values(array_diff($inventarioIds, array_keys($lineasPorId)));
    if ($idsNoEncontrados !== []) {
        throw new RuntimeException('Algunas lineas del pedido no existen en inventario y no se puede completar la salida.');
    }

    $idsActivos = [];
    $idsHistorico = [];
    $idsNoValidos = [];

    foreach ($inventarioIds as $inventarioId) {
        $linea = $lineasPorId[$inventarioId] ?? null;
        $estadoLinea = (string) ($linea['estado'] ?? '');

        if ($estadoLinea === INVENTARIO_ESTADO_ACTIVO) {
            $idsActivos[] = $inventarioId;
            continue;
        }

        if ($estadoLinea === INVENTARIO_ESTADO_HISTORICO) {
            $idsHistorico[] = $inventarioId;
            continue;
        }

        $idsNoValidos[] = $inventarioId;
    }

    if ($idsNoValidos !== []) {
        throw new RuntimeException('No se puede procesar stock porque hay lineas fuera de estado activo/historico.');
    }

    $usuarioId = isset($usuario['user_id']) && (int) $usuario['user_id'] > 0 ? (int) $usuario['user_id'] : null;
    $username = trim((string) ($usuario['username'] ?? ''));
    $numeroSalida = construirNumeroSalidaPedido($pedido, $pedidoId, $fechaGestion);

    if ($idsActivos === []) {
        return [
            'movido_stock' => false,
            'solo_historico' => true,
            'numero_salida' => $numeroSalida,
            'lineas_procesadas' => 0,
            'lineas_ya_historico' => count($idsHistorico),
        ];
    }

    if ($idsActivos !== []) {
        $lineasActivas = [];
        foreach ($idsActivos as $idActivo) {
            $lineasActivas[] = $lineasPorId[$idActivo];
        }

        $resumen = resumirLineasAlbaranConfirmado($lineasActivas);
        $albaranId = insertarCabeceraAlbaranSalida($pdo, $resumen, $fechaGestion, $usuarioId, $username);
        actualizarNumeroAlbaranCabecera($pdo, $albaranId, $numeroSalida);
        insertarLineasAlbaranSalida($pdo, $albaranId, $idsActivos);
        moverLineasInventarioAHistorico($pdo, $idsActivos, $numeroSalida, $fechaGestion, $usuarioId, $username);

        registrarActividadSistema($pdo, [
            'usuario_id' => $usuarioId,
            'usuario' => $username,
            'tipo_evento' => ACTIVIDAD_TIPO_PEDIDO_STOCK,
            'entidad' => 'pedido',
            'entidad_id' => $pedidoId,
            'entidad_codigo' => trim((string) ($pedido['codigo_pedido'] ?? '')) !== '' ? (string) $pedido['codigo_pedido'] : null,
            'descripcion' => 'Stock descontado para pedido ' . (trim((string) ($pedido['codigo_pedido'] ?? '')) !== '' ? (string) $pedido['codigo_pedido'] : '#' . $pedidoId),
            'metadata' => [
                'estado_final' => $estadoFinal,
                'numero_salida' => $numeroSalida,
                'lineas_procesadas' => count($idsActivos),
                'lineas_ya_historico' => count($idsHistorico),
            ],
            'fecha_evento' => $fechaGestion,
        ]);
    }

    return [
        'movido_stock' => true,
        'solo_historico' => false,
        'numero_salida' => $numeroSalida,
        'lineas_procesadas' => count($idsActivos),
        'lineas_ya_historico' => count($idsHistorico),
    ];
}

function actualizarEstadoPedido(PDO $pdo, int $pedidoId, string $estado, array $usuario): void
{
    if (!puedeGestionarPedidos()) {
        throw new RuntimeException('No tienes permisos para gestionar pedidos.');
    }

    $estado = normalizarEstadoPedido($estado);
    $usuarioId = isset($usuario['user_id']) && (int) $usuario['user_id'] > 0 ? (int) $usuario['user_id'] : null;
    $username = trim((string) ($usuario['username'] ?? ''));
    $fechaGestion = new DateTimeImmutable('now', new DateTimeZone('Europe/Madrid'));

    try {
        $pdo->beginTransaction();

        $pedido = bloquearPedidoPorId($pdo, $pedidoId);
        if ($pedido === null) {
            throw new RuntimeException('El pedido solicitado no existe.');
        }

        $soportaStockProcesado = pedidosSoportanStockProcesado($pdo);
        if ($estado === PEDIDO_ESTADO_CANCELADO && !pedidosSoportanEstadoCancelado($pdo)) {
            throw new RuntimeException('El estado cancelado no esta disponible en este entorno.');
        }
        $estadoAnterior = normalizarEstadoPedido((string) ($pedido['estado'] ?? PEDIDO_ESTADO_PENDIENTE));
        $resultadoStock = null;
        $haMovidoStock = false;
        $soloHistorico = false;
        $seHaProcesadoStock = false;

        $pedidoYaProcesado = $soportaStockProcesado && (int) ($pedido['stock_procesado'] ?? 0) === 1;
        if (estadoPedidoProcesaStock($estado) && !$pedidoYaProcesado) {
            $resultadoStock = procesarStockPedido($pdo, $pedido, $estado, $usuario, $fechaGestion);
            $haMovidoStock = (bool) ($resultadoStock['movido_stock'] ?? false);
            $soloHistorico = (bool) ($resultadoStock['solo_historico'] ?? false);
            $seHaProcesadoStock = $haMovidoStock || ($soportaStockProcesado && $soloHistorico);
        }

        $sqlActualizacionPedido = 'UPDATE pedidos
             SET estado = :estado,
                 usuario_gestion_id = :usuario_gestion_id,
                 usuario_gestion = :usuario_gestion,
                 fecha_ultima_gestion = :fecha_ultima_gestion';
        $paramsActualizacionPedido = [
            ':estado' => $estado,
            ':usuario_gestion_id' => $usuarioId,
            ':usuario_gestion' => $username !== '' ? $username : null,
            ':fecha_ultima_gestion' => $fechaGestion->format('Y-m-d H:i:s'),
            ':id' => $pedidoId,
        ];

        if ($seHaProcesadoStock && $soportaStockProcesado) {
            $sqlActualizacionPedido .= ',
                 stock_procesado = 1,
                 fecha_stock_procesado = :fecha_stock_procesado';
            $paramsActualizacionPedido[':fecha_stock_procesado'] = $fechaGestion->format('Y-m-d H:i:s');
        }

        $sqlActualizacionPedido .= '
             WHERE id = :id';

        $stmt = $pdo->prepare($sqlActualizacionPedido);
        $stmt->execute($paramsActualizacionPedido);

        if ($haMovidoStock) {
            $codigoPedido = trim((string) ($pedido['codigo_pedido'] ?? ''));
            $descripcionStock = $estado === PEDIDO_ESTADO_PREPARADO
                ? 'Stock descontado por preparacion de pedido.'
                : 'Stock descontado por completado de pedido.';

            registrarEventoPedido($pdo, $pedidoId, [
                'tipo_evento' => PEDIDO_EVENTO_STOCK_PROCESADO,
                'estado_nuevo' => $estado,
                'usuario_id' => $usuarioId,
                'usuario' => $username,
                'descripcion' => $descripcionStock,
                'metadata' => [
                    'codigo_pedido' => $codigoPedido,
                    'numero_salida' => (string) ($resultadoStock['numero_salida'] ?? ''),
                    'lineas_procesadas' => (int) ($resultadoStock['lineas_procesadas'] ?? 0),
                    'lineas_ya_historico' => (int) ($resultadoStock['lineas_ya_historico'] ?? 0),
                ],
                'fecha_evento' => $fechaGestion,
            ]);
        }

        if ($estadoAnterior !== $estado) {
            $codigoPedido = trim((string) ($pedido['codigo_pedido'] ?? ''));

            registrarEventoPedido($pdo, $pedidoId, [
                'tipo_evento' => PEDIDO_EVENTO_CAMBIO_ESTADO,
                'estado_anterior' => $estadoAnterior,
                'estado_nuevo' => $estado,
                'usuario_id' => $usuarioId,
                'usuario' => $username,
                'descripcion' => sprintf(
                    'Estado cambiado de %s a %s por %s.',
                    strtolower(etiquetaEstadoPedido($estadoAnterior)),
                    strtolower(etiquetaEstadoPedido($estado)),
                    $username !== '' ? $username : 'sistema'
                ),
                'metadata' => [
                    'codigo_pedido' => $codigoPedido,
                ],
                'fecha_evento' => $fechaGestion,
            ]);

            registrarActividadSistema($pdo, [
                'usuario_id' => $usuarioId,
                'usuario' => $username,
                'tipo_evento' => ACTIVIDAD_TIPO_PEDIDO_ESTADO,
                'entidad' => 'pedido',
                'entidad_id' => $pedidoId,
                'entidad_codigo' => $codigoPedido !== '' ? $codigoPedido : null,
                'descripcion' => sprintf(
                    'Cambio de estado de pedido %s a %s',
                    $codigoPedido !== '' ? $codigoPedido : '#' . $pedidoId,
                    strtolower(etiquetaEstadoPedido($estado))
                ),
                'metadata' => [
                    'estado_anterior' => $estadoAnterior,
                    'estado_nuevo' => $estado,
                ],
                'fecha_evento' => $fechaGestion,
            ]);
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        if ($e instanceof RuntimeException) {
            throw $e;
        }

        throw new RuntimeException('No se ha podido actualizar el estado del pedido.', 0, $e);
    }
}

function configuracionEmailPedidos(): array
{
    $configFile = dirname(__DIR__) . '/config/config.php';
    if (!is_file($configFile)) {
        return ['enabled' => false];
    }

    $config = require $configFile;
    if (!is_array($config)) {
        return ['enabled' => false];
    }

    $enabled = (bool) ($config['pedido_email_enabled'] ?? false);
    $to = trim((string) ($config['pedido_email_to'] ?? ''));

    return [
        'enabled' => $enabled && $to !== '',
        'to' => $to,
        'from' => trim((string) ($config['pedido_email_from'] ?? '')),
    ];
}

function intentarNotificarPedidoPorEmail(array $pedido, array $lineas): array
{
    $config = configuracionEmailPedidos();
    if (($config['enabled'] ?? false) !== true) {
        return [
            'enabled' => false,
            'sent' => false,
            'message' => 'Notificacion email no configurada.',
        ];
    }

    if (!function_exists('mail')) {
        return [
            'enabled' => true,
            'sent' => false,
            'message' => 'La funcion mail() no esta disponible en el entorno.',
        ];
    }

    $asunto = 'Nuevo pedido interno ' . (string) ($pedido['codigo_pedido'] ?? '');
    $lineasTexto = [];

    foreach ($lineas as $linea) {
        $lineasTexto[] = sprintf(
            '- ID %s | %s | %s | %s bultos',
            (string) ($linea['id'] ?? ''),
            (string) ($linea['editorial'] ?? ''),
            (string) ($linea['colegio'] ?? ''),
            (string) ($linea['bultos'] ?? 0)
        );
    }

    $mensaje = implode("\n", [
        'Se ha creado un nuevo pedido interno.',
        'Codigo: ' . (string) ($pedido['codigo_pedido'] ?? ''),
        'Solicitante: ' . (string) ($pedido['usuario_creacion'] ?? ''),
        'Estado: ' . etiquetaEstadoPedido((string) ($pedido['estado'] ?? PEDIDO_ESTADO_PENDIENTE)),
        'Total lineas: ' . (string) ($pedido['total_lineas'] ?? 0),
        'Total bultos: ' . (string) ($pedido['total_bultos'] ?? 0),
        '',
        'Detalle:',
        implode("\n", $lineasTexto),
    ]);

    $headers = [];
    $from = trim((string) ($config['from'] ?? ''));
    if ($from !== '') {
        $headers[] = 'From: ' . $from;
    }
    $headers[] = 'Content-Type: text/plain; charset=UTF-8';

    $sent = @mail((string) $config['to'], $asunto, $mensaje, implode("\r\n", $headers));

    return [
        'enabled' => true,
        'sent' => $sent,
        'message' => $sent
            ? 'Notificacion email enviada correctamente.'
            : 'No se ha podido enviar la notificacion email.',
    ];
}
