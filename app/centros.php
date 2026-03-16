<?php

declare(strict_types=1);

function cargarCentros(PDO $pdo): array
{
    $stmt = $pdo->query('SELECT codigo_centro, nombre_centro, ciudad, tipo, codigo_grupo, actualizado_en FROM centros ORDER BY codigo_centro ASC');
    return $stmt->fetchAll();
}

function sincronizarCentrosDesdeCsv(PDO $pdo, string $csvUrl): array
{
    if (trim($csvUrl) === '') {
        throw new RuntimeException('URL CSV no definida.');
    }

    $contenido = descargarCsvCentros($csvUrl);
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
