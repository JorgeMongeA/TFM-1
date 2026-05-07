<?php

/**
 * Autor: Jorge Monge
 * Trabajo Final de Máster (TFM)
 * UOC - 2026
 */

declare(strict_types=1);

require_once __DIR__ . '/auth.php';

const ACTIVIDAD_TIPO_PEDIDO_CREADO = 'pedido_creado';
const ACTIVIDAD_TIPO_PEDIDO_ESTADO = 'pedido_estado';
const ACTIVIDAD_TIPO_PEDIDO_STOCK = 'pedido_stock';
const ACTIVIDAD_TIPO_ALBARAN_CONFIRMADO = 'albaran_confirmado';
const ACTIVIDAD_TIPO_SYNC_HISTORICO = 'sync_historico';
const ACTIVIDAD_TIPO_INVENTARIO_ANULADO = 'inventario_anulado';
const ACTIVIDAD_TIPO_INVENTARIO_PDF = 'inventario_pdf';

const PEDIDO_EVENTO_CREADO = 'pedido_creado';
const PEDIDO_EVENTO_CAMBIO_ESTADO = 'pedido_estado';
const PEDIDO_EVENTO_STOCK_PROCESADO = 'pedido_stock';

function obtenerContextoActividadActual(): array
{
    return [
        'user_id' => isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null,
        'username' => trim((string) ($_SESSION['username'] ?? $_SESSION['usuario'] ?? '')),
        'rol' => obtenerRolUsuario(),
    ];
}

function registrarActividadSistema(PDO $pdo, array $actividad): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO actividad_sistema (
            usuario_id,
            usuario,
            tipo_evento,
            entidad,
            entidad_id,
            entidad_codigo,
            descripcion,
            metadata_json,
            fecha_evento
         ) VALUES (
            :usuario_id,
            :usuario,
            :tipo_evento,
            :entidad,
            :entidad_id,
            :entidad_codigo,
            :descripcion,
            :metadata_json,
            :fecha_evento
         )'
    );

    $metadata = $actividad['metadata'] ?? null;
    $metadataJson = null;

    if (is_array($metadata) && $metadata !== []) {
        $json = json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $metadataJson = $json !== false ? $json : null;
    }

    $stmt->execute([
        ':usuario_id' => isset($actividad['usuario_id']) && (int) $actividad['usuario_id'] > 0 ? (int) $actividad['usuario_id'] : null,
        ':usuario' => valorTextoNullable($actividad['usuario'] ?? null),
        ':tipo_evento' => trim((string) ($actividad['tipo_evento'] ?? 'actividad')),
        ':entidad' => trim((string) ($actividad['entidad'] ?? 'sistema')),
        ':entidad_id' => isset($actividad['entidad_id']) && (int) $actividad['entidad_id'] > 0 ? (int) $actividad['entidad_id'] : null,
        ':entidad_codigo' => valorTextoNullable($actividad['entidad_codigo'] ?? null),
        ':descripcion' => trim((string) ($actividad['descripcion'] ?? 'Actividad registrada en el sistema.')),
        ':metadata_json' => $metadataJson,
        ':fecha_evento' => normalizarFechaEvento($actividad['fecha_evento'] ?? null),
    ]);
}

function registrarEventoPedido(PDO $pdo, int $pedidoId, array $evento): void
{
    if ($pedidoId <= 0) {
        return;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO pedido_eventos (
            pedido_id,
            tipo_evento,
            estado_anterior,
            estado_nuevo,
            usuario_id,
            usuario,
            descripcion,
            metadata_json,
            fecha_evento
         ) VALUES (
            :pedido_id,
            :tipo_evento,
            :estado_anterior,
            :estado_nuevo,
            :usuario_id,
            :usuario,
            :descripcion,
            :metadata_json,
            :fecha_evento
         )'
    );

    $metadata = $evento['metadata'] ?? null;
    $metadataJson = null;

    if (is_array($metadata) && $metadata !== []) {
        $json = json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $metadataJson = $json !== false ? $json : null;
    }

    $stmt->execute([
        ':pedido_id' => $pedidoId,
        ':tipo_evento' => trim((string) ($evento['tipo_evento'] ?? PEDIDO_EVENTO_CAMBIO_ESTADO)),
        ':estado_anterior' => valorTextoNullable($evento['estado_anterior'] ?? null),
        ':estado_nuevo' => valorTextoNullable($evento['estado_nuevo'] ?? null),
        ':usuario_id' => isset($evento['usuario_id']) && (int) $evento['usuario_id'] > 0 ? (int) $evento['usuario_id'] : null,
        ':usuario' => valorTextoNullable($evento['usuario'] ?? null),
        ':descripcion' => trim((string) ($evento['descripcion'] ?? 'Evento registrado para el pedido.')),
        ':metadata_json' => $metadataJson,
        ':fecha_evento' => normalizarFechaEvento($evento['fecha_evento'] ?? null),
    ]);
}

