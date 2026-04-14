<?php

declare(strict_types=1);

require_once __DIR__ . '/actividad.php';

const USUARIO_ESTADO_PENDIENTE = 'pendiente';
const USUARIO_ESTADO_ACTIVO = 'activo';
const USUARIO_ESTADO_DESACTIVADO = 'desactivado';
const USUARIO_CIRCUITO_SOLICITUDES_USERNAME = 'almacen';
const USUARIO_CIRCUITO_SOLICITUDES_EMAIL = 'almacen@maximosl.com';

function rolesFuncionalesUsuarios(): array
{
    return [
        ROL_ALMACEN => 'Almacen',
        ROL_EDELVIVES => 'Edelvives',
    ];
}

function usuariosSoportanGestion(PDO $pdo): bool
{
    static $cache = null;

    if (is_bool($cache)) {
        return $cache;
    }

    $columnasNecesarias = [
        'email',
        'activo',
        'aprobado',
        'aprobado_por_id',
        'fecha_aprobacion',
        'creado_en',
        'actualizado_en',
    ];

    try {
        $stmt = $pdo->query('SHOW COLUMNS FROM usuarios');
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

function leerFormularioUsuarioDesdeRequest(array $source): array
{
    return [
        'username' => trim((string) ($source['username'] ?? '')),
        'email' => trim((string) ($source['email'] ?? '')),
        'password' => (string) ($source['password'] ?? ''),
        'password_confirmacion' => (string) ($source['password_confirmacion'] ?? ''),
        'rol_id' => (int) ($source['rol_id'] ?? 0),
    ];
}

function rolesAsignablesUsuarios(PDO $pdo): array
{
    $stmt = $pdo->query(
        "SELECT id, nombre
         FROM roles
         WHERE nombre IN ('almacen', 'edelvives')
         ORDER BY FIELD(nombre, 'almacen', 'edelvives'), id ASC"
    );
    $roles = $stmt->fetchAll();

    $esperados = array_keys(rolesFuncionalesUsuarios());
    $disponibles = array_map(
        static fn(array $rol): string => trim((string) ($rol['nombre'] ?? '')),
        $roles
    );
    $faltantes = array_values(array_diff($esperados, $disponibles));

    if ($faltantes !== []) {
        throw new RuntimeException(
            'Faltan roles funcionales canonicos en la base de datos: ' . implode(', ', $faltantes) . '.'
        );
    }

    return $roles;
}

function rolIdAsignableUsuarios(PDO $pdo, int $rolId): bool
{
    if ($rolId <= 0) {
        return false;
    }

    foreach (rolesAsignablesUsuarios($pdo) as $rol) {
        if ((int) ($rol['id'] ?? 0) === $rolId) {
            return true;
        }
    }

    return false;
}

function rolesAsignablesPorNombre(PDO $pdo): array
{
    $rolesPorNombre = [];

    foreach (rolesAsignablesUsuarios($pdo) as $rol) {
        $nombre = trim((string) ($rol['nombre'] ?? ''));
        if ($nombre === '') {
            continue;
        }

        $rolesPorNombre[$nombre] = $rol;
    }

    return $rolesPorNombre;
}

function rolCanonicoPorIdUsuario(PDO $pdo, int $rolId): string
{
    $rol = obtenerRolPorId($pdo, $rolId);
    return normalizarRolAplicacion((string) ($rol['nombre'] ?? ''));
}

function idsRolesCompatibles(PDO $pdo, string $rolCanonico): array
{
    $rolCanonico = normalizarRolAplicacion($rolCanonico);
    if ($rolCanonico === '') {
        return [];
    }

    $stmt = $pdo->query('SELECT id, nombre FROM roles ORDER BY id ASC');
    $roles = $stmt->fetchAll();
    $ids = [];

    foreach ($roles as $rol) {
        if (normalizarRolAplicacion((string) ($rol['nombre'] ?? '')) !== $rolCanonico) {
            continue;
        }

        $rolId = (int) ($rol['id'] ?? 0);
        if ($rolId > 0) {
            $ids[] = $rolId;
        }
    }

    return array_values(array_unique($ids));
}

function contarUsuariosPendientes(PDO $pdo): int
{
    if (!usuariosSoportanGestion($pdo)) {
        return 0;
    }

    $stmt = $pdo->query('SELECT COUNT(*) FROM usuarios WHERE aprobado = 0');
    return (int) $stmt->fetchColumn();
}

function listarUsuariosGestion(PDO $pdo, array $filtros = []): array
{
    if (!usuariosSoportanGestion($pdo)) {
        throw new RuntimeException('La gestion avanzada de usuarios no esta disponible hasta aplicar la migracion de base de datos.');
    }

    $sql = 'SELECT u.id, u.username, u.email, u.rol_id, r.nombre AS rol_nombre, u.activo, u.aprobado,
                   u.aprobado_por_id, ua.username AS aprobado_por_username, u.fecha_aprobacion,
                   u.creado_en, u.actualizado_en
            FROM usuarios u
            LEFT JOIN roles r ON r.id = u.rol_id
            LEFT JOIN usuarios ua ON ua.id = u.aprobado_por_id
            WHERE 1 = 1';
    $params = [];

    $estado = trim((string) ($filtros['estado'] ?? ''));
    if ($estado !== '') {
        switch ($estado) {
            case USUARIO_ESTADO_PENDIENTE:
                $sql .= ' AND u.aprobado = 0';
                break;
            case USUARIO_ESTADO_ACTIVO:
                $sql .= ' AND u.aprobado = 1 AND u.activo = 1';
                break;
            case USUARIO_ESTADO_DESACTIVADO:
                $sql .= ' AND u.aprobado = 1 AND u.activo = 0';
                break;
        }
    }

    $rolId = (int) ($filtros['rol_id'] ?? 0);
    if ($rolId > 0) {
        $rolCanonicoFiltro = rolCanonicoPorIdUsuario($pdo, $rolId);
        $rolesCompatibles = $rolCanonicoFiltro !== '' ? idsRolesCompatibles($pdo, $rolCanonicoFiltro) : [$rolId];

        if ($rolesCompatibles === []) {
            $rolesCompatibles = [$rolId];
        }

        $placeholdersRol = [];
        foreach ($rolesCompatibles as $indice => $rolCompatibleId) {
            $placeholder = ':rol_id_' . $indice;
            $placeholdersRol[] = $placeholder;
            $params[$placeholder] = $rolCompatibleId;
        }

        $sql .= ' AND u.rol_id IN (' . implode(', ', $placeholdersRol) . ')';
    }

    $texto = trim((string) ($filtros['q'] ?? ''));
    if ($texto !== '') {
        $sql .= ' AND (u.username LIKE :q OR u.email LIKE :q)';
        $params[':q'] = '%' . $texto . '%';
    }

    $sql .= ' ORDER BY u.aprobado ASC, u.creado_en DESC, u.id DESC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $usuarios = $stmt->fetchAll();

    $rolesCanonicos = rolesAsignablesPorNombre($pdo);

    foreach ($usuarios as &$usuario) {
        $rolCanonico = normalizarRolAplicacion((string) ($usuario['rol_nombre'] ?? ''));
        $usuario['estado_gestion'] = estadoGestionUsuario($usuario);
        $usuario['estado_badge'] = claseEstadoGestionUsuario((string) $usuario['estado_gestion']);
        $usuario['rol_label'] = etiquetaRolUsuario($rolCanonico);
        $usuario['rol_id_asignable'] = (int) ($rolesCanonicos[$rolCanonico]['id'] ?? ($usuario['rol_id'] ?? 0));
    }
    unset($usuario);

    return $usuarios;
}

function estadoGestionUsuario(array $usuario): string
{
    if ((int) ($usuario['aprobado'] ?? 0) !== 1) {
        return USUARIO_ESTADO_PENDIENTE;
    }

    return (int) ($usuario['activo'] ?? 0) === 1
        ? USUARIO_ESTADO_ACTIVO
        : USUARIO_ESTADO_DESACTIVADO;
}

function claseEstadoGestionUsuario(string $estado): string
{
    return match ($estado) {
        USUARIO_ESTADO_ACTIVO => 'text-bg-success',
        USUARIO_ESTADO_DESACTIVADO => 'text-bg-secondary',
        default => 'text-bg-warning',
    };
}

function etiquetaEstadoGestionUsuario(string $estado): string
{
    return match ($estado) {
        USUARIO_ESTADO_ACTIVO => 'Activo',
        USUARIO_ESTADO_DESACTIVADO => 'Desactivado',
        default => 'Pendiente',
    };
}

function validarDatosNuevoUsuario(PDO $pdo, array $datos): array
{
    if (!usuariosSoportanGestion($pdo)) {
        return ['La gestion avanzada de usuarios no esta disponible hasta aplicar la migracion de base de datos.'];
    }

    $errores = [];

    $username = trim((string) ($datos['username'] ?? ''));
    $email = trim((string) ($datos['email'] ?? ''));
    $password = (string) ($datos['password'] ?? '');
    $passwordConfirmacion = (string) ($datos['password_confirmacion'] ?? '');
    $rolId = (int) ($datos['rol_id'] ?? 0);

    if ($username === '' || strlen($username) < 3) {
        $errores[] = 'El nombre de usuario debe tener al menos 3 caracteres.';
    }

    if (!preg_match('/^[a-zA-Z0-9._-]+$/', $username)) {
        $errores[] = 'El nombre de usuario solo puede contener letras, numeros, puntos, guiones y guion bajo.';
    }

    if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        $errores[] = 'Indica un email valido.';
    }

    if ($password === '' || strlen($password) < 8) {
        $errores[] = 'La contrasena debe tener al menos 8 caracteres.';
    }

    if ($password !== $passwordConfirmacion) {
        $errores[] = 'La contrasena y su confirmacion no coinciden.';
    }

    if (!rolIdAsignableUsuarios($pdo, $rolId)) {
        $errores[] = 'Selecciona un rol inicial valido.';
    }

    if ($username !== '' && existeUsernameUsuario($pdo, $username)) {
        $errores[] = 'El nombre de usuario ya existe.';
    }

    if ($email !== '' && existeEmailUsuario($pdo, $email)) {
        $errores[] = 'El email ya esta asociado a otra cuenta.';
    }

    return $errores;
}

function crearSolicitudUsuario(PDO $pdo, array $datos, ?array $solicitante = null): array
{
    if (!usuariosSoportanGestion($pdo)) {
        throw new RuntimeException('La gestion avanzada de usuarios no esta disponible hasta aplicar la migracion de base de datos.');
    }

    $errores = validarDatosNuevoUsuario($pdo, $datos);
    if ($errores !== []) {
        throw new RuntimeException($errores[0]);
    }

    $username = trim((string) $datos['username']);
    $email = trim((string) $datos['email']);
    $rolId = (int) $datos['rol_id'];
    $passwordHash = password_hash((string) $datos['password'], PASSWORD_DEFAULT);
    $fecha = new DateTimeImmutable('now', new DateTimeZone('Europe/Madrid'));

    $stmt = $pdo->prepare(
        'INSERT INTO usuarios (
            username,
            email,
            password,
            rol_id,
            activo,
            aprobado,
            aprobado_por_id,
            fecha_aprobacion,
            creado_en,
            actualizado_en
         ) VALUES (
            :username,
            :email,
            :password,
            :rol_id,
            0,
            0,
            NULL,
            NULL,
            :creado_en,
            :actualizado_en
         )'
    );
    $stmt->execute([
        ':username' => $username,
        ':email' => $email,
        ':password' => $passwordHash,
        ':rol_id' => $rolId,
        ':creado_en' => $fecha->format('Y-m-d H:i:s'),
        ':actualizado_en' => $fecha->format('Y-m-d H:i:s'),
    ]);

    $usuarioId = (int) $pdo->lastInsertId();
    $rol = obtenerRolPorId($pdo, $rolId);

    registrarActividadSistema($pdo, [
        'usuario_id' => isset($solicitante['user_id']) ? (int) $solicitante['user_id'] : null,
        'usuario' => trim((string) ($solicitante['username'] ?? $username)),
        'tipo_evento' => 'usuario_creado',
        'entidad' => 'usuario',
        'entidad_id' => $usuarioId,
        'entidad_codigo' => $username,
        'descripcion' => 'Creacion de usuario pendiente ' . $username,
        'metadata' => [
            'email' => $email,
            'rol_solicitado' => (string) ($rol['nombre'] ?? ''),
            'origen' => 'solicitud_publica',
            'circuito_gestion_username' => USUARIO_CIRCUITO_SOLICITUDES_USERNAME,
            'circuito_gestion_email' => USUARIO_CIRCUITO_SOLICITUDES_EMAIL,
        ],
        'fecha_evento' => $fecha,
    ]);

    return obtenerUsuarioGestionPorId($pdo, $usuarioId) ?? [];
}

