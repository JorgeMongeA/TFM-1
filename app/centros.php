<?php

declare(strict_types=1);

const CENTRO_DESCONOCIDO_CODIGO = '000000';
const CENTRO_DESCONOCIDO_NOMBRE = 'DESCONOCIDO';

function columnasCentrosTabla(bool $conAcciones = false): array
{
    $columnas = [
        'codigo_centro' => 'Código centro',
        'nombre_centro' => 'Nombre centro',
        'ciudad' => 'Ciudad',
        'tipo' => 'Tipo',
        'codigo_grupo' => 'Código grupo',
        'actualizado_en' => 'Actualizado en',
    ];

    if ($conAcciones) {
        $columnas['acciones'] = 'Acciones';
    }

    return $columnas;
}

function filtrosCentrosPermitidos(): array
{
    return ['codigo_centro', 'nombre_centro', 'ciudad', 'tipo', 'codigo_grupo'];
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
    $sql = 'SELECT codigo_centro, nombre_centro, ciudad, tipo, codigo_grupo, actualizado_en FROM centros';
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

    $stmt = $pdo->query('SELECT codigo_centro, nombre_centro, ciudad FROM centros ORDER BY nombre_centro ASC, codigo_centro ASC');
    return $stmt->fetchAll();
}

function asegurarCentroDesconocido(PDO $pdo): void
{
    $stmt = $pdo->prepare('SELECT codigo_centro FROM centros WHERE codigo_centro = :codigo_centro LIMIT 1');
    $stmt->execute([':codigo_centro' => CENTRO_DESCONOCIDO_CODIGO]);

    if ($stmt->fetch() !== false) {
        return;
    }

    $insert = $pdo->prepare(
        'INSERT INTO centros (codigo_centro, nombre_centro, ciudad, tipo, codigo_grupo)
         VALUES (:codigo_centro, :nombre_centro, :ciudad, :tipo, :codigo_grupo)'
    );
    $insert->execute([
        ':codigo_centro' => CENTRO_DESCONOCIDO_CODIGO,
        ':nombre_centro' => CENTRO_DESCONOCIDO_NOMBRE,
        ':ciudad' => null,
        ':tipo' => null,
        ':codigo_grupo' => null,
    ]);
}

function buscarCentroPorCodigo(PDO $pdo, string $codigoCentro): ?array
{
    $stmt = $pdo->prepare('SELECT codigo_centro, nombre_centro, ciudad, tipo, codigo_grupo FROM centros WHERE codigo_centro = :codigo_centro LIMIT 1');
    $stmt->execute([':codigo_centro' => $codigoCentro]);
    $centro = $stmt->fetch();

    return $centro !== false ? $centro : null;
}

function guardarCentro(PDO $pdo, array $datos, ?string $codigoOriginal = null): void
{
    if ($codigoOriginal === null) {
        $stmt = $pdo->prepare(
            'INSERT INTO centros (codigo_centro, nombre_centro, ciudad, tipo, codigo_grupo)
             VALUES (:codigo_centro, :nombre_centro, :ciudad, :tipo, :codigo_grupo)'
        );
        $stmt->execute([
            ':codigo_centro' => $datos['codigo_centro'],
            ':nombre_centro' => $datos['nombre_centro'] !== '' ? $datos['nombre_centro'] : null,
            ':ciudad' => $datos['ciudad'] !== '' ? $datos['ciudad'] : null,
            ':tipo' => $datos['tipo'] !== '' ? $datos['tipo'] : null,
            ':codigo_grupo' => $datos['codigo_grupo'] !== '' ? $datos['codigo_grupo'] : null,
        ]);

        return;
    }

    $stmt = $pdo->prepare(
        'UPDATE centros
         SET codigo_centro = :codigo_centro,
             nombre_centro = :nombre_centro,
             ciudad = :ciudad,
             tipo = :tipo,
             codigo_grupo = :codigo_grupo,
             actualizado_en = CURRENT_TIMESTAMP
         WHERE codigo_centro = :codigo_original'
    );
    $stmt->execute([
        ':codigo_centro' => $datos['codigo_centro'],
        ':nombre_centro' => $datos['nombre_centro'] !== '' ? $datos['nombre_centro'] : null,
        ':ciudad' => $datos['ciudad'] !== '' ? $datos['ciudad'] : null,
        ':tipo' => $datos['tipo'] !== '' ? $datos['tipo'] : null,
        ':codigo_grupo' => $datos['codigo_grupo'] !== '' ? $datos['codigo_grupo'] : null,
        ':codigo_original' => $codigoOriginal,
    ]);
}

function eliminarCentro(PDO $pdo, string $codigoCentro): void
{
    $stmt = $pdo->prepare('DELETE FROM centros WHERE codigo_centro = :codigo_centro');
    $stmt->execute([':codigo_centro' => $codigoCentro]);
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

function limpiarCampoCsvONull(mixed $valor): ?string
{
    $texto = limpiarCampoCsv($valor);

    return $texto !== '' ? $texto : null;
}
