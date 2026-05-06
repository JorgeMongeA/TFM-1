<?php

/**
 * Autor: Jorge Monge
 * Trabajo Final de Máster (TFM)
 * UOC - 2026
 */

declare(strict_types=1);

const CENTRO_DESCONOCIDO_CODIGO = '000000';
const CENTRO_DESCONOCIDO_NOMBRE = 'DESCONOCIDO';
const CENTROS_GOOGLE_SHEETS_WEBAPP_URL = 'https://script.google.com/macros/s/AKfycbzGdKiAIX3CcsJnSRRcqLwmCqK5T7-7AYXnegfnByEwMfLFFs0Ane0U4044L81LzHO9iw/exec';

function columnasCentrosTabla(bool $conAcciones = false): array
{
    $columnas = [
        'codigo_centro' => 'Codigo centro',
        'nombre_centro' => 'Nombre centro',
        'ciudad' => 'Localidad',
        'codigo_congregacion' => 'Codigo congregacion',
        'congregacion' => 'Congregacion',
        'entrada' => 'Entrada',
        'almacen' => 'Almacen',
        'destino' => 'Destino',
        'actualizado_en' => 'Actualizado en',
    ];

    if ($conAcciones) {
        $columnas['acciones'] = 'Acciones';
    }

    return $columnas;
}

function filtrosCentrosPermitidos(): array
{
    return ['codigo_centro', 'nombre_centro', 'ciudad', 'congregacion', 'destino'];
}

function leerFiltrosCentrosDesdeRequest(array $source): array
{
    $filtros = [];

    foreach (filtrosCentrosPermitidos() as $campo) {
        $filtros[$campo] = trim((string) ($source[$campo] ?? ''));
    }

    return $filtros;
}

function cargarCentros(PDO $pdo, array $filtros = []): array
{
    $sql = 'SELECT codigo_centro, nombre_centro, ciudad, codigo_congregacion, congregacion, entrada, almacen, destino, actualizado_en FROM centros';
    $where = [];
    $params = [];

    foreach ($filtros as $campo => $valor) {
        if ($valor === '') {
            continue;
        }

        $where[] = $campo . ' LIKE :' . $campo;
        $params[':' . $campo] = '%' . $valor . '%';
    }

    if ($where !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }

    $sql .= ' ORDER BY codigo_centro ASC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function cargarCentrosParaSelector(PDO $pdo): array
{
    asegurarCentroDesconocido($pdo);

    $stmt = $pdo->query('SELECT codigo_centro, nombre_centro, ciudad, destino FROM centros ORDER BY nombre_centro ASC, codigo_centro ASC');
    return $stmt->fetchAll();
}

function asegurarCentroDesconocido(PDO $pdo): void
{
    $stmt = $pdo->prepare('SELECT codigo_centro FROM centros WHERE codigo_centro = :codigo_centro LIMIT 1');
    $stmt->execute([':codigo_centro' => CENTRO_DESCONOCIDO_CODIGO]);

    if ($stmt->fetch() !== false) {
        $update = $pdo->prepare(
            'UPDATE centros
             SET nombre_centro = :nombre_centro,
                 ciudad = NULL,
                 codigo_congregacion = NULL,
                 congregacion = NULL,
                 entrada = NULL,
                 almacen = NULL,
                 destino = NULL,
                 tipo = NULL,
                 codigo_grupo = NULL,
                 actualizado_en = CURRENT_TIMESTAMP
             WHERE codigo_centro = :codigo_centro'
        );
        $update->execute([
            ':codigo_centro' => CENTRO_DESCONOCIDO_CODIGO,
            ':nombre_centro' => CENTRO_DESCONOCIDO_NOMBRE,
        ]);
        return;
    }

    $insert = $pdo->prepare(
        'INSERT INTO centros (
            codigo_centro, nombre_centro, ciudad, codigo_congregacion, congregacion, entrada, almacen, destino, tipo, codigo_grupo
         ) VALUES (
            :codigo_centro, :nombre_centro, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL
         )'
    );
    $insert->execute([
        ':codigo_centro' => CENTRO_DESCONOCIDO_CODIGO,
        ':nombre_centro' => CENTRO_DESCONOCIDO_NOMBRE,
    ]);
}

function buscarCentroPorCodigo(PDO $pdo, string $codigoCentro): ?array
{
    $stmt = $pdo->prepare('SELECT codigo_centro, nombre_centro, ciudad, codigo_congregacion, congregacion, entrada, almacen, destino, tipo, codigo_grupo FROM centros WHERE codigo_centro = :codigo_centro LIMIT 1');
    $stmt->execute([':codigo_centro' => $codigoCentro]);
    $centro = $stmt->fetch();

    return $centro !== false ? $centro : null;
}