function obtenerUsuarioGestionPorId(PDO $pdo, int $usuarioId): ?array
{
    if ($usuarioId <= 0) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT u.id, u.username, u.email, u.rol_id, r.nombre AS rol_nombre, u.activo, u.aprobado,
                u.aprobado_por_id, ua.username AS aprobado_por_username, u.fecha_aprobacion,
                u.creado_en, u.actualizado_en
         FROM usuarios u
         LEFT JOIN roles r ON r.id = u.rol_id
         LEFT JOIN usuarios ua ON ua.id = u.aprobado_por_id
         WHERE u.id = :id
         LIMIT 1'
    );
    $stmt->execute([':id' => $usuarioId]);
    $usuario = $stmt->fetch();

    return is_array($usuario) ? $usuario : null;
}

function aprobarUsuario(PDO $pdo, int $usuarioId, int $rolId, array $admin): void
{
    if (!usuariosSoportanGestion($pdo)) {
        throw new RuntimeException('La gestion avanzada de usuarios no esta disponible hasta aplicar la migracion de base de datos.');
    }

    $usuario = obtenerUsuarioGestionPorId($pdo, $usuarioId);
    if ($usuario === null) {
        throw new RuntimeException('El usuario indicado no existe.');
    }

    if (!rolIdAsignableUsuarios($pdo, $rolId)) {
        throw new RuntimeException('El rol seleccionado no es valido.');
    }
    $rol = obtenerRolPorId($pdo, $rolId);

    $adminId = isset($admin['user_id']) ? (int) $admin['user_id'] : null;
    $adminUsername = trim((string) ($admin['username'] ?? ''));
    $fecha = new DateTimeImmutable('now', new DateTimeZone('Europe/Madrid'));

    $stmt = $pdo->prepare(
        'UPDATE usuarios
         SET rol_id = :rol_id,
             aprobado = 1,
             activo = 1,
             aprobado_por_id = :aprobado_por_id,
             fecha_aprobacion = :fecha_aprobacion,
             actualizado_en = :actualizado_en
         WHERE id = :id'
    );
    $stmt->execute([
        ':rol_id' => $rolId,
        ':aprobado_por_id' => $adminId,
        ':fecha_aprobacion' => $fecha->format('Y-m-d H:i:s'),
        ':actualizado_en' => $fecha->format('Y-m-d H:i:s'),
        ':id' => $usuarioId,
    ]);

    registrarActividadSistema($pdo, [
        'usuario_id' => $adminId,
        'usuario' => $adminUsername,
        'tipo_evento' => 'usuario_aprobado',
        'entidad' => 'usuario',
        'entidad_id' => $usuarioId,
        'entidad_codigo' => (string) ($usuario['username'] ?? ''),
        'descripcion' => 'Aprobacion de usuario ' . (string) ($usuario['username'] ?? ''),
        'metadata' => [
            'rol_asignado' => (string) ($rol['nombre'] ?? ''),
        ],
        'fecha_evento' => $fecha,
    ]);
}