function obtenerUltimaActividad(PDO $pdo, int $limit = 8): array
{
    $limit = max(1, min(100, $limit));
    $tiposOcultos = tiposActividadOcultaEnDashboard();
    $sql = 'SELECT id, usuario_id, usuario, tipo_evento, entidad, entidad_id, entidad_codigo, descripcion, metadata_json, fecha_evento
            FROM actividad_sistema';
    $params = [];

    if ($tiposOcultos !== []) {
        $placeholders = [];
        foreach ($tiposOcultos as $indice => $tipoOculto) {
            $placeholder = ':tipo_oculto_' . $indice;
            $placeholders[] = $placeholder;
            $params[$placeholder] = $tipoOculto;
        }

        $sql .= ' WHERE tipo_evento NOT IN (' . implode(', ', $placeholders) . ')';
    }

    $sql .= ' ORDER BY fecha_evento DESC, id DESC LIMIT ' . $limit;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $actividades = $stmt->fetchAll();

    foreach ($actividades as &$actividad) {
        $actividad['metadata'] = decodificarMetadataActividad($actividad['metadata_json'] ?? null);
        $actividad['tiempo_relativo'] = formatearTiempoRelativo((string) ($actividad['fecha_evento'] ?? ''));
        $actividad['fecha_evento_legible'] = formatearFechaHora((string) ($actividad['fecha_evento'] ?? ''));
        $actividad['badge'] = actividadBadge((string) ($actividad['tipo_evento'] ?? ''));
        $actividad['icono'] = actividadIcono((string) ($actividad['tipo_evento'] ?? ''));
    }
    unset($actividad);

    return $actividades;
}

function tiposActividadOcultaEnDashboard(): array
{
    return [
        'password_reset_audit',
        'campana_confirmacion_validada',
    ];
}

function obtenerTimelinePedido(PDO $pdo, int $pedidoId): array
{
    if ($pedidoId <= 0) {
        return [];
    }

    $stmt = $pdo->prepare(
        'SELECT id, pedido_id, tipo_evento, estado_anterior, estado_nuevo, usuario_id, usuario, descripcion, metadata_json, fecha_evento
         FROM pedido_eventos
         WHERE pedido_id = :pedido_id
         ORDER BY fecha_evento ASC, id ASC'
    );
    $stmt->execute([':pedido_id' => $pedidoId]);
    $eventos = $stmt->fetchAll();

    foreach ($eventos as &$evento) {
        $evento['metadata'] = decodificarMetadataActividad($evento['metadata_json'] ?? null);
        $evento['fecha_evento_legible'] = formatearFechaHora((string) ($evento['fecha_evento'] ?? ''));
        $evento['tiempo_relativo'] = formatearTiempoRelativo((string) ($evento['fecha_evento'] ?? ''));
        $evento['badge'] = timelineBadgePedido($evento);
        $evento['icono'] = timelineIconoPedido($evento);
    }
    unset($evento);

    return $eventos;
}

function actividadBadge(string $tipoEvento): string
{
    return match ($tipoEvento) {
        ACTIVIDAD_TIPO_PEDIDO_CREADO => 'text-bg-primary',
        ACTIVIDAD_TIPO_PEDIDO_ESTADO => 'text-bg-info',
        ACTIVIDAD_TIPO_PEDIDO_STOCK => 'text-bg-success',
        ACTIVIDAD_TIPO_ALBARAN_CONFIRMADO => 'text-bg-success',
        ACTIVIDAD_TIPO_SYNC_HISTORICO => 'text-bg-warning',
        ACTIVIDAD_TIPO_INVENTARIO_ANULADO => 'text-bg-dark',
        ACTIVIDAD_TIPO_INVENTARIO_PDF => 'text-bg-secondary',
        'usuario_creado', 'usuario_aprobado', 'usuario_activado', 'usuario_desactivado', 'usuario_rol', 'usuario_password' => 'text-bg-primary',
        'usuario_rechazado', 'usuario_eliminado' => 'text-bg-danger',
        'password_reset_requested', 'password_reset_completed' => 'text-bg-secondary',
        'sistema_reiniciado' => 'text-bg-dark',
        'campana_confirmacion_validada', 'password_reset_audit' => 'text-bg-dark',
        default => 'text-bg-secondary',
    };
}

function actividadEtiqueta(string $tipoEvento): string
{
    return match ($tipoEvento) {
        ACTIVIDAD_TIPO_PEDIDO_CREADO => 'Pedido',
        ACTIVIDAD_TIPO_PEDIDO_ESTADO => 'Estado',
        ACTIVIDAD_TIPO_PEDIDO_STOCK => 'Stock',
        ACTIVIDAD_TIPO_ALBARAN_CONFIRMADO => 'Albaran',
        ACTIVIDAD_TIPO_SYNC_HISTORICO => 'Sync',
        ACTIVIDAD_TIPO_INVENTARIO_ANULADO => 'Anulacion',
        ACTIVIDAD_TIPO_INVENTARIO_PDF => 'PDF',
        'usuario_creado', 'usuario_aprobado', 'usuario_activado', 'usuario_desactivado', 'usuario_rol', 'usuario_password', 'usuario_rechazado', 'usuario_eliminado' => 'Usuario',
        'password_reset_requested', 'password_reset_completed' => 'Acceso',
        'sistema_reiniciado' => 'Sistema',
        'campana_confirmacion_validada' => 'Campana',
        'password_reset_audit' => 'Auditoria',
        default => 'Sistema',
    };
}