function guardarCentro(PDO $pdo, array $datos, ?string $codigoOriginal = null): void
{
    $parametros = prepararParametrosCentroManual($datos);

    if ($codigoOriginal === null) {
        $stmt = $pdo->prepare(
            'INSERT INTO centros (codigo_centro, nombre_centro, ciudad, codigo_congregacion, congregacion, entrada, almacen, destino, tipo, codigo_grupo)
             VALUES (:codigo_centro, :nombre_centro, :ciudad, :codigo_congregacion, :congregacion, :entrada, :almacen, :destino, :tipo, :codigo_grupo)'
        );
        $stmt->execute($parametros);

        return;
    }

    $stmt = $pdo->prepare(
        'UPDATE centros
         SET codigo_centro = :codigo_centro,
             nombre_centro = :nombre_centro,
             ciudad = :ciudad,
             codigo_congregacion = :codigo_congregacion,
             congregacion = :congregacion,
             entrada = :entrada,
             almacen = :almacen,
             destino = :destino,
             tipo = :tipo,
             codigo_grupo = :codigo_grupo,
             actualizado_en = CURRENT_TIMESTAMP
         WHERE codigo_centro = :codigo_original'
    );
    $parametros[':codigo_original'] = $codigoOriginal;
    $stmt->execute($parametros);
}

function prepararParametrosCentroManual(array $datos): array
{
    $almacen = limpiarCampoCsv($datos['almacen'] ?? '');
    $destino = normalizarDestinoCentro($datos['destino'] ?? '');

    if ($destino === '') {
        $destino = calcularDestinoCentroDesdeAlmacen($almacen);
    }

    return [
        ':codigo_centro' => normalizarCodigoCentro($datos['codigo_centro'] ?? ''),
        ':nombre_centro' => valorCentroONull($datos['nombre_centro'] ?? null),
        ':ciudad' => valorCentroONull($datos['ciudad'] ?? null),
        ':codigo_congregacion' => valorCentroONull($datos['codigo_congregacion'] ?? null),
        ':congregacion' => valorCentroONull($datos['congregacion'] ?? null),
        ':entrada' => valorCentroONull($datos['entrada'] ?? null),
        ':almacen' => valorCentroONull($almacen),
        ':destino' => valorCentroONull($destino),
        ':tipo' => valorCentroONull($datos['tipo'] ?? null),
        ':codigo_grupo' => valorCentroONull($datos['codigo_grupo'] ?? null),
    ];
}

function eliminarCentro(PDO $pdo, string $codigoCentro): void
{
    $stmt = $pdo->prepare('DELETE FROM centros WHERE codigo_centro = :codigo_centro');
    $stmt->execute([':codigo_centro' => $codigoCentro]);
}

function obtenerTokenSyncCentrosGoogleSheets(): string
{
    return 'congregaciones_sync_2026';
}

function obtenerUrlSyncCentrosGoogleSheets(array $config): string
{
    $urlCentros = trim((string) ($config['centros_google_sync_url'] ?? ''));
    if ($urlCentros !== '') {
        return $urlCentros;
    }

    return CENTROS_GOOGLE_SHEETS_WEBAPP_URL;
}

function sincronizarCentrosDesdeAppsScript(PDO $pdo, string $scriptUrl, string $token): array
{
    $respuesta = llamarAppsScriptCentros($scriptUrl, $token, 'get_centros_nuevo_origen');
    $filas = extraerFilasCentrosDesdeAppsScript($respuesta);

    return reconstruirCentrosDesdeFilas($pdo, $filas);
}

