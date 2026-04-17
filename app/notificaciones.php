<?php

/**
 * Autor: Jorge Monge
 * Trabajo Final de Máster (TFM)
 * UOC - 2026
 */

declare(strict_types=1);

const NOTIFICACION_TIPO_ALTA_USUARIO = 'usuario_nuevo';
const NOTIFICACION_TIPO_RESET_PASSWORD = 'reset_password';
const NOTIFICACION_TIPO_EVENTO_CRITICO = 'evento_critico';

function notificacionesSoportadas(PDO $pdo): bool
{
    static $cache = null;

    if (is_bool($cache)) {
        return $cache;
    }

    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'notificaciones'");
        $cache = $stmt->fetch() !== false;
        return $cache;
    } catch (Throwable $e) {
        $cache = false;
        return $cache;
    }
}

function crearNotificacion(PDO $pdo, string $usuarioDestino, string $tipo, string $mensaje): void
{
    $usuarioDestino = trim($usuarioDestino);
    $tipo = trim($tipo);
    $mensaje = trim($mensaje);

    if ($usuarioDestino === '' || $tipo === '' || $mensaje === '') {
        return;
    }

    try {
        $stmt = $pdo->prepare(
            'INSERT INTO notificaciones (usuario_destino, tipo, mensaje, leida, fecha)
             VALUES (:usuario_destino, :tipo, :mensaje, 0, :fecha)'
        );
        $stmt->execute([
            ':usuario_destino' => $usuarioDestino,
            ':tipo' => $tipo,
            ':mensaje' => $mensaje,
            ':fecha' => (new DateTimeImmutable('now', new DateTimeZone('Europe/Madrid')))->format('Y-m-d H:i:s'),
        ]);
    } catch (Throwable $e) {
        error_log('[NOTIFICACIONES] Error al insertar notificacion para ' . $usuarioDestino . ': ' . $e->getMessage());
    }
}

function listarNotificacionesUsuario(PDO $pdo, string $usuarioDestino, int $limit = 10): array
{
    if (!notificacionesSoportadas($pdo)) {
        return [];
    }

    $limit = max(1, min(50, $limit));
    $stmt = $pdo->prepare(
        'SELECT id, usuario_destino, tipo, mensaje, leida, fecha
         FROM notificaciones
         WHERE usuario_destino = :usuario_destino
         ORDER BY fecha DESC, id DESC
         LIMIT ' . $limit
    );
    $stmt->execute([':usuario_destino' => trim($usuarioDestino)]);
    return $stmt->fetchAll();
}

function contarNotificacionesNoLeidas(PDO $pdo, string $usuarioDestino): int
{
    if (!notificacionesSoportadas($pdo)) {
        return 0;
    }

    $stmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM notificaciones
         WHERE usuario_destino = :usuario_destino
           AND leida = 0'
    );
    $stmt->execute([':usuario_destino' => trim($usuarioDestino)]);
    return (int) $stmt->fetchColumn();
}

function marcarNotificacionLeida(PDO $pdo, int $notificacionId, string $usuarioDestino): void
{
    if (!notificacionesSoportadas($pdo) || $notificacionId <= 0) {
        return;
    }

    $stmt = $pdo->prepare(
        'UPDATE notificaciones
         SET leida = 1
         WHERE id = :id
           AND usuario_destino = :usuario_destino'
    );
    $stmt->execute([
        ':id' => $notificacionId,
        ':usuario_destino' => trim($usuarioDestino),
    ]);
}
