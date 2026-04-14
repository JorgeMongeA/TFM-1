<?php

declare(strict_types=1);

require_once __DIR__ . '/actividad.php';

const EMPRESA_RECOGIDA_POR_DEFECTO = 'MAXIMO SERVICIOS LOGISTICOS S.L.U.';

function obtenerUsuarioOperacionActual(): array
{
    return [
        'user_id' => isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null,
        'username' => trim((string) ($_SESSION['username'] ?? $_SESSION['usuario'] ?? '')),
    ];
}

function confirmarAlbaranSalida(PDO $pdo, array $ids, array $usuario): array
{
    $ids = normalizarIdsSalida($ids);
    if ($ids === []) {
        throw new RuntimeException('No se ha recibido ninguna linea valida para confirmar.');
    }

    $usuarioId = isset($usuario['user_id']) && (int) $usuario['user_id'] > 0 ? (int) $usuario['user_id'] : null;
    $username = trim((string) ($usuario['username'] ?? ''));
    $fechaConfirmacion = new DateTimeImmutable('now', new DateTimeZone('Europe/Madrid'));

    try {
        $pdo->beginTransaction();

        $lineas = bloquearLineasInventarioParaConfirmacion($pdo, $ids);
        validarLineasConfirmables($lineas, $ids);

        $resumen = resumirLineasAlbaranConfirmado($lineas);
        $albaranId = insertarCabeceraAlbaranSalida($pdo, $resumen, $fechaConfirmacion, $usuarioId, $username);
        $numeroAlbaran = construirNumeroAlbaranDesdeId($albaranId, $fechaConfirmacion);
        actualizarNumeroAlbaranCabecera($pdo, $albaranId, $numeroAlbaran);
        insertarLineasAlbaranSalida($pdo, $albaranId, $ids);
        moverLineasInventarioAHistorico($pdo, $ids, $numeroAlbaran, $fechaConfirmacion, $usuarioId, $username);
        registrarActividadSistema($pdo, [
            'usuario_id' => $usuarioId,
            'usuario' => $username,
            'tipo_evento' => ACTIVIDAD_TIPO_ALBARAN_CONFIRMADO,
            'entidad' => 'albaran',
            'entidad_id' => $albaranId,
            'entidad_codigo' => $numeroAlbaran,
            'descripcion' => 'Confirmacion de albaran ' . $numeroAlbaran,
            'metadata' => [
                'total_lineas' => (int) ($resumen['total_lineas'] ?? 0),
                'total_bultos' => (int) ($resumen['total_bultos'] ?? 0),
            ],
            'fecha_evento' => $fechaConfirmacion,
        ]);

        $pdo->commit();

        return [
            'albaran_id' => $albaranId,
            'numero_albaran' => $numeroAlbaran,
            'fecha_confirmacion' => $fechaConfirmacion->format('Y-m-d H:i:s'),
            'total_lineas' => $resumen['total_lineas'],
            'total_bultos' => $resumen['total_bultos'],
        ];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        if ($e instanceof RuntimeException) {
            throw $e;
        }

        throw new RuntimeException('No se pudo confirmar el albaran en este momento.', 0, $e);
    }
}

function normalizarIdsSalida(array $ids): array
{
    $ids = array_map(static fn(mixed $valor): int => (int) $valor, $ids);
    $ids = array_filter($ids, static fn(int $valor): bool => $valor > 0);

    return array_values(array_unique($ids));
}

