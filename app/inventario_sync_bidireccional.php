<?php

declare(strict_types=1);

function obtenerInventarioSql(PDO $pdo): array
{
    $stmt = $pdo->query(
        'SELECT id, ubicacion, destino, editorial, fecha_entrada, codigo_centro, colegio, bultos, `orden`, fecha_salida, indicador_completa
         FROM inventario
         ORDER BY id ASC'
    );

    $filas = $stmt->fetchAll();

    return array_map('normalizarFilaInventarioBidireccional', $filas);
}

function llamarAppsScriptInventario(string $url, string $token, string $accion, array $payload = []): array
{
    if (trim($url) === '' || trim($token) === '') {
        throw new RuntimeException('Falta la configuración de URL o token para la sincronización bidireccional.');
    }

    $body = json_encode([
        'token' => $token,
        'action' => $accion,
        'payload' => $payload,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if ($body === false) {
        throw new RuntimeException('No se pudo generar el JSON para Apps Script.');
    }

    $respuesta = false;
    $error = '';

    $contexto = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\nAccept: application/json\r\n",
            'content' => $body,
            'timeout' => 30,
            'ignore_errors' => true,
        ],
    ]);

    $respuesta = @file_get_contents($url, false, $contexto);

    if ($respuesta === false && function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30,
        ]);

        $respuesta = curl_exec($ch);
        if ($respuesta === false) {
            $error = (string) curl_error($ch);
        }
        curl_close($ch);
    }

    if ($respuesta === false) {
        throw new RuntimeException($error !== '' ? $error : 'No se pudo contactar con Apps Script.');
    }

    $textoRespuesta = trim((string) $respuesta);
    $datos = json_decode($textoRespuesta, true);
    if (!is_array($datos)) {
        $fragmento = mb_substr($textoRespuesta, 0, 300);
        throw new RuntimeException(
            'Apps Script devolvió una respuesta JSON inválida.'
            . ($fragmento !== '' ? ' Respuesta recibida: ' . $fragmento : '')
        );
    }

    return $datos;
}

function compararInventarioPorId(array $sqlRows, array $sheetRows): array
{
    $sqlErrores = [];
    $sheetErrores = [];
    $sqlIndexado = indexarInventarioPorId($sqlRows, $sqlErrores);
    $sheetIndexado = indexarInventarioPorId($sheetRows, $sheetErrores);

    $paraInsertarEnSheet = [];
    foreach ($sqlIndexado as $id => $fila) {
        if (!array_key_exists($id, $sheetIndexado)) {
            $paraInsertarEnSheet[] = $fila;
        }
    }

    $paraInsertarEnSql = [];
    foreach ($sheetIndexado as $id => $fila) {
        if (!array_key_exists($id, $sqlIndexado)) {
            $paraInsertarEnSql[] = $fila;
        }
    }

    return [
        'para_insertar_en_sheet' => array_values($paraInsertarEnSheet),
        'para_insertar_en_sql' => array_values($paraInsertarEnSql),
        'coincidencias' => count(array_intersect(array_keys($sqlIndexado), array_keys($sheetIndexado))),
        'total_sql' => count($sqlIndexado),
        'total_sheet' => count($sheetIndexado),
        'ids_sql' => array_keys($sqlIndexado),
        'ids_sheet' => array_keys($sheetIndexado),
        'ids_duplicados_sql' => $sqlErrores['duplicados'] ?? [],
        'ids_duplicados_sheet' => $sheetErrores['duplicados'] ?? [],
        'ids_para_insertar_en_sheet' => array_map(
            static fn(array $fila): string => (string) $fila['id'],
            array_values($paraInsertarEnSheet)
        ),
        'ids_para_insertar_en_sql' => array_map(
            static fn(array $fila): string => (string) $fila['id'],
            array_values($paraInsertarEnSql)
        ),
    ];
}

