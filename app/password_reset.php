<?php

/**
 * Autor: Jorge Monge
 * Trabajo Final de Máster (TFM)
 * UOC - 2026
 */

declare(strict_types=1);

require_once __DIR__ . '/actividad.php';
require_once __DIR__ . '/notificaciones.php';
require_once __DIR__ . '/usuarios.php';

const PASSWORD_RESET_EXPIRACION_MINUTOS = 60;
const PASSWORD_RESET_MENSAJE_NEUTRO = 'Si el usuario existe, la solicitud se ha registrado correctamente.';

function passwordResetSoportado(PDO $pdo): bool
{
    static $cache = null;

    if (is_bool($cache)) {
        return $cache;
    }

    if (!usuariosSoportanGestion($pdo)) {
        $cache = false;
        return $cache;
    }

    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'password_resets'");
        $cache = $stmt->fetch() !== false;
        return $cache;
    } catch (Throwable $e) {
        $cache = false;
        return $cache;
    }
}

function buscarUsuarioRecuperacion(PDO $pdo, string $identificador): ?array
{
    if (!passwordResetSoportado($pdo)) {
        return null;
    }

    $identificador = trim($identificador);
    if ($identificador === '') {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT u.id, u.username, u.email, u.aprobado, u.activo, u.rechazado
         FROM usuarios u
         WHERE u.username = :identificador
            OR u.email = :identificador
         LIMIT 1'
    );
    $stmt->execute([':identificador' => $identificador]);
    $usuario = $stmt->fetch();

    return is_array($usuario) ? $usuario : null;
}

function crearSolicitudRecuperacionPassword(PDO $pdo, string $identificador): array
{
    $respuestaNeutra = [
        'ok' => true,
        'mensaje' => PASSWORD_RESET_MENSAJE_NEUTRO,
    ];

    if (!passwordResetSoportado($pdo)) {
        return $respuestaNeutra;
    }

    $usuario = buscarUsuarioRecuperacion($pdo, $identificador);
    if ($usuario === null) {
        registrarAuditoriaRecuperacionPassword($pdo, $identificador, 'usuario_no_encontrado');
        return $respuestaNeutra;
    }

    if ((int) ($usuario['rechazado'] ?? 0) === 1) {
        registrarAuditoriaRecuperacionPassword($pdo, $identificador, 'usuario_rechazado', $usuario);
        return $respuestaNeutra;
    }

    if ((int) ($usuario['aprobado'] ?? 0) !== 1 || (int) ($usuario['activo'] ?? 0) !== 1) {
        registrarAuditoriaRecuperacionPassword(
            $pdo,
            $identificador,
            (int) ($usuario['aprobado'] ?? 0) !== 1 ? 'cuenta_pendiente' : 'cuenta_desactivada',
            $usuario
        );
        return $respuestaNeutra;
    }

    $tokenPlano = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $tokenPlano);
    $fecha = new DateTimeImmutable('now', new DateTimeZone('Europe/Madrid'));
    $expira = $fecha->modify('+' . PASSWORD_RESET_EXPIRACION_MINUTOS . ' minutes');

    try {
        $pdo->beginTransaction();

        $stmtDelete = $pdo->prepare('DELETE FROM password_resets WHERE usuario_id = :usuario_id');
        $stmtDelete->execute([':usuario_id' => (int) $usuario['id']]);

        $stmtInsert = $pdo->prepare(
            'INSERT INTO password_resets (
                usuario_id,
                email,
                token_hash,
                expira_en,
                usado_en,
                creado_en
             ) VALUES (
                :usuario_id,
                :email,
                :token_hash,
                :expira_en,
                NULL,
                :creado_en
             )'
        );
        $stmtInsert->execute([
            ':usuario_id' => (int) $usuario['id'],
            ':email' => trim((string) ($usuario['email'] ?? '')),
            ':token_hash' => $tokenHash,
            ':expira_en' => $expira->format('Y-m-d H:i:s'),
            ':creado_en' => $fecha->format('Y-m-d H:i:s'),
        ]);

        registrarActividadSistema($pdo, [
            'usuario_id' => (int) $usuario['id'],
            'usuario' => (string) ($usuario['username'] ?? ''),
            'tipo_evento' => 'password_reset_requested',
            'entidad' => 'usuario',
            'entidad_id' => (int) $usuario['id'],
            'entidad_codigo' => (string) ($usuario['username'] ?? ''),
            'descripcion' => 'Solicitud de recuperación de contraseña para usuario ' . (string) ($usuario['username'] ?? ''),
            'metadata' => [
                'expira_en' => $expira->format('Y-m-d H:i:s'),
            ],
            'fecha_evento' => $fecha,
        ]);

        try {
            $stmtNotificacion = $pdo->prepare(
                "INSERT INTO notificaciones (usuario_destino, tipo, mensaje, leida, fecha)
                 VALUES ('almacen', 'reset_password', :mensaje, 0, NOW())"
            );
            $stmtNotificacion->execute([
                ':mensaje' => construirMensajeNotificacionResetPassword($usuario, $tokenPlano, $expira),
            ]);
        } catch (Throwable $e) {
            error_log('[PASSWORD_RESET] No se pudo crear notificacion para almacen: ' . $e->getMessage());
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        registrarAuditoriaRecuperacionPassword($pdo, $identificador, 'error_generando_token', $usuario, [
            'error' => $e->getMessage(),
        ]);
    }

    return $respuestaNeutra;
}

function construirMensajeNotificacionResetPassword(array $usuario, string $tokenPlano, DateTimeInterface $expira): string
{
    $username = trim((string) ($usuario['username'] ?? ''));
    return 'Recuperación de contraseña solicitada para ' . $username
        . '. Enlace: ' . construirUrlResetPassword($tokenPlano)
        . ' | Caduca: ' . $expira->format('d/m/Y H:i');
}