function actualizarEstadoYRolUsuario(PDO $pdo, int $usuarioId, int $rolId, bool $activo, array $admin): void
{
    if (!usuariosSoportanGestion($pdo)) {
        throw new RuntimeException('La gestion avanzada de usuarios no esta disponible hasta aplicar la migracion de base de datos.');
    }

    $usuario = obtenerUsuarioGestionPorId($pdo, $usuarioId);
    if ($usuario === null) {
        throw new RuntimeException('El usuario indicado no existe.');
    }

    if (!rolIdAsignableUsuarios($pdo, $rolId)) {
        throw new RuntimeException('El rol seleccionado no es valido.');
    }
    $rol = obtenerRolPorId($pdo, $rolId);

    if ((int) $usuario['id'] === (int) ($admin['user_id'] ?? 0) && $activo === false) {
        throw new RuntimeException('No puedes desactivar tu propia cuenta desde este panel.');
    }

    $fecha = new DateTimeImmutable('now', new DateTimeZone('Europe/Madrid'));
    $adminId = isset($admin['user_id']) ? (int) $admin['user_id'] : null;
    $adminUsername = trim((string) ($admin['username'] ?? ''));

    $stmt = $pdo->prepare(
        'UPDATE usuarios
         SET rol_id = :rol_id,
             activo = :activo,
             actualizado_en = :actualizado_en
         WHERE id = :id'
    );
    $stmt->execute([
        ':rol_id' => $rolId,
        ':activo' => $activo ? 1 : 0,
        ':actualizado_en' => $fecha->format('Y-m-d H:i:s'),
        ':id' => $usuarioId,
    ]);

    $rolAnterior = normalizarRolAplicacion((string) ($usuario['rol_nombre'] ?? ''));
    $rolNuevo = normalizarRolAplicacion((string) ($rol['nombre'] ?? ''));
    if ($rolAnterior !== $rolNuevo) {
        registrarActividadSistema($pdo, [
            'usuario_id' => $adminId,
            'usuario' => $adminUsername,
            'tipo_evento' => 'usuario_rol',
            'entidad' => 'usuario',
            'entidad_id' => $usuarioId,
            'entidad_codigo' => (string) ($usuario['username'] ?? ''),
            'descripcion' => 'Cambio de rol de usuario ' . (string) ($usuario['username'] ?? ''),
            'metadata' => [
                'rol_anterior' => (string) ($usuario['rol_nombre'] ?? ''),
                'rol_nuevo' => (string) ($rol['nombre'] ?? ''),
            ],
            'fecha_evento' => $fecha,
        ]);
    }

    $estadoAnteriorActivo = (int) ($usuario['activo'] ?? 0) === 1;
    if ($estadoAnteriorActivo !== $activo) {
        registrarActividadSistema($pdo, [
            'usuario_id' => $adminId,
            'usuario' => $adminUsername,
            'tipo_evento' => $activo ? 'usuario_activado' : 'usuario_desactivado',
            'entidad' => 'usuario',
            'entidad_id' => $usuarioId,
            'entidad_codigo' => (string) ($usuario['username'] ?? ''),
            'descripcion' => ($activo ? 'Activacion' : 'Desactivacion') . ' de usuario ' . (string) ($usuario['username'] ?? ''),
            'fecha_evento' => $fecha,
        ]);
    }
}