function detectarCambiosInventario(array $sqlRows, array $sheetRows): array
{
    $sqlErrores = [];
    $sheetErrores = [];
    $sqlIndexado = indexarInventarioPorId($sqlRows, $sqlErrores);
    $sheetIndexado = indexarInventarioPorId($sheetRows, $sheetErrores);
    $camposComparables = [
        'ubicacion',
        'destino',
        'editorial',
        'fecha_entrada',
        'codigo_centro',
        'colegio',
        'bultos',
        'orden',
        'fecha_salida',
        'indicador_completa',
    ];

    $paraActualizarEnSql = [];
    $diferencias = [];

    foreach ($sheetIndexado as $id => $sheetRow) {
        if (!isset($sqlIndexado[$id])) {
            continue;
        }

        $sqlRow = $sqlIndexado[$id];
        $camposDistintos = [];

        foreach ($camposComparables as $campo) {
            $sqlValor = normalizarValorComparableInventario($campo, $sqlRow[$campo] ?? null);
            $sheetValor = normalizarValorComparableInventario($campo, $sheetRow[$campo] ?? null);

            if ($sqlValor !== $sheetValor) {
                $camposDistintos[] = $campo;
            }
        }

        if ($camposDistintos === []) {
            continue;
        }

        $paraActualizarEnSql[] = $sheetRow;
        $diferencias[] = [
            'id' => $id,
            'campos_distintos' => $camposDistintos,
            'sql' => $sqlRow,
            'sheet' => $sheetRow,
        ];
    }

    return [
        'para_actualizar_en_sql' => $paraActualizarEnSql,
        'diferencias' => $diferencias,
    ];
}

function insertarInventarioEnSql(PDO $pdo, array $rows): array
{
    $resultado = [
        'insertados' => 0,
        'ignorados_por_existir' => [],
        'errores' => [],
    ];

    if ($rows === []) {
        return $resultado;
    }

    $insert = $pdo->prepare(
        'INSERT INTO inventario (
            id, ubicacion, destino, editorial, fecha_entrada, codigo_centro, colegio, bultos, `orden`, fecha_salida, indicador_completa
         ) VALUES (
            :id, :ubicacion, :destino, :editorial, :fecha_entrada, :codigo_centro, :colegio, :bultos, :orden, :fecha_salida, :indicador_completa
         )'
    );
    $selectExiste = $pdo->prepare('SELECT id FROM inventario WHERE id = :id LIMIT 1');

    foreach ($rows as $fila) {
        $filaNormalizada = normalizarFilaInventarioBidireccional($fila);

        if ($filaNormalizada['id'] === '') {
            $resultado['errores'][] = 'Se ignoró una fila de Google Sheets sin ID.';
            continue;
        }

        try {
            $selectExiste->execute([':id' => (int) $filaNormalizada['id']]);
            if ($selectExiste->fetchColumn() !== false) {
                $resultado['ignorados_por_existir'][] = $filaNormalizada['id'];
                $resultado['errores'][] = 'ID ' . $filaNormalizada['id'] . ' ya existe en SQL, se evitó duplicado.';
                continue;
            }

            $insert->execute([
                ':id' => (int) $filaNormalizada['id'],
                ':ubicacion' => $filaNormalizada['ubicacion'],
                ':destino' => $filaNormalizada['destino'],
                ':editorial' => $filaNormalizada['editorial'],
                ':fecha_entrada' => $filaNormalizada['fecha_entrada'],
                ':codigo_centro' => $filaNormalizada['codigo_centro'],
                ':colegio' => $filaNormalizada['colegio'],
                ':bultos' => $filaNormalizada['bultos'] !== null && $filaNormalizada['bultos'] !== '' ? (int) $filaNormalizada['bultos'] : null,
                ':orden' => $filaNormalizada['orden'],
                ':fecha_salida' => $filaNormalizada['fecha_salida'],
                ':indicador_completa' => $filaNormalizada['indicador_completa'],
            ]);
            $resultado['insertados']++;
        } catch (Throwable $e) {
            $mensaje = trim($e->getMessage());
            $resultado['errores'][] = 'ID ' . $filaNormalizada['id'] . ': ' . ($mensaje !== '' ? $mensaje : 'No se pudo insertar en SQL.');
        }
    }

    return $resultado;
}