function reconstruirCentrosDesdeFilas(PDO $pdo, array $filas): array
{
    $resumen = [
        'total_leidos' => 0,
        'insertados' => 0,
        'actualizados' => 0,
        'eliminados' => 0,
        'ignorados' => 0,
        'errores' => [],
    ];

    $filasPorCodigo = [];

    foreach ($filas as $indice => $fila) {
        $resumen['total_leidos']++;
        $datosFila = normalizarFilaCentroNuevoOrigen(is_array($fila) ? $fila : []);

        if (implode('', $datosFila) === '') {
            $resumen['ignorados']++;
            continue;
        }

        if ($datosFila['codigo_centro'] === '') {
            $resumen['ignorados']++;
            $resumen['errores'][] = 'Fila ' . ($indice + 2) . ': centro sin codigo, se ignora.';
            continue;
        }

        if ($datosFila['codigo_centro'] === CENTRO_DESCONOCIDO_CODIGO) {
            $resumen['ignorados']++;
            continue;
        }

        if (isset($filasPorCodigo[$datosFila['codigo_centro']])) {
            $resumen['ignorados']++;
            $resumen['errores'][] = 'Fila ' . ($indice + 2) . ': codigo de centro duplicado en origen (' . $datosFila['codigo_centro'] . '), se ignora duplicado.';
            continue;
        }

        $filasPorCodigo[$datosFila['codigo_centro']] = $datosFila;
    }

    $upsert = $pdo->prepare(
        'INSERT INTO centros (
            codigo_centro, nombre_centro, ciudad, codigo_congregacion, congregacion, entrada, almacen, destino, tipo, codigo_grupo
         ) VALUES (
            :codigo_centro, :nombre_centro, :ciudad, :codigo_congregacion, :congregacion, :entrada, :almacen, :destino, NULL, NULL
         )
         ON DUPLICATE KEY UPDATE
            nombre_centro = VALUES(nombre_centro),
            ciudad = VALUES(ciudad),
            codigo_congregacion = VALUES(codigo_congregacion),
            congregacion = VALUES(congregacion),
            entrada = VALUES(entrada),
            almacen = VALUES(almacen),
            destino = VALUES(destino),
            tipo = NULL,
            codigo_grupo = NULL,
            actualizado_en = CURRENT_TIMESTAMP'
    );

    try {
        $pdo->beginTransaction();
        asegurarCentroDesconocido($pdo);

        $existentes = [];
        $stmtExistentes = $pdo->query('SELECT codigo_centro FROM centros');
        foreach ($stmtExistentes->fetchAll(PDO::FETCH_COLUMN) as $codigoExistente) {
            $existentes[(string) $codigoExistente] = true;
        }

        foreach ($filasPorCodigo as $codigoCentro => $datosFila) {
            try {
                $upsert->execute(prepararParametrosCentroNuevoOrigen($datosFila));

                if (isset($existentes[$codigoCentro])) {
                    $resumen['actualizados']++;
                } else {
                    $resumen['insertados']++;
                }
            } catch (Throwable $e) {
                $mensaje = trim($e->getMessage());
                $resumen['errores'][] = 'Centro ' . $codigoCentro . ': ' . ($mensaje !== '' ? $mensaje : 'Error al guardar el registro.');
            }
        }

        $resumen['eliminados'] = eliminarCentrosNoPresentesEnOrigen($pdo, array_keys($filasPorCodigo));

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }

    return $resumen;
}

function eliminarCentrosNoPresentesEnOrigen(PDO $pdo, array $codigosOrigen): int
{
    $codigosOrigen = array_values(array_unique(array_filter(
        array_map('normalizarCodigoCentro', $codigosOrigen),
        static fn(string $codigo): bool => $codigo !== '' && $codigo !== CENTRO_DESCONOCIDO_CODIGO
    )));

    $params = [
        ':codigo_desconocido' => CENTRO_DESCONOCIDO_CODIGO,
    ];

    $sql = 'DELETE FROM centros WHERE codigo_centro <> :codigo_desconocido';

    if ($codigosOrigen !== []) {
        $placeholders = [];
        foreach ($codigosOrigen as $indice => $codigoCentro) {
            $placeholder = ':codigo_origen_' . $indice;
            $placeholders[] = $placeholder;
            $params[$placeholder] = $codigoCentro;
        }

        $sql .= ' AND codigo_centro NOT IN (' . implode(', ', $placeholders) . ')';
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->rowCount();
}

function llamarAppsScriptCentros(string $url, string $token, string $accion, int $timeoutSegundos = 30): array
{
    if (trim($url) === '' || trim($token) === '') {
        throw new RuntimeException('Falta URL o token para sincronizar centros.');
    }

    $body = json_encode([
        'token' => $token,
        'action' => $accion,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if ($body === false) {
        throw new RuntimeException('No se pudo generar el JSON de sincronizacion de centros.');
    }

    $contexto = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\nAccept: application/json\r\n",
            'content' => $body,
            'timeout' => max(1, $timeoutSegundos),
            'ignore_errors' => true,
        ],
    ]);

    $respuesta = @file_get_contents($url, false, $contexto);
    $error = '';

    if ($respuesta === false && function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => min(10, max(1, $timeoutSegundos)),
            CURLOPT_TIMEOUT => max(1, $timeoutSegundos),
        ]);

        $respuesta = curl_exec($ch);
        if ($respuesta === false) {
            $error = (string) curl_error($ch);
        }
        curl_close($ch);
    }

    if ($respuesta === false) {
        throw new RuntimeException('No se pudo contactar con Apps Script para centros' . ($error !== '' ? ': ' . $error : '.'));
    }

    $datos = json_decode(trim((string) $respuesta), true);
    if (!is_array($datos)) {
        throw new RuntimeException('Apps Script no devolvio JSON valido para centros.');
    }

    if (($datos['success'] ?? true) === false) {
        $mensaje = trim((string) ($datos['message'] ?? $datos['error'] ?? ''));
        throw new RuntimeException($mensaje !== '' ? $mensaje : 'Apps Script rechazo la sincronizacion de centros.');
    }

    return $datos;
}

