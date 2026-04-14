<?php

declare(strict_types=1);

require_once __DIR__ . '/actividad.php';
require_once __DIR__ . '/notificaciones.php';

const USUARIO_ESTADO_PENDIENTE = 'pendiente';
const USUARIO_ESTADO_ACTIVO = 'activo';
const USUARIO_ESTADO_DESACTIVADO = 'desactivado';
const USUARIO_ESTADO_RECHAZADO = 'rechazado';
const USUARIO_CIRCUITO_SOLICITUDES_USERNAME = 'almacen';
const USUARIO_CIRCUITO_SOLICITUDES_EMAIL = 'almacen@maximosl.com';

function detalleColumnasUsuarios(PDO $pdo): array
{
    try {
        $stmt = $pdo->query('SHOW COLUMNS FROM usuarios');
        $columnas = $stmt->fetchAll();

        return is_array($columnas) ? $columnas : [];
    } catch (Throwable $e) {
        return [];
    }
}

function condicionesPendienteSql(PDO $pdo, string $alias = 'usuarios'): string
{
    $prefijo = $alias !== '' ? $alias . '.' : '';
    $condiciones = [$prefijo . 'aprobado = 0', $prefijo . 'activo = 0'];

    if (usuariosTieneColumna($pdo, 'rechazado')) {
        $condiciones[] = $prefijo . 'rechazado = 0';
    }

    return implode(' AND ', $condiciones);
}

function estadoInicialSolicitudUsuario(PDO $pdo): array
{
    $estado = [
        'activo' => 0,
        'aprobado' => 0,
    ];

    if (usuariosTieneColumna($pdo, 'rechazado')) {
        $estado['rechazado'] = 0;
    }

    return $estado;
}

function diagnosticoValidacionNuevoUsuario(PDO $pdo, array $datos): array
{
    $resultado = [
        'gestion_disponible' => usuariosSoportanGestion($pdo),
        'username' => ['ok' => true, 'mensajes' => []],
        'email' => ['ok' => true, 'mensajes' => []],
        'password' => ['ok' => true, 'mensajes' => []],
        'rol_id' => ['ok' => true, 'mensajes' => []],
        'errores' => [],
    ];

    if ($resultado['gestion_disponible'] !== true) {
        $resultado['errores'][] = 'La gestion avanzada de usuarios no esta disponible hasta aplicar la migracion de base de datos.';
        return $resultado;
    }

    $username = trim((string) ($datos['username'] ?? ''));
    $email = trim((string) ($datos['email'] ?? ''));
    $password = (string) ($datos['password'] ?? '');
    $passwordConfirmacion = (string) ($datos['password_confirmacion'] ?? '');
    $rolId = (int) ($datos['rol_id'] ?? 0);

    if ($username === '' || strlen($username) < 3) {
        $resultado['username']['ok'] = false;
        $resultado['username']['mensajes'][] = 'El nombre de usuario debe tener al menos 3 caracteres.';
    }

    if (!preg_match('/^[a-zA-Z0-9._-]+$/', $username)) {
        $resultado['username']['ok'] = false;
        $resultado['username']['mensajes'][] = 'El nombre de usuario solo puede contener letras, numeros, puntos, guiones y guion bajo.';
    }

    if ($username !== '' && existeUsernameUsuario($pdo, $username)) {
        $resultado['username']['ok'] = false;
        $resultado['username']['mensajes'][] = 'El nombre de usuario ya existe.';
    }

    if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        $resultado['email']['ok'] = false;
        $resultado['email']['mensajes'][] = 'Indica un email valido.';
    }

    if ($email !== '' && existeEmailUsuario($pdo, $email)) {
        $resultado['email']['ok'] = false;
        $resultado['email']['mensajes'][] = 'El email ya esta asociado a otra cuenta.';
    }

    if ($password === '' || strlen($password) < 8) {
        $resultado['password']['ok'] = false;
        $resultado['password']['mensajes'][] = 'La contraseña debe tener al menos 8 caracteres.';
    }

    if ($password !== $passwordConfirmacion) {
        $resultado['password']['ok'] = false;
        $resultado['password']['mensajes'][] = 'La contraseña y su confirmacion no coinciden.';
    }

    if (!rolIdAsignableUsuarios($pdo, $rolId)) {
        $resultado['rol_id']['ok'] = false;
        $resultado['rol_id']['mensajes'][] = 'Selecciona un rol inicial valido.';
    }

    foreach (['username', 'email', 'password', 'rol_id'] as $campo) {
        foreach ($resultado[$campo]['mensajes'] as $mensaje) {
            $resultado['errores'][] = $mensaje;
        }
    }

    return $resultado;
}