function actualizarInventarioEnSql(PDO $pdo, array $rows): array
{
    $resultado = [
        'actualizados' => 0,
        'errores' => [],
    ];

    if ($rows === []) {
        return $resultado;
    }

    $update = $pdo->prepare(
        'UPDATE inventario
         SET ubicacion = :ubicacion,
             destino = :destino,
             editorial = :editorial,
             fecha_entrada = :fecha_entrada,
             codigo_centro = :codigo_centro,
             colegio = :colegio,
             bultos = :bultos,
             `orden` = :orden,
             fecha_salida = :fecha_salida,
             indicador_completa = :indicador_completa
         WHERE id = :id'
    );

    foreach ($rows as $fila) {
        $filaNormalizada = normalizarFilaInventarioBidireccional($fila);

        if ($filaNormalizada['id'] === '') {
            $resultado['errores'][] = 'Se ignoró una fila a actualizar sin ID.';
            continue;
        }

        try {
            $update->execute([
                ':id' => (int) $filaNormalizada['id'],
                ':ubicacion' => $filaNormalizada['ubicacion'],
                ':destino' => $filaNormalizada['destino'],
                ':editorial' => $filaNormalizada['editorial'],
                ':fecha_entrada' => $filaNormalizada['fecha_entrada'],
                ':codigo_centro' => $filaNormalizada['codigo_centro'],
                ':colegio' => $filaNormalizada['colegio'],
                ':bultos' => $filaNormalizada['bultos'] !== null && $filaNormalizada['bultos'] !== '' ? (int) $filaNormalizada['bultos'] : null,
                ':orden' => $filaNormalizada['orden'],
                ':fecha_salida' => $filaNormalizada['fecha_salida'],
                ':indicador_completa' => $filaNormalizada['indicador_completa'],
            ]);
            $resultado['actualizados']++;
        } catch (Throwable $e) {
            $mensaje = trim($e->getMessage());
            $resultado['errores'][] = 'ID ' . $filaNormalizada['id'] . ': ' . ($mensaje !== '' ? $mensaje : 'No se pudo actualizar en SQL.');
        }
    }

    return $resultado;
}

function sincronizarInventarioBidireccional(PDO $pdo, string $scriptUrl, string $token): array
{
    $sqlRows = obtenerInventarioSql($pdo);
    $respuestaSheet = llamarAppsScriptInventario($scriptUrl, $token, 'get_inventory');
    $sheetRows = extraerFilasInventarioDesdeAppsScript($respuestaSheet);
    $comparacion = compararInventarioPorId($sqlRows, $sheetRows);
    $cambios = detectarCambiosInventario($sqlRows, $sheetRows);
    $errores = [];
    $respuestaAppend = [];
    $idsSql = array_slice($comparacion['ids_sql'], 0, 20);
    $idsSheet = array_slice($comparacion['ids_sheet'], 0, 20);
    $idsDuplicadosSql = $comparacion['ids_duplicados_sql'];
    $idsDuplicadosSheet = $comparacion['ids_duplicados_sheet'];
    $idsParaInsertarEnSheet = $comparacion['ids_para_insertar_en_sheet'];
    $idsParaInsertarEnSql = $comparacion['ids_para_insertar_en_sql'];
    $idsParaActualizarEnSql = array_map(
        static fn(array $fila): string => (string) $fila['id'],
        $cambios['para_actualizar_en_sql']
    );
    $insertadosEnSheet = 0;

    if ($comparacion['para_insertar_en_sheet'] !== []) {
        $respuestaAppend = llamarAppsScriptInventario(
            $scriptUrl,
            $token,
            'append_inventory_rows',
            $comparacion['para_insertar_en_sheet']
        );

        if (($respuestaAppend['success'] ?? true) === false) {
            $errores[] = (string) ($respuestaAppend['error'] ?? 'Apps Script no confirmó la inserción en Google Sheets.');
        }

        $inserted = $respuestaAppend['inserted'] ?? 0;
        $insertadosEnSheet = is_numeric($inserted) ? (int) $inserted : 0;
    }

    $resultadoSql = insertarInventarioEnSql($pdo, $comparacion['para_insertar_en_sql']);
    $resultadoActualizacionSql = actualizarInventarioEnSql($pdo, $cambios['para_actualizar_en_sql']);
    if ($idsDuplicadosSql !== []) {
        $errores[] = 'IDs duplicados detectados en SQL: ' . implode(', ', $idsDuplicadosSql);
    }
    if ($idsDuplicadosSheet !== []) {
        $errores[] = 'IDs duplicados detectados en Google Sheets: ' . implode(', ', $idsDuplicadosSheet);
    }
    $errores = array_merge($errores, $resultadoSql['errores'], $resultadoActualizacionSql['errores']);

    return [
        'total_sql' => $comparacion['total_sql'],
        'total_sheet' => $comparacion['total_sheet'],
        'insertados_en_sql' => $resultadoSql['insertados'],
        'insertados_en_sheet' => $insertadosEnSheet,
        'actualizados_en_sql' => $resultadoActualizacionSql['actualizados'],
        'coincidencias' => $comparacion['coincidencias'],
        'cambios_detectados' => count($cambios['para_actualizar_en_sql']),
        'ids_sql' => $idsSql,
        'ids_sheet' => $idsSheet,
        'ids_duplicados_sql' => $idsDuplicadosSql,
        'ids_duplicados_sheet' => $idsDuplicadosSheet,
        'ids_para_insertar_en_sheet' => $idsParaInsertarEnSheet,
        'ids_para_insertar_en_sql' => $idsParaInsertarEnSql,
        'ids_para_actualizar_en_sql' => $idsParaActualizarEnSql,
        'ids_ignorados_por_existir' => $resultadoSql['ignorados_por_existir'],
        'respuesta_get_inventory' => $respuestaSheet,
        'respuesta_append_inventory_rows' => $respuestaAppend,
        'diferencias' => $cambios['diferencias'],
        'errores' => $errores,
    ];
}