function extraerFilasCentrosDesdeAppsScript(array $respuesta): array
{
    $filas = $respuesta['payload'] ?? $respuesta['rows'] ?? [];

    if (!is_array($filas)) {
        throw new RuntimeException('Apps Script no devolvio filas de centros validas.');
    }

    return $filas;
}

function normalizarFilaCentroNuevoOrigen(array $fila): array
{
    $almacen = limpiarCampoCsv($fila['almacen'] ?? '');
    $destino = normalizarDestinoCentro($fila['destino'] ?? '');

    if ($destino === '') {
        $destino = calcularDestinoCentroDesdeAlmacen($almacen);
    }

    return [
        'codigo_centro' => normalizarCodigoCentro($fila['codigo_centro'] ?? ''),
        'nombre_centro' => limpiarCampoCsv($fila['nombre_centro'] ?? ''),
        'ciudad' => limpiarCampoCsv($fila['localidad'] ?? $fila['ciudad'] ?? ''),
        'codigo_congregacion' => normalizarCodigoCentro($fila['codigo_congregacion'] ?? ''),
        'congregacion' => limpiarCampoCsv($fila['congregacion'] ?? ''),
        'entrada' => normalizarEntradaCentro($fila['entrada'] ?? ''),
        'almacen' => $almacen,
        'destino' => $destino,
    ];
}

function prepararParametrosCentroNuevoOrigen(array $datosFila): array
{
    return [
        ':codigo_centro' => $datosFila['codigo_centro'],
        ':nombre_centro' => valorCentroONull($datosFila['nombre_centro'] ?? null),
        ':ciudad' => valorCentroONull($datosFila['ciudad'] ?? null),
        ':codigo_congregacion' => valorCentroONull($datosFila['codigo_congregacion'] ?? null),
        ':congregacion' => valorCentroONull($datosFila['congregacion'] ?? null),
        ':entrada' => valorCentroONull($datosFila['entrada'] ?? null),
        ':almacen' => valorCentroONull($datosFila['almacen'] ?? null),
        ':destino' => valorCentroONull($datosFila['destino'] ?? null),
    ];
}

function sincronizarCentrosDesdeCsv(PDO $pdo, string $csvUrl): array
{
    if (trim($csvUrl) === '') {
        throw new RuntimeException('URL CSV no definida.');
    }

    $contenido = descargarCsvDesdeUrl($csvUrl);
    $lineas = explode("\n", $contenido);

    $resumen = [
        'total_leidos' => 0,
        'insertados' => 0,
        'actualizados' => 0,
        'ignorados' => 0,
        'errores' => [],
    ];

    $select = $pdo->prepare('SELECT codigo_centro FROM centros WHERE codigo_centro = :codigo_centro');
    $insert = $pdo->prepare(
        'INSERT INTO centros (codigo_centro, nombre_centro, ciudad, tipo, codigo_grupo)
         VALUES (:codigo_centro, :nombre_centro, :ciudad, :tipo, :codigo_grupo)'
    );
    $update = $pdo->prepare(
        'UPDATE centros
         SET nombre_centro = :nombre_centro,
             ciudad = :ciudad,
             tipo = :tipo,
             codigo_grupo = :codigo_grupo,
             actualizado_en = CURRENT_TIMESTAMP
         WHERE codigo_centro = :codigo_centro'
    );

    try {
        $pdo->beginTransaction();

        foreach ($lineas as $indice => $linea) {
            if ($indice === 0) {
                continue;
            }

            $fila = str_getcsv($linea);
            procesarFilaCentro($select, $insert, $update, $resumen, $fila, $indice + 1);
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }

    return $resumen;
}

function descargarCsvCentros(string $csvUrl): string
{
    return descargarCsvDesdeUrl($csvUrl);
}

function descargarCsvDesdeUrl(string $csvUrl): string
{
    $contenido = @file_get_contents($csvUrl);
    if ($contenido !== false) {
        return $contenido;
    }

    if (function_exists('curl_init')) {
        $ch = curl_init($csvUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 20,
        ]);

        $respuesta = curl_exec($ch);
        curl_close($ch);

        if ($respuesta !== false) {
            return (string) $respuesta;
        }
    }

    throw new RuntimeException('No se pudo descargar el CSV.');
}