function diagnosticoSolicitudesUsuarios(PDO $pdo): array
{
    $pendientesSql = condicionesPendienteSql($pdo);
    $totalUsuarios = (int) $pdo->query('SELECT COUNT(*) FROM usuarios')->fetchColumn();
    $totalPendientes = (int) $pdo->query('SELECT COUNT(*) FROM usuarios WHERE ' . $pendientesSql)->fetchColumn();

    $selectUltimo = [
        'id',
        'username',
        usuariosTieneColumna($pdo, 'email') ? 'email' : 'NULL AS email',
        usuariosTieneColumna($pdo, 'activo') ? 'activo' : '1 AS activo',
        usuariosTieneColumna($pdo, 'aprobado') ? 'aprobado' : '1 AS aprobado',
        usuariosTieneColumna($pdo, 'rechazado') ? 'rechazado' : '0 AS rechazado',
        'rol_id',
    ];

    $stmt = $pdo->query('SELECT ' . implode(', ', $selectUltimo) . ' FROM usuarios ORDER BY id DESC LIMIT 1');
    $ultimaFila = $stmt->fetch();

    return [
        'total_usuarios' => $totalUsuarios,
        'total_pendientes' => $totalPendientes,
        'pendientes_sql' => $pendientesSql,
        'ultima_fila' => is_array($ultimaFila) ? $ultimaFila : null,
    ];
}

function usuarioEstaPendiente(array $usuario): bool
{
    if ((int) ($usuario['rechazado'] ?? 0) === 1) {
        return false;
    }

    return (int) ($usuario['aprobado'] ?? 0) === 0
        && (int) ($usuario['activo'] ?? 0) === 0;
}

function rolesFuncionalesUsuarios(): array
{
    return [
        ROL_ALMACEN => 'Almacen',
        ROL_EDELVIVES => 'Edelvives',
    ];
}

function columnasUsuariosDisponibles(PDO $pdo): array
{
    static $cache = null;

    if (is_array($cache)) {
        return $cache;
    }

    try {
        $stmt = $pdo->query('SHOW COLUMNS FROM usuarios');
        $columnas = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
        $cache = is_array($columnas) ? $columnas : [];
        return $cache;
    } catch (Throwable $e) {
        $cache = [];
        return $cache;
    }
}

function usuariosTieneColumna(PDO $pdo, string $columna): bool
{
    return in_array($columna, columnasUsuariosDisponibles($pdo), true);
}

function usuariosSoportanGestion(PDO $pdo): bool
{
    static $cache = null;

    if (is_bool($cache)) {
        return $cache;
    }

    $columnasNecesarias = ['email', 'activo', 'aprobado'];

    foreach ($columnasNecesarias as $columna) {
        if (!usuariosTieneColumna($pdo, $columna)) {
            $cache = false;
            return $cache;
        }
    }

    $cache = true;
    return $cache;
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

    $sql = 'SELECT COUNT(*) FROM usuarios WHERE ' . condicionesPendienteSql($pdo);
    error_log('[USUARIOS] contar pendientes SQL => ' . $sql);

    $stmt = $pdo->query($sql);
    $total = (int) $stmt->fetchColumn();
    error_log('[USUARIOS] pendientes detectados => ' . $total);

    return $total;
}

