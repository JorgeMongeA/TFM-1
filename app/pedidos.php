<?php

/**
 * Autor: Jorge Monge
 * Trabajo Final de Máster (TFM)
 * UOC - 2026
 */

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/inventario.php';
require_once __DIR__ . '/actividad.php';

const PEDIDO_ESTADO_PENDIENTE = 'pendiente';
const PEDIDO_ESTADO_EN_PREPARACION = 'en_preparacion';
const PEDIDO_ESTADO_PREPARADO = 'preparado';
const PEDIDO_ESTADO_COMPLETADO = 'completado';

function estadosPedidoDisponibles(): array
{
    return [
        PEDIDO_ESTADO_PENDIENTE => 'Pendiente',
        PEDIDO_ESTADO_EN_PREPARACION => 'En preparacion',
        PEDIDO_ESTADO_PREPARADO => 'Preparado',
        PEDIDO_ESTADO_COMPLETADO => 'Completado',
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

function consultarPedidos(PDO $pdo, array $filtros = [], ?int $usuarioId = null): array
{
    $sql = 'SELECT id, codigo_pedido, usuario_creacion_id, usuario_creacion, estado, observaciones, total_lineas, total_bultos,
                   usuario_gestion_id, usuario_gestion, fecha_creacion, fecha_ultima_gestion
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

    $stmt = $pdo->prepare(
        'SELECT id, codigo_pedido, usuario_creacion_id, usuario_creacion, estado, observaciones, total_lineas, total_bultos,
                usuario_gestion_id, usuario_gestion, fecha_creacion, fecha_ultima_gestion
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

function actualizarEstadoPedido(PDO $pdo, int $pedidoId, string $estado, array $usuario): void
{
    if (!puedeGestionarPedidos()) {
        throw new RuntimeException('No tienes permisos para gestionar pedidos.');
    }

    $pedido = consultarPedidoPorId($pdo, $pedidoId);
    if ($pedido === null) {
        throw new RuntimeException('El pedido solicitado no existe.');
    }

    $estado = normalizarEstadoPedido($estado);
    $estadoAnterior = normalizarEstadoPedido((string) ($pedido['estado'] ?? PEDIDO_ESTADO_PENDIENTE));
    $usuarioId = isset($usuario['user_id']) && (int) $usuario['user_id'] > 0 ? (int) $usuario['user_id'] : null;
    $username = trim((string) ($usuario['username'] ?? ''));
    $fechaGestion = new DateTimeImmutable('now', new DateTimeZone('Europe/Madrid'));

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare(
            'UPDATE pedidos
             SET estado = :estado,
                 usuario_gestion_id = :usuario_gestion_id,
                 usuario_gestion = :usuario_gestion,
                 fecha_ultima_gestion = :fecha_ultima_gestion
             WHERE id = :id'
        );
        $stmt->execute([
            ':estado' => $estado,
            ':usuario_gestion_id' => $usuarioId,
            ':usuario_gestion' => $username !== '' ? $username : null,
            ':fecha_ultima_gestion' => $fechaGestion->format('Y-m-d H:i:s'),
            ':id' => $pedidoId,
        ]);

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
