<?php

declare(strict_types=1);

require_once __DIR__ . '/actividad.php';
require_once __DIR__ . '/usuarios.php';

const PASSWORD_RESET_EXPIRACION_MINUTOS = 60;

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
        $stmt = $pdo->query('SHOW TABLES LIKE \'password_resets\'');
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
        'SELECT u.id, u.username, u.email, u.aprobado, u.activo
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
        'mensaje' => 'Si la cuenta existe, recibiras un correo para restablecer la contrasena.',
        'email' => [
            'enabled' => false,
            'sent' => false,
            'message' => 'Recuperacion no disponible en este entorno.',
        ],
    ];

    if (!passwordResetSoportado($pdo)) {
        return $respuestaNeutra;
    }

    $usuario = buscarUsuarioRecuperacion($pdo, $identificador);
    if ($usuario === null) {
        return $respuestaNeutra;
    }

    if ((int) ($usuario['aprobado'] ?? 0) !== 1 || (int) ($usuario['activo'] ?? 0) !== 1) {
        return $respuestaNeutra;
    }

    $email = trim((string) ($usuario['email'] ?? ''));
    if ($email === '') {
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
            ':email' => $email,
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
            'descripcion' => 'Solicitud de recuperacion de contrasena para usuario ' . (string) ($usuario['username'] ?? ''),
            'fecha_evento' => $fecha,
        ]);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        return $respuestaNeutra;
    }

    $resultadoEmail = enviarEmailRecuperacionPassword($usuario, $tokenPlano, $expira);

    return [
        'ok' => true,
        'mensaje' => 'Si la cuenta existe, recibiras un correo para restablecer la contrasena.',
        'email' => $resultadoEmail,
    ];
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
                u.username, u.email AS usuario_email, u.aprobado, u.activo
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
        throw new RuntimeException('La recuperacion de contrasena no esta disponible hasta aplicar la migracion de base de datos.');
    }

    if ($passwordNueva === '' || strlen($passwordNueva) < 8) {
        throw new RuntimeException('La nueva contrasena debe tener al menos 8 caracteres.');
    }

    if ($passwordNueva !== $passwordConfirmacion) {
        throw new RuntimeException('La nueva contrasena y su confirmacion no coinciden.');
    }

    $reset = obtenerResetPasswordValido($pdo, $tokenPlano);
    if ($reset === null) {
        throw new RuntimeException('El enlace de recuperacion no es valido o ha caducado.');
    }

    if ((int) ($reset['aprobado'] ?? 0) !== 1 || (int) ($reset['activo'] ?? 0) !== 1) {
        throw new RuntimeException('La cuenta asociada no esta disponible para recuperar la contrasena.');
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
            'descripcion' => 'Restablecimiento de contrasena completado para usuario ' . (string) ($reset['username'] ?? ''),
            'fecha_evento' => $fecha,
        ]);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        if ($e instanceof RuntimeException) {
            throw $e;
        }

        throw new RuntimeException('No se ha podido restablecer la contrasena.', 0, $e);
    }
}

function configuracionEmailPasswordReset(): array
{
    $configFile = dirname(__DIR__) . '/config/config.php';
    if (!is_file($configFile)) {
        return ['enabled' => false];
    }

    $config = require $configFile;
    if (!is_array($config)) {
        return ['enabled' => false];
    }

    $enabled = (bool) ($config['password_reset_email_enabled'] ?? false);
    $from = trim((string) ($config['password_reset_email_from'] ?? ''));

    return [
        'enabled' => $enabled,
        'from' => $from,
    ];
}

function construirUrlResetPassword(string $tokenPlano): string
{
    return rtrim(BASE_URL, '/') . '/password_reset.php?token=' . rawurlencode($tokenPlano);
}

function enviarEmailRecuperacionPassword(array $usuario, string $tokenPlano, DateTimeInterface $expira): array
{
    $config = configuracionEmailPasswordReset();
    if (($config['enabled'] ?? false) !== true) {
        return [
            'enabled' => false,
            'sent' => false,
            'message' => 'Recuperacion por email no activada en este entorno.',
        ];
    }

    if (!function_exists('mail')) {
        return [
            'enabled' => true,
            'sent' => false,
            'message' => 'La funcion mail() no esta disponible en el entorno.',
        ];
    }

    $email = trim((string) ($usuario['email'] ?? $usuario['usuario_email'] ?? ''));
    if ($email === '') {
        return [
            'enabled' => true,
            'sent' => false,
            'message' => 'La cuenta no tiene email asociado.',
        ];
    }

    $asunto = 'Recuperacion de contrasena';
    $url = construirUrlResetPassword($tokenPlano);
    $mensaje = implode("\n", [
        'Hemos recibido una solicitud para restablecer la contrasena de tu cuenta.',
        'Usuario: ' . (string) ($usuario['username'] ?? ''),
        'Enlace de recuperacion: ' . $url,
        'Caduca el: ' . $expira->format('d/m/Y H:i'),
        '',
        'Si no has solicitado este cambio, puedes ignorar este mensaje.',
    ]);

    $headers = [];
    $from = trim((string) ($config['from'] ?? ''));
    if ($from !== '') {
        $headers[] = 'From: ' . $from;
    }
    $headers[] = 'Content-Type: text/plain; charset=UTF-8';

    $sent = @mail($email, $asunto, $mensaje, implode("\r\n", $headers));

    return [
        'enabled' => true,
        'sent' => $sent,
        'message' => $sent
            ? 'Correo de recuperacion enviado correctamente.'
            : 'No se ha podido enviar el correo de recuperacion.',
    ];
}
