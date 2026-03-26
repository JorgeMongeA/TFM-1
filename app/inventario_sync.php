<?php

declare(strict_types=1);

require_once __DIR__ . '/centros.php';

function sincronizarInventarioDesdeCsv(PDO $pdo, string $csvUrl): array
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

    $select = $pdo->prepare(
        'SELECT id
         FROM inventario
         WHERE codigo_centro = :codigo_centro
           AND editorial = :editorial
           AND fecha_entrada = :fecha_entrada
           AND ubicacion = :ubicacion
         LIMIT 1'
    );
    $insert = $pdo->prepare(
        'INSERT INTO inventario (
            id, editorial, colegio, codigo_centro, ubicacion, fecha_entrada, fecha_salida, bultos, destino, `orden`, indicador_completa
         ) VALUES (
            :id, :editorial, :colegio, :codigo_centro, :ubicacion, :fecha_entrada, :fecha_salida, :bultos, :destino, :orden, :indicador_completa
         )'
    );
    $update = $pdo->prepare(
        'UPDATE inventario
         SET destino = :destino,
             colegio = :colegio,
             fecha_entrada = :fecha_entrada,
             ubicacion = :ubicacion
         WHERE id = :id'
    );

    $siguienteId = obtenerSiguienteIdSincronizacionInventario($pdo);

    // Futuro: desde aquí se podrá coordinar también la sincronización inversa SQL -> Google Sheets.

    try {
        $pdo->beginTransaction();

        foreach ($lineas as $indice => $linea) {
            if ($indice === 0) {
                continue;
            }

            $fila = str_getcsv($linea);
            procesarFilaInventario($select, $insert, $update, $resumen, $fila, $indice + 1, $siguienteId);
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

function procesarFilaInventario(
    PDOStatement $select,
    PDOStatement $insert,
    PDOStatement $update,
    array &$resumen,
    array $fila,
    int $numeroFila,
    int &$siguienteId
): void {
    $resumen['total_leidos']++;
    $datosFila = extraerFilaInventario($fila);

    if ($datosFila === null) {
        $resumen['ignorados']++;
        return;
    }

    if ($datosFila['codigo_centro'] === null || $datosFila['editorial'] === null || $datosFila['fecha_entrada'] === null || $datosFila['ubicacion'] === null) {
        $resumen['ignorados']++;
        return;
    }

    try {
        $select->execute([
            ':codigo_centro' => $datosFila['codigo_centro'],
            ':editorial' => $datosFila['editorial'],
            ':fecha_entrada' => $datosFila['fecha_entrada'],
            ':ubicacion' => $datosFila['ubicacion'],
        ]);

        $idExistente = $select->fetchColumn();

        if ($idExistente === false) {
            $insert->execute([
                ':id' => $siguienteId,
                ':editorial' => $datosFila['editorial'],
                ':colegio' => $datosFila['colegio'],
                ':codigo_centro' => $datosFila['codigo_centro'],
                ':ubicacion' => $datosFila['ubicacion'],
                ':fecha_entrada' => $datosFila['fecha_entrada'],
                ':fecha_salida' => null,
                ':bultos' => null,
                ':destino' => $datosFila['destino'],
                ':orden' => null,
                ':indicador_completa' => null,
            ]);
            $siguienteId++;
            $resumen['insertados']++;
            return;
        }

        $update->execute([
            ':id' => (int) $idExistente,
            ':destino' => $datosFila['destino'],
            ':colegio' => $datosFila['colegio'],
            ':fecha_entrada' => $datosFila['fecha_entrada'],
            ':ubicacion' => $datosFila['ubicacion'],
        ]);
        $resumen['actualizados']++;
    } catch (Throwable $e) {
        $mensaje = trim($e->getMessage());
        $resumen['errores'][] = 'Fila ' . $numeroFila . ': ' . ($mensaje !== '' ? $mensaje : 'Error al guardar el registro.');
    }
}

function extraerFilaInventario(array $fila): ?array
{
    $fila = array_pad($fila, 7, null);

    $datos = [
        'ubicacion' => limpiarCampoCsvONull($fila[0]),
        'destino' => limpiarCampoCsvONull($fila[1]),
        'editorial' => limpiarCampoCsvONull($fila[3]),
        'fecha_entrada' => limpiarCampoCsvONull($fila[4]),
        'codigo_centro' => limpiarCampoCsvONull($fila[5]),
        'colegio' => limpiarCampoCsvONull($fila[6]),
    ];

    if (implode('', array_map(static fn(?string $valor): string => $valor ?? '', $datos)) === '') {
        return null;
    }

    return $datos;
}

function obtenerSiguienteIdSincronizacionInventario(PDO $pdo): int
{
    $stmt = $pdo->query('SELECT MAX(id) + 1 AS siguiente FROM inventario');
    $siguiente = $stmt->fetchColumn();

    if ($siguiente === false || $siguiente === null) {
        return 1;
    }

    return max(1, (int) $siguiente);
}