function procesarFilaCentro(PDOStatement $select, PDOStatement $insert, PDOStatement $update, array &$resumen, array $fila, int $numeroFila): void
{
    $resumen['total_leidos']++;
    $datosFila = extraerFilaCentros($fila);

    if (implode('', $datosFila) === '') {
        $resumen['ignorados']++;
        return;
    }

    if ($datosFila['codigo_centro'] === '') {
        $resumen['ignorados']++;
        return;
    }

    try {
        $parametros = [
            ':codigo_centro' => $datosFila['codigo_centro'],
            ':nombre_centro' => $datosFila['nombre_centro'] !== '' ? $datosFila['nombre_centro'] : null,
            ':ciudad' => $datosFila['ciudad'] !== '' ? $datosFila['ciudad'] : null,
            ':tipo' => $datosFila['tipo'] !== '' ? $datosFila['tipo'] : null,
            ':codigo_grupo' => $datosFila['codigo_grupo'] !== '' ? $datosFila['codigo_grupo'] : null,
        ];

        $select->execute([':codigo_centro' => $datosFila['codigo_centro']]);

        if ($select->fetch() === false) {
            $insert->execute($parametros);
            $resumen['insertados']++;
            return;
        }

        $update->execute($parametros);
        $resumen['actualizados']++;
    } catch (Throwable $e) {
        $mensaje = trim($e->getMessage());
        $resumen['errores'][] = 'Fila ' . $numeroFila . ': ' . ($mensaje !== '' ? $mensaje : 'Error al guardar el registro.');
    }
}

function extraerFilaCentros(array $fila): array
{
    $fila = array_pad($fila, 5, null);

    return [
        'codigo_centro' => limpiarCampoCsv($fila[0]),
        'nombre_centro' => limpiarCampoCsv($fila[1]),
        'ciudad' => limpiarCampoCsv($fila[2]),
        'tipo' => limpiarCampoCsv($fila[3]),
        'codigo_grupo' => limpiarCampoCsv($fila[4]),
    ];
}

function limpiarCampoCsv(mixed $valor): string
{
    if ($valor === null) {
        return '';
    }

    return trim((string) $valor);
}

function valorCentroONull(mixed $valor): ?string
{
    $texto = limpiarCampoCsv($valor);

    return $texto !== '' ? $texto : null;
}

function normalizarCodigoCentro(mixed $valor): string
{
    return preg_replace('/\s+/', '', limpiarCampoCsv($valor)) ?? '';
}

function normalizarEntradaCentro(mixed $valor): string
{
    $texto = limpiarCampoCsv($valor);

    if (preg_match('/^(\d{4})(?:[.,]0+)?$/', $texto, $matches) === 1) {
        return $matches[1];
    }

    return $texto;
}

function calcularDestinoCentroDesdeAlmacen(mixed $almacen): string
{
    $codigoAlmacen = normalizarCodigoAlmacenCentro($almacen);

    return match ($codigoAlmacen) {
        '1901' => 'EDV',
        '1905' => 'EPL',
        default => '',
    };
}

function normalizarDestinoCentro(mixed $destino): string
{
    $texto = strtoupper(limpiarCampoCsv($destino));

    return in_array($texto, ['EDV', 'EPL'], true) ? $texto : '';
}

function normalizarCodigoAlmacenCentro(mixed $almacen): string
{
    $texto = preg_replace('/\s+/', '', limpiarCampoCsv($almacen)) ?? '';

    if (preg_match('/^(\d+)(?:[.,]0+)?$/', $texto, $matches) === 1) {
        return $matches[1];
    }

    if (preg_match('/(1901|1905)/', $texto, $matches) === 1) {
        return $matches[1];
    }

    return preg_replace('/\D+/', '', $texto) ?? '';
}

function limpiarCampoCsvONull(mixed $valor): ?string
{
    $texto = limpiarCampoCsv($valor);

    return $texto !== '' ? $texto : null;
}