function extraerFilasInventarioDesdeAppsScript(array $respuesta): array
{
    $filas = $respuesta['payload'] ?? $respuesta['rows'] ?? [];

    if (!is_array($filas)) {
        throw new RuntimeException('Apps Script no devolvió filas de inventario válidas.');
    }

    return array_map('normalizarFilaInventarioBidireccional', $filas);
}

function indexarInventarioPorId(array $rows, array &$errores = []): array
{
    $indexado = [];
    $duplicados = [];

    foreach ($rows as $fila) {
        $filaNormalizada = normalizarFilaInventarioBidireccional($fila);
        if ($filaNormalizada['id'] === '') {
            continue;
        }

        if (array_key_exists($filaNormalizada['id'], $indexado)) {
            $duplicados[$filaNormalizada['id']] = $filaNormalizada['id'];
            continue;
        }

        $indexado[$filaNormalizada['id']] = $filaNormalizada;
    }

    $errores['duplicados'] = array_values($duplicados);

    return $indexado;
}

function normalizarFilaInventarioBidireccional(array $row): array
{
    return [
        'id' => normalizarIdInventarioBidireccional($row['id'] ?? ''),
        'ubicacion' => valorInventarioBidireccional($row['ubicacion'] ?? null),
        'destino' => valorInventarioBidireccional($row['destino'] ?? null),
        'editorial' => valorInventarioBidireccional($row['editorial'] ?? null),
        'fecha_entrada' => valorInventarioBidireccional($row['fecha_entrada'] ?? null),
        'codigo_centro' => valorInventarioBidireccional($row['codigo_centro'] ?? null),
        'colegio' => valorInventarioBidireccional($row['colegio'] ?? $row['centro'] ?? null),
        'bultos' => valorInventarioBidireccional($row['bultos'] ?? null),
        'orden' => valorInventarioBidireccional($row['orden'] ?? null),
        'fecha_salida' => valorInventarioBidireccional($row['fecha_salida'] ?? null),
        'indicador_completa' => valorInventarioBidireccional($row['indicador_completa'] ?? null),
    ];
}

function valorInventarioBidireccional(mixed $valor): ?string
{
    if ($valor === null) {
        return null;
    }

    $texto = trim((string) $valor);

    return $texto !== '' ? $texto : null;
}

function normalizarIdInventarioBidireccional(mixed $valor): string
{
    return preg_replace('/\s+/', '', trim((string) $valor)) ?? '';
}

function normalizarValorComparableInventario(string $campo, mixed $valor): ?string
{
    $texto = valorInventarioBidireccional($valor);

    if ($texto === null) {
        return null;
    }

    if (in_array($campo, ['fecha_entrada', 'fecha_salida'], true)) {
        $timestamp = strtotime($texto);
        if ($timestamp !== false) {
            return date('Y-m-d', $timestamp);
        }
    }

    return $texto;
}

/*
Apps Script esperado:
- action: get_inventory
  Devuelve JSON con payload o rows, cada una con los campos del inventario.
- action: append_inventory_rows
  Recibe payload con filas nuevas y las añade a la pestaña inventario.
*/