function existeUsernameUsuario(PDO $pdo, string $username): bool
{
    $stmt = $pdo->prepare('SELECT id FROM usuarios WHERE username = :username LIMIT 1');
    $stmt->execute([':username' => $username]);

    return $stmt->fetch() !== false;
}

function existeEmailUsuario(PDO $pdo, string $email): bool
{
    $stmt = $pdo->prepare('SELECT id FROM usuarios WHERE email = :email LIMIT 1');
    $stmt->execute([':email' => $email]);

    return $stmt->fetch() !== false;
}

function obtenerRolPorId(PDO $pdo, int $rolId): ?array
{
    if ($rolId <= 0) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT id, nombre FROM roles WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $rolId]);
    $rol = $stmt->fetch();

    return is_array($rol) ? $rol : null;
}

function configuracionEmailUsuarios(): array
{
    try {
        $config = cargarConfiguracion();
    } catch (Throwable $e) {
        return ['enabled' => false];
    }

    if (!is_array($config)) {
        return ['enabled' => false];
    }

    $enabled = (bool) ($config['user_request_email_enabled'] ?? false);
    $to = trim((string) ($config['user_request_email_to'] ?? USUARIO_CIRCUITO_SOLICITUDES_EMAIL));

    return [
        'enabled' => $enabled && $to !== '',
        'to' => $to,
        'from' => trim((string) ($config['user_request_email_from'] ?? '')),
    ];
}

function notificarSolicitudUsuarioPorEmail(array $usuario): array
{
    $config = configuracionEmailUsuarios();
    if (($config['enabled'] ?? false) !== true) {
        return [
            'enabled' => false,
            'sent' => false,
            'message' => 'Notificacion de alta no activada en este entorno.',
        ];
    }

    if (!function_exists('mail')) {
        return [
            'enabled' => true,
            'sent' => false,
            'message' => 'La funcion mail() no esta disponible en el entorno.',
        ];
    }

    $asunto = 'Nueva cuenta pendiente de aprobacion';
    $mensaje = implode("\n", [
        'Se ha creado una nueva cuenta pendiente de aprobacion.',
        'Usuario: ' . (string) ($usuario['username'] ?? ''),
        'Email: ' . (string) ($usuario['email'] ?? ''),
        'Rol solicitado: ' . etiquetaRolUsuario(normalizarRolAplicacion((string) ($usuario['rol_nombre'] ?? ''))),
        'Gestion de solicitudes: ' . rtrim(BASE_URL, '/') . '/usuarios.php?estado=pendiente',
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
            ? 'Aviso enviado al circuito de almacen.'
            : 'No se ha podido enviar el aviso por email.',
    ];
}