function actividadIcono(string $tipoEvento): string
{
    return match ($tipoEvento) {
        ACTIVIDAD_TIPO_PEDIDO_CREADO => 'P',
        ACTIVIDAD_TIPO_PEDIDO_ESTADO => 'E',
        ACTIVIDAD_TIPO_PEDIDO_STOCK => 'ST',
        ACTIVIDAD_TIPO_ALBARAN_CONFIRMADO => 'A',
        ACTIVIDAD_TIPO_SYNC_HISTORICO => 'S',
        ACTIVIDAD_TIPO_INVENTARIO_ANULADO => 'X',
        ACTIVIDAD_TIPO_INVENTARIO_PDF => 'PDF',
        'usuario_creado', 'usuario_aprobado', 'usuario_activado', 'usuario_desactivado', 'usuario_rol', 'usuario_password' => 'U',
        'usuario_rechazado', 'usuario_eliminado' => 'U',
        'password_reset_requested', 'password_reset_completed' => 'R',
        'sistema_reiniciado' => 'S',
        'campana_confirmacion_validada' => 'C',
        'password_reset_audit' => 'A',
        default => '*',
    };
}

function timelineBadgePedido(array $evento): string
{
    $tipoEvento = (string) ($evento['tipo_evento'] ?? '');
    $estadoNuevo = (string) ($evento['estado_nuevo'] ?? '');

    if ($tipoEvento === PEDIDO_EVENTO_CREADO) {
        return 'text-bg-primary';
    }
    if ($tipoEvento === PEDIDO_EVENTO_STOCK_PROCESADO) {
        return 'text-bg-success';
    }

    return match ($estadoNuevo) {
        'en_preparacion' => 'text-bg-warning',
        'preparado' => 'text-bg-info',
        'completado' => 'text-bg-success',
        'cancelado' => 'text-bg-dark',
        default => 'text-bg-secondary',
    };
}

function timelineIconoPedido(array $evento): string
{
    $tipoEvento = (string) ($evento['tipo_evento'] ?? '');
    if ($tipoEvento === PEDIDO_EVENTO_CREADO) {
        return '+';
    }
    if ($tipoEvento === PEDIDO_EVENTO_STOCK_PROCESADO) {
        return 'S';
    }

    return match ((string) ($evento['estado_nuevo'] ?? '')) {
        'en_preparacion' => '1',
        'preparado' => '2',
        'completado' => '3',
        'cancelado' => 'X',
        default => '*',
    };
}

function formatearTiempoRelativo(string $fecha): string
{
    $fechaNormalizada = trim($fecha);
    if ($fechaNormalizada === '') {
        return '';
    }

    try {
        $evento = new DateTimeImmutable($fechaNormalizada, new DateTimeZone('Europe/Madrid'));
        $ahora = new DateTimeImmutable('now', new DateTimeZone('Europe/Madrid'));
    } catch (Throwable $e) {
        return '';
    }

    $segundos = $ahora->getTimestamp() - $evento->getTimestamp();
    if ($segundos < 60) {
        return 'Hace menos de 1 minuto';
    }

    if ($segundos < 3600) {
        $minutos = (int) floor($segundos / 60);
        return 'Hace ' . $minutos . ' minuto' . ($minutos === 1 ? '' : 's');
    }

    if ($segundos < 86400) {
        $horas = (int) floor($segundos / 3600);
        return 'Hace ' . $horas . ' hora' . ($horas === 1 ? '' : 's');
    }

    $dias = (int) floor($segundos / 86400);
    if ($dias < 7) {
        return 'Hace ' . $dias . ' dia' . ($dias === 1 ? '' : 's');
    }

    return $evento->format('d/m/Y H:i');
}

function formatearFechaHora(string $fecha): string
{
    $fechaNormalizada = trim($fecha);
    if ($fechaNormalizada === '') {
        return '-';
    }

    try {
        $date = new DateTimeImmutable($fechaNormalizada, new DateTimeZone('Europe/Madrid'));
        return $date->format('d/m/Y H:i');
    } catch (Throwable $e) {
        return $fechaNormalizada;
    }
}

function valorTextoNullable(mixed $valor): ?string
{
    $texto = trim((string) $valor);
    return $texto !== '' ? $texto : null;
}

function normalizarFechaEvento(mixed $fecha): string
{
    if ($fecha instanceof DateTimeInterface) {
        return $fecha->format('Y-m-d H:i:s');
    }

    $texto = trim((string) $fecha);
    if ($texto !== '') {
        return $texto;
    }

    return (new DateTimeImmutable('now', new DateTimeZone('Europe/Madrid')))->format('Y-m-d H:i:s');
}

function decodificarMetadataActividad(mixed $metadataJson): array
{
    $texto = trim((string) $metadataJson);
    if ($texto === '') {
        return [];
    }

    $metadata = json_decode($texto, true);
    return is_array($metadata) ? $metadata : [];
}
