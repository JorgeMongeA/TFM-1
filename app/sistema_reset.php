<?php

/**
 * Autor: Jorge Monge
 * Trabajo Final de Máster (TFM)
 * UOC - 2026
 */

declare(strict_types=1);

require_once __DIR__ . '/actividad.php';
require_once __DIR__ . '/inventario_sync_bidireccional.php';

function reiniciarSistemaCompleto(PDO $pdo, array $usuario): void
{
    $usuarioId = (int) ($usuario['user_id'] ?? 0);
    $username = trim((string) ($usuario['username'] ?? ''));

    if ($usuarioId <= 0 || $username === '') {
        throw new RuntimeException('No se ha podido identificar al usuario autenticado.');
    }

    $fecha = new DateTimeImmutable('now', new DateTimeZone('Europe/Madrid'));

    try {
        $pdo->beginTransaction();

        $pdo->exec('DELETE FROM albaranes_salida_lineas');
        $pdo->exec('DELETE FROM pedido_lineas');
        $pdo->exec('DELETE FROM pedido_eventos');
        $pdo->exec('DELETE FROM pedidos');
        $pdo->exec('DELETE FROM albaranes_salida');
        $pdo->exec('DELETE FROM inventario');
        $pdo->exec('DELETE FROM password_resets');
        $pdo->exec('DELETE FROM notificaciones');
        $pdo->exec('DELETE FROM actividad_sistema');

        registrarActividadSistema($pdo, [
            'usuario_id' => $usuarioId,
            'usuario' => $username,
            'tipo_evento' => 'sistema_reiniciado',
            'entidad' => 'sistema',
            'descripcion' => 'Sistema reiniciado por usuario ' . $username,
            'fecha_evento' => $fecha,
        ]);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw new RuntimeException('No se ha podido reiniciar el sistema.', 0, $e);
    }

    intentarResetGoogleSheets();
}

function intentarResetGoogleSheets(): void
{
    try {
        $config = cargarConfiguracion();
        $url = obtenerUrlWebAppGoogleSheets($config);
        if ($url === '') {
            registrarLogAppsScript('error', '', 'reset', ['action' => 'reset'], 'URL no configurada');
            return;
        }

        $payload = ['action' => 'reset'];
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        registrarLogAppsScript('request', $url, 'reset', $payload);

        $response = false;

        if ($body !== false) {
            $contexto = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => "Content-Type: application/json\r\nAccept: application/json\r\n",
                    'content' => $body,
                    'timeout' => 10,
                    'ignore_errors' => true,
                ],
            ]);

            $response = @file_get_contents($url, false, $contexto);
        }

        if ($response === false && function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body !== false ? $body : json_encode($payload));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);

            $response = curl_exec($ch);
            if ($response === false) {
                $response = 'ERROR: ' . curl_error($ch);
            }
            curl_close($ch);
        }

        registrarLogAppsScript('response', $url, 'reset', $payload, (string) $response);
    } catch (Throwable $e) {
        registrarLogAppsScript('error', $url ?? '', 'reset', ['action' => 'reset'], 'ERROR: ' . $e->getMessage());
    }
}