function bloquearLineasInventarioParaConfirmacion(PDO $pdo, array $ids): array
{
    $placeholders = [];
    $params = [];

    foreach ($ids as $indice => $id) {
        $placeholder = ':id_' . $indice;
        $placeholders[] = $placeholder;
        $params[$placeholder] = $id;
    }

    $stmt = $pdo->prepare(
        'SELECT id, editorial, colegio, codigo_centro, ubicacion, fecha_entrada, fecha_salida, bultos, destino, `orden`, indicador_completa, estado, numero_albaran
         FROM inventario
         WHERE id IN (' . implode(', ', $placeholders) . ')
         FOR UPDATE'
    );
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function validarLineasConfirmables(array $lineas, array $idsSolicitados): void
{
    if ($lineas === []) {
        throw new RuntimeException('No se han encontrado lineas de inventario para confirmar.');
    }

    $lineasPorId = [];
    foreach ($lineas as $linea) {
        $lineasPorId[(int) ($linea['id'] ?? 0)] = $linea;
    }

    $idsNoEncontrados = array_values(array_diff($idsSolicitados, array_keys($lineasPorId)));
    if ($idsNoEncontrados !== []) {
        throw new RuntimeException('No se pueden confirmar lineas inexistentes o ya no disponibles.');
    }

    $idsNoActivos = [];

    foreach ($lineasPorId as $id => $linea) {
        if (($linea['estado'] ?? '') !== INVENTARIO_ESTADO_ACTIVO) {
            $idsNoActivos[] = $id;
        }
    }

    if ($idsNoActivos !== []) {
        throw new RuntimeException('Algunas lineas ya estaban confirmadas y no pueden procesarse de nuevo.');
    }
}

function resumirLineasAlbaranConfirmado(array $lineas): array
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

function insertarCabeceraAlbaranSalida(
    PDO $pdo,
    array $resumen,
    DateTimeImmutable $fechaConfirmacion,
    ?int $usuarioId,
    string $username
): int {
    $numeroTemporal = 'TMP-' . $fechaConfirmacion->format('YmdHis') . '-' . bin2hex(random_bytes(4));

    $stmt = $pdo->prepare(
        'INSERT INTO albaranes_salida (
            numero_albaran,
            fecha_confirmacion,
            usuario_confirmacion_id,
            usuario_confirmacion,
            empresa_recogida,
            total_lineas,
            total_bultos
         ) VALUES (
            :numero_albaran,
            :fecha_confirmacion,
            :usuario_confirmacion_id,
            :usuario_confirmacion,
            :empresa_recogida,
            :total_lineas,
            :total_bultos
         )'
    );
    $stmt->execute([
        ':numero_albaran' => $numeroTemporal,
        ':fecha_confirmacion' => $fechaConfirmacion->format('Y-m-d H:i:s'),
        ':usuario_confirmacion_id' => $usuarioId,
        ':usuario_confirmacion' => $username !== '' ? $username : null,
        ':empresa_recogida' => EMPRESA_RECOGIDA_POR_DEFECTO,
        ':total_lineas' => (int) ($resumen['total_lineas'] ?? 0),
        ':total_bultos' => (int) ($resumen['total_bultos'] ?? 0),
    ]);

    return (int) $pdo->lastInsertId();
}

function construirNumeroAlbaranDesdeId(int $albaranId, DateTimeInterface $fechaConfirmacion): string
{
    return 'SAL-' . $fechaConfirmacion->format('Ymd') . '-' . str_pad((string) $albaranId, 6, '0', STR_PAD_LEFT);
}

function actualizarNumeroAlbaranCabecera(PDO $pdo, int $albaranId, string $numeroAlbaran): void
{
    $stmt = $pdo->prepare(
        'UPDATE albaranes_salida
         SET numero_albaran = :numero_albaran
         WHERE id = :id'
    );
    $stmt->execute([
        ':numero_albaran' => $numeroAlbaran,
        ':id' => $albaranId,
    ]);
}

function insertarLineasAlbaranSalida(PDO $pdo, int $albaranId, array $ids): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO albaranes_salida_lineas (albaran_id, inventario_id)
         VALUES (:albaran_id, :inventario_id)'
    );

    foreach ($ids as $id) {
        $stmt->execute([
            ':albaran_id' => $albaranId,
            ':inventario_id' => $id,
        ]);
    }
}

function moverLineasInventarioAHistorico(
    PDO $pdo,
    array $ids,
    string $numeroAlbaran,
    DateTimeImmutable $fechaConfirmacion,
    ?int $usuarioId,
    string $username
): void {
    $placeholders = [];
    $params = [
        ':estado' => INVENTARIO_ESTADO_HISTORICO,
        ':numero_albaran' => $numeroAlbaran,
        ':fecha_confirmacion_salida' => $fechaConfirmacion->format('Y-m-d H:i:s'),
        ':usuario_confirmacion_id' => $usuarioId,
        ':usuario_confirmacion' => $username !== '' ? $username : null,
    ];

    foreach ($ids as $indice => $id) {
        $placeholder = ':id_' . $indice;
        $placeholders[] = $placeholder;
        $params[$placeholder] = $id;
    }

    $stmt = $pdo->prepare(
        'UPDATE inventario
         SET estado = :estado,
             numero_albaran = :numero_albaran,
             fecha_confirmacion_salida = :fecha_confirmacion_salida,
             usuario_confirmacion_id = :usuario_confirmacion_id,
             usuario_confirmacion = :usuario_confirmacion,
             sync_pendiente_historico = 1,
             fecha_sync_historico = NULL
         WHERE estado = \'activo\'
           AND id IN (' . implode(', ', $placeholders) . ')'
    );
    $stmt->execute($params);

    if ($stmt->rowCount() !== count($ids)) {
        throw new RuntimeException('No se han podido actualizar todas las lineas a historico.');
    }
}