function obtenerResetPasswordValido(PDO $pdo, string $tokenPlano): ?array
{
    if (!passwordResetSoportado($pdo)) {
        return null;
    }

    $tokenPlano = trim($tokenPlano);
    if ($tokenPlano === '') {
        return null;
    }

    $tokenHash = hash('sha256', $tokenPlano);
    $fecha = new DateTimeImmutable('now', new DateTimeZone('Europe/Madrid'));

    $stmt = $pdo->prepare(
        'SELECT pr.id, pr.usuario_id, pr.email, pr.token_hash, pr.expira_en, pr.usado_en, pr.creado_en,
                u.username, u.email AS usuario_email, u.aprobado, u.activo, u.rechazado
         FROM password_resets pr
         INNER JOIN usuarios u ON u.id = pr.usuario_id
         WHERE pr.token_hash = :token_hash
           AND pr.usado_en IS NULL
           AND pr.expira_en >= :ahora
         LIMIT 1'
    );
    $stmt->execute([
        ':token_hash' => $tokenHash,
        ':ahora' => $fecha->format('Y-m-d H:i:s'),
    ]);
    $reset = $stmt->fetch();

    return is_array($reset) ? $reset : null;
}

function completarResetPassword(PDO $pdo, string $tokenPlano, string $passwordNueva, string $passwordConfirmacion): void
{
    if (!passwordResetSoportado($pdo)) {
        throw new RuntimeException('La recuperación de contraseña no está disponible hasta aplicar la migración de base de datos.');
    }

    if ($passwordNueva === '' || strlen($passwordNueva) < 8) {
        throw new RuntimeException('La nueva contraseña debe tener al menos 8 caracteres.');
    }

    if ($passwordNueva !== $passwordConfirmacion) {
        throw new RuntimeException('La nueva contraseña y su confirmación no coinciden.');
    }

    $reset = obtenerResetPasswordValido($pdo, $tokenPlano);
    if ($reset === null) {
        throw new RuntimeException('El enlace de recuperación no es válido o ha caducado.');
    }

    if ((int) ($reset['rechazado'] ?? 0) === 1 || (int) ($reset['aprobado'] ?? 0) !== 1 || (int) ($reset['activo'] ?? 0) !== 1) {
        throw new RuntimeException('La cuenta asociada no está disponible para recuperar la contraseña.');
    }

    $fecha = new DateTimeImmutable('now', new DateTimeZone('Europe/Madrid'));
    $hash = password_hash($passwordNueva, PASSWORD_DEFAULT);

    try {
        $pdo->beginTransaction();

        $stmtPassword = $pdo->prepare(
            'UPDATE usuarios
             SET password = :password,
                 actualizado_en = :actualizado_en
             WHERE id = :id'
        );
        $stmtPassword->execute([
            ':password' => $hash,
            ':actualizado_en' => $fecha->format('Y-m-d H:i:s'),
            ':id' => (int) $reset['usuario_id'],
        ]);

        $stmtUse = $pdo->prepare(
            'UPDATE password_resets
             SET usado_en = :usado_en
             WHERE id = :id'
        );
        $stmtUse->execute([
            ':usado_en' => $fecha->format('Y-m-d H:i:s'),
            ':id' => (int) $reset['id'],
        ]);

        $stmtDeleteOthers = $pdo->prepare(
            'DELETE FROM password_resets
             WHERE usuario_id = :usuario_id
               AND id <> :id'
        );
        $stmtDeleteOthers->execute([
            ':usuario_id' => (int) $reset['usuario_id'],
            ':id' => (int) $reset['id'],
        ]);

        registrarActividadSistema($pdo, [
            'usuario_id' => (int) $reset['usuario_id'],
            'usuario' => (string) ($reset['username'] ?? ''),
            'tipo_evento' => 'password_reset_completed',
            'entidad' => 'usuario',
            'entidad_id' => (int) $reset['usuario_id'],
            'entidad_codigo' => (string) ($reset['username'] ?? ''),
            'descripcion' => 'Restablecimiento de contraseña completado para usuario ' . (string) ($reset['username'] ?? ''),
            'fecha_evento' => $fecha,
        ]);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw new RuntimeException('No se ha podido restablecer la contraseña.', 0, $e);
    }
}

function construirUrlResetPassword(string $tokenPlano): string
{
    return rtrim(BASE_URL, '/') . '/password_reset.php?token=' . rawurlencode($tokenPlano);
}

function registrarAuditoriaRecuperacionPassword(PDO $pdo, string $identificador, string $resultado, ?array $usuario = null, array $extra = []): void
{
    $username = trim((string) ($usuario['username'] ?? ''));
    $metadata = array_merge([
        'identificador' => trim($identificador),
        'resultado' => $resultado,
    ], $extra);

    try {
        registrarActividadSistema($pdo, [
            'usuario_id' => isset($usuario['id']) ? (int) $usuario['id'] : null,
            'usuario' => $username !== '' ? $username : null,
            'tipo_evento' => 'password_reset_audit',
            'entidad' => 'usuario',
            'entidad_id' => isset($usuario['id']) ? (int) $usuario['id'] : null,
            'entidad_codigo' => $username !== '' ? $username : trim($identificador),
            'descripcion' => 'Intento de recuperación de contraseña registrado internamente.',
            'metadata' => $metadata,
        ]);
    } catch (Throwable $e) {
        error_log('Auditoría de recuperación de contraseña no registrada: ' . $e->getMessage());
    }
}