function listarUsuariosGestion(PDO $pdo, array $filtros = []): array
{
    if (!usuariosSoportanGestion($pdo)) {
        throw new RuntimeException('La gestion avanzada de usuarios no esta disponible hasta aplicar la migracion de base de datos.');
    }

    $select = [
        'u.id',
        'u.username',
        usuariosTieneColumna($pdo, 'email') ? 'u.email' : 'NULL AS email',
        'u.rol_id',
        'r.nombre AS rol_nombre',
        usuariosTieneColumna($pdo, 'activo') ? 'u.activo' : '1 AS activo',
        usuariosTieneColumna($pdo, 'aprobado') ? 'u.aprobado' : '1 AS aprobado',
        usuariosTieneColumna($pdo, 'rechazado') ? 'u.rechazado' : '0 AS rechazado',
        usuariosTieneColumna($pdo, 'aprobado_por_id') ? 'u.aprobado_por_id' : 'NULL AS aprobado_por_id',
        usuariosTieneColumna($pdo, 'aprobado_por_id') ? 'ua.username AS aprobado_por_username' : 'NULL AS aprobado_por_username',
        usuariosTieneColumna($pdo, 'fecha_aprobacion') ? 'u.fecha_aprobacion' : 'NULL AS fecha_aprobacion',
        usuariosTieneColumna($pdo, 'creado_en') ? 'u.creado_en' : 'NULL AS creado_en',
        usuariosTieneColumna($pdo, 'actualizado_en') ? 'u.actualizado_en' : 'NULL AS actualizado_en',
    ];

    $sql = 'SELECT ' . implode(', ', $select) . '
            FROM usuarios u
            LEFT JOIN roles r ON r.id = u.rol_id';
    if (usuariosTieneColumna($pdo, 'aprobado_por_id')) {
        $sql .= '
            LEFT JOIN usuarios ua ON ua.id = u.aprobado_por_id';
    }
    $sql .= '
            WHERE 1 = 1';
    $params = [];

    $estado = trim((string) ($filtros['estado'] ?? ''));
    if ($estado !== '') {
        switch ($estado) {
            case USUARIO_ESTADO_PENDIENTE:
                $sql .= ' AND ' . condicionesPendienteSql($pdo, 'u');
                break;
            case USUARIO_ESTADO_ACTIVO:
                $sql .= ' AND u.aprobado = 1 AND u.activo = 1';
                break;
            case USUARIO_ESTADO_DESACTIVADO:
                $sql .= ' AND u.aprobado = 1 AND u.activo = 0';
                break;
            case USUARIO_ESTADO_RECHAZADO:
                $sql .= usuariosTieneColumna($pdo, 'rechazado')
                    ? ' AND u.rechazado = 1'
                    : ' AND 1 = 0';
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

    $sql .= ' ORDER BY u.aprobado ASC';
    if (usuariosTieneColumna($pdo, 'creado_en')) {
        $sql .= ', u.creado_en DESC';
    }
    $sql .= ', u.id DESC';
    error_log('[USUARIOS] listar gestion SQL => ' . $sql . ' | filtros=' . json_encode($filtros, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $usuarios = $stmt->fetchAll();
    error_log('[USUARIOS] listar gestion total => ' . count($usuarios));

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
    if ((int) ($usuario['rechazado'] ?? 0) === 1) {
        return USUARIO_ESTADO_RECHAZADO;
    }

    if (usuarioEstaPendiente($usuario)) {
        return USUARIO_ESTADO_PENDIENTE;
    }

    return (int) ($usuario['activo'] ?? 0) === 1 && (int) ($usuario['aprobado'] ?? 0) === 1
        ? USUARIO_ESTADO_ACTIVO
        : USUARIO_ESTADO_DESACTIVADO;
}

function claseEstadoGestionUsuario(string $estado): string
{
    return match ($estado) {
        USUARIO_ESTADO_ACTIVO => 'text-bg-success',
        USUARIO_ESTADO_DESACTIVADO => 'text-bg-secondary',
        USUARIO_ESTADO_RECHAZADO => 'text-bg-danger',
        default => 'text-bg-warning',
    };
}

function etiquetaEstadoGestionUsuario(string $estado): string
{
    return match ($estado) {
        USUARIO_ESTADO_ACTIVO => 'Activo',
        USUARIO_ESTADO_DESACTIVADO => 'Desactivado',
        USUARIO_ESTADO_RECHAZADO => 'Rechazado',
        default => 'Pendiente',
    };
}

function validarDatosNuevoUsuario(PDO $pdo, array $datos): array
{
    $diagnostico = diagnosticoValidacionNuevoUsuario($pdo, $datos);
    $errores = $diagnostico['errores'];

    if ($errores !== []) {
        error_log('[USUARIOS] validacion alta bloqueada => ' . json_encode([
            'username' => trim((string) ($datos['username'] ?? '')),
            'email' => trim((string) ($datos['email'] ?? '')),
            'rol_id' => (int) ($datos['rol_id'] ?? 0),
            'errores' => $errores,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    return $errores;

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
        $errores[] = 'La contraseña debe tener al menos 8 caracteres.';
    }

    if ($password !== $passwordConfirmacion) {
        $errores[] = 'La contraseña y su confirmación no coinciden.';
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

    if ($errores !== []) {
        error_log('[USUARIOS] validacion alta bloqueada => ' . json_encode([
            'username' => $username,
            'email' => $email,
            'rol_id' => $rolId,
            'errores' => $errores,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
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
    $estadoInicial = estadoInicialSolicitudUsuario($pdo);

    error_log('[USUARIOS] alta solicitud recibida => ' . json_encode([
        'username' => $username,
        'email' => $email,
        'rol_id' => $rolId,
        'estado_inicial' => $estadoInicial,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

    $columnasInsert = ['username', 'email', 'password', 'rol_id'];
    $valoresInsert = [':username', ':email', ':password', ':rol_id'];
    $paramsInsert = [
        ':username' => $username,
        ':email' => $email,
        ':password' => $passwordHash,
        ':rol_id' => $rolId,
    ];

    if (usuariosTieneColumna($pdo, 'activo')) {
        $columnasInsert[] = 'activo';
        $valoresInsert[] = ':activo';
        $paramsInsert[':activo'] = $estadoInicial['activo'];
    }
    if (usuariosTieneColumna($pdo, 'aprobado')) {
        $columnasInsert[] = 'aprobado';
        $valoresInsert[] = ':aprobado';
        $paramsInsert[':aprobado'] = $estadoInicial['aprobado'];
    }
    if (usuariosTieneColumna($pdo, 'rechazado')) {
        $columnasInsert[] = 'rechazado';
        $valoresInsert[] = ':rechazado';
        $paramsInsert[':rechazado'] = $estadoInicial['rechazado'];
    }
    if (usuariosTieneColumna($pdo, 'creado_en')) {
        $columnasInsert[] = 'creado_en';
        $valoresInsert[] = ':creado_en';
        $paramsInsert[':creado_en'] = $fecha->format('Y-m-d H:i:s');
    }
    if (usuariosTieneColumna($pdo, 'actualizado_en')) {
        $columnasInsert[] = 'actualizado_en';
        $valoresInsert[] = ':actualizado_en';
        $paramsInsert[':actualizado_en'] = $fecha->format('Y-m-d H:i:s');
    }

    try {
        $stmt = $pdo->prepare(
            'INSERT INTO usuarios (' . implode(', ', $columnasInsert) . ')
             VALUES (' . implode(', ', $valoresInsert) . ')'
        );
        $stmt->execute($paramsInsert);
    } catch (Throwable $e) {
        error_log('[USUARIOS] error insertando solicitud => ' . $e->getMessage());
        throw $e;
    }

    $usuarioId = (int) $pdo->lastInsertId();
    $rol = obtenerRolPorId($pdo, $rolId);
    error_log('[USUARIOS] solicitud insertada => usuario_id=' . $usuarioId . ' username=' . $username);

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

    try {
        crearNotificacion($pdo, USUARIO_CIRCUITO_SOLICITUDES_USERNAME, NOTIFICACION_TIPO_ALTA_USUARIO, 'Nueva solicitud de usuario: ' . $username);
    } catch (Throwable $e) {
        error_log('[USUARIOS] No se pudo crear notificacion de usuario nuevo para almacen: ' . $e->getMessage());
    }

    $usuarioCreado = obtenerUsuarioGestionPorId($pdo, $usuarioId) ?? [];
    error_log('[USUARIOS] estado persistido solicitud => ' . json_encode([
        'id' => $usuarioCreado['id'] ?? $usuarioId,
        'username' => $usuarioCreado['username'] ?? $username,
        'activo' => $usuarioCreado['activo'] ?? null,
        'aprobado' => $usuarioCreado['aprobado'] ?? null,
        'rechazado' => $usuarioCreado['rechazado'] ?? null,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

    return $usuarioCreado;
}

function obtenerUsuarioGestionPorId(PDO $pdo, int $usuarioId): ?array
{
    if ($usuarioId <= 0) {
        return null;
    }

    $select = [
        'u.id',
        'u.username',
        usuariosTieneColumna($pdo, 'email') ? 'u.email' : 'NULL AS email',
        'u.rol_id',
        'r.nombre AS rol_nombre',
        usuariosTieneColumna($pdo, 'activo') ? 'u.activo' : '1 AS activo',
        usuariosTieneColumna($pdo, 'aprobado') ? 'u.aprobado' : '1 AS aprobado',
        usuariosTieneColumna($pdo, 'rechazado') ? 'u.rechazado' : '0 AS rechazado',
        usuariosTieneColumna($pdo, 'aprobado_por_id') ? 'u.aprobado_por_id' : 'NULL AS aprobado_por_id',
        usuariosTieneColumna($pdo, 'aprobado_por_id') ? 'ua.username AS aprobado_por_username' : 'NULL AS aprobado_por_username',
        usuariosTieneColumna($pdo, 'fecha_aprobacion') ? 'u.fecha_aprobacion' : 'NULL AS fecha_aprobacion',
        usuariosTieneColumna($pdo, 'creado_en') ? 'u.creado_en' : 'NULL AS creado_en',
        usuariosTieneColumna($pdo, 'actualizado_en') ? 'u.actualizado_en' : 'NULL AS actualizado_en',
    ];
    $sql = 'SELECT ' . implode(', ', $select) . '
         FROM usuarios u
         LEFT JOIN roles r ON r.id = u.rol_id';
    if (usuariosTieneColumna($pdo, 'aprobado_por_id')) {
        $sql .= '
         LEFT JOIN usuarios ua ON ua.id = u.aprobado_por_id';
    }
    $sql .= '
         WHERE u.id = :id
         LIMIT 1';

    $stmt = $pdo->prepare($sql);
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

    $sets = ['rol_id = :rol_id'];
    $params = [':rol_id' => $rolId, ':id' => $usuarioId];
    if (usuariosTieneColumna($pdo, 'aprobado')) {
        $sets[] = 'aprobado = 1';
    }
    if (usuariosTieneColumna($pdo, 'rechazado')) {
        $sets[] = 'rechazado = 0';
    }
    if (usuariosTieneColumna($pdo, 'activo')) {
        $sets[] = 'activo = 1';
    }
    if (usuariosTieneColumna($pdo, 'aprobado_por_id')) {
        $sets[] = 'aprobado_por_id = :aprobado_por_id';
        $params[':aprobado_por_id'] = $adminId;
    }
    if (usuariosTieneColumna($pdo, 'fecha_aprobacion')) {
        $sets[] = 'fecha_aprobacion = :fecha_aprobacion';
        $params[':fecha_aprobacion'] = $fecha->format('Y-m-d H:i:s');
    }
    if (usuariosTieneColumna($pdo, 'actualizado_en')) {
        $sets[] = 'actualizado_en = :actualizado_en';
        $params[':actualizado_en'] = $fecha->format('Y-m-d H:i:s');
    }

    $stmt = $pdo->prepare('UPDATE usuarios SET ' . implode(', ', $sets) . ' WHERE id = :id');
    $stmt->execute($params);

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

    $sets = ['rol_id = :rol_id'];
    $params = [
        ':rol_id' => $rolId,
        ':id' => $usuarioId,
    ];
    if (usuariosTieneColumna($pdo, 'activo')) {
        $sets[] = 'activo = :activo';
        $params[':activo'] = $activo ? 1 : 0;
    }
    if (usuariosTieneColumna($pdo, 'actualizado_en')) {
        $sets[] = 'actualizado_en = :actualizado_en';
        $params[':actualizado_en'] = $fecha->format('Y-m-d H:i:s');
    }

    $stmt = $pdo->prepare('UPDATE usuarios SET ' . implode(', ', $sets) . ' WHERE id = :id');
    $stmt->execute($params);

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

function rechazarUsuario(PDO $pdo, int $usuarioId, array $admin): void
{
    if (!usuariosSoportanGestion($pdo)) {
        throw new RuntimeException('La gestion avanzada de usuarios no esta disponible hasta aplicar la migracion de base de datos.');
    }

    $usuario = obtenerUsuarioGestionPorId($pdo, $usuarioId);
    if ($usuario === null) {
        throw new RuntimeException('El usuario indicado no existe.');
    }

    if ((int) ($usuario['aprobado'] ?? 0) === 1) {
        throw new RuntimeException('Solo se pueden rechazar solicitudes pendientes.');
    }

    $fecha = new DateTimeImmutable('now', new DateTimeZone('Europe/Madrid'));
    $adminId = isset($admin['user_id']) ? (int) $admin['user_id'] : null;
    $adminUsername = trim((string) ($admin['username'] ?? ''));

    $sets = [];
    $params = [':id' => $usuarioId];
    if (usuariosTieneColumna($pdo, 'rechazado')) {
        $sets[] = 'rechazado = 1';
    }
    if (usuariosTieneColumna($pdo, 'activo')) {
        $sets[] = 'activo = 0';
    }
    if (usuariosTieneColumna($pdo, 'aprobado')) {
        $sets[] = 'aprobado = 0';
    }
    if (usuariosTieneColumna($pdo, 'actualizado_en')) {
        $sets[] = 'actualizado_en = :actualizado_en';
        $params[':actualizado_en'] = $fecha->format('Y-m-d H:i:s');
    }

    $stmt = $pdo->prepare('UPDATE usuarios SET ' . implode(', ', $sets) . ' WHERE id = :id');
    $stmt->execute($params);

    registrarActividadSistema($pdo, [
        'usuario_id' => $adminId,
        'usuario' => $adminUsername,
        'tipo_evento' => 'usuario_rechazado',
        'entidad' => 'usuario',
        'entidad_id' => $usuarioId,
        'entidad_codigo' => (string) ($usuario['username'] ?? ''),
        'descripcion' => 'Rechazo de usuario ' . (string) ($usuario['username'] ?? ''),
        'fecha_evento' => $fecha,
    ]);
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
