<?php

declare(strict_types=1);

const ALBARAN_ORIGEN_FIJO = [
    'MÁXIMO SERVICIOS LOGÍSTICOS S.L.U.',
    'Calle Buenos Aires 23',
    'Polígono Ind. Centrovía',
    'La Muela',
    'Zaragoza - España',
];

function destinosAlbaranDisponibles(): array
{
    return [
        'EPL' => [
            'codigo' => 'EPL',
            'nombre' => 'Épila',
            'direccion' => 'Av. Tastueña, 50290 Épila, Zaragoza',
        ],
        'EDV' => [
            'codigo' => 'EDV',
            'nombre' => 'Zaragoza',
            'direccion' => 'N-II, KM 315, 700, 50012 Zaragoza',
        ],
    ];
}

function leerIdsSeleccionadosDesdeRequest(array $source): array
{
    $ids = $source['seleccionados'] ?? [];

    if (!is_array($ids)) {
        $ids = [$ids];
    }

    $ids = array_map(static fn(mixed $valor): int => (int) $valor, $ids);
    $ids = array_filter($ids, static fn(int $valor): bool => $valor > 0);

    return array_values(array_unique($ids));
}

function normalizarTextoDestinoAlbaran(?string $valor): string
{
    $texto = trim((string) $valor);

    if ($texto === '') {
        return '';
    }

    $texto = strtr($texto, [
        'á' => 'a',
        'Á' => 'A',
        'é' => 'e',
        'É' => 'E',
        'í' => 'i',
        'Í' => 'I',
        'ó' => 'o',
        'Ó' => 'O',
        'ú' => 'u',
        'Ú' => 'U',
        'ü' => 'u',
        'Ü' => 'U',
        'ñ' => 'n',
        'Ñ' => 'N',
    ]);
    $texto = strtoupper($texto);

    return preg_replace('/[^A-Z0-9]+/', '', $texto) ?? '';
}

function normalizarDestinoAlbaran(?string $destino): ?string
{
    $destinoNormalizado = normalizarTextoDestinoAlbaran($destino);

    if ($destinoNormalizado === '') {
        return null;
    }

    if (
        str_contains($destinoNormalizado, 'EPL')
        || str_contains($destinoNormalizado, 'EPILA')
        || str_contains($destinoNormalizado, 'TASTUENA')
    ) {
        return 'EPL';
    }

    if (
        str_contains($destinoNormalizado, 'EDV')
        || str_contains($destinoNormalizado, 'NII')
        || str_contains($destinoNormalizado, 'KM315')
        || str_contains($destinoNormalizado, '315700')
    ) {
        return 'EDV';
    }

    return null;
}

function obtenerDestinoAlbaran(string $codigoDestino): array
{
    $destinos = destinosAlbaranDisponibles();

    if (!isset($destinos[$codigoDestino])) {
        throw new RuntimeException('Destino logístico no soportado para albarán.');
    }

    return $destinos[$codigoDestino];
}

function agruparMercanciaPorDestinoAlbaran(array $mercancia): array
{
    $grupos = [];
    $idsSinDestino = [];

    foreach ($mercancia as $fila) {
        $codigoDestino = normalizarDestinoAlbaran((string) ($fila['destino'] ?? ''));

        if ($codigoDestino === null) {
            $idsSinDestino[] = (int) ($fila['id'] ?? 0);
            continue;
        }

        if (!isset($grupos[$codigoDestino])) {
            $grupos[$codigoDestino] = [];
        }

        $grupos[$codigoDestino][] = $fila;
    }

    if ($idsSinDestino !== []) {
        throw new RuntimeException('Hay mercancías sin destino logístico válido para generar el albarán.');
    }

    if ($grupos === []) {
        throw new RuntimeException('No hay mercancía válida para generar el albarán.');
    }

    $gruposOrdenados = [];

    foreach (['EPL', 'EDV'] as $codigoDestino) {
        if (isset($grupos[$codigoDestino])) {
            $gruposOrdenados[$codigoDestino] = $grupos[$codigoDestino];
        }
    }

    return $gruposOrdenados;
}

function formatearFechaAlbaran(?string $fecha): string
{
    $fecha = trim((string) $fecha);

    if ($fecha === '') {
        return '-';
    }

    try {
        return (new DateTimeImmutable($fecha))->format('d/m/Y');
    } catch (Throwable $e) {
        return $fecha;
    }
}

function limpiarTextoPlanoAlbaran(mixed $valor, string $fallback = '-', int $maxLength = 0): string
{
    $texto = trim(strip_tags((string) $valor));
    $texto = preg_replace('/\s+/u', ' ', $texto) ?? '';

    if ($texto === '') {
        return $fallback;
    }

    if ($maxLength > 0 && longitudTextoAlbaran($texto) > $maxLength) {
        $texto = recortarTextoAlbaran($texto, $maxLength - 1) . '...';
    }

    return $texto;
}

function textoMayusculasAlbaran(string $texto): string
{
    if (function_exists('mb_strtoupper')) {
        return mb_strtoupper($texto, 'UTF-8');
    }

    return strtoupper($texto);
}

function longitudTextoAlbaran(string $texto): int
{
    if (function_exists('mb_strlen')) {
        return mb_strlen($texto, 'UTF-8');
    }

    return strlen($texto);
}

function recortarTextoAlbaran(string $texto, int $longitud): string
{
    if ($longitud <= 0) {
        return '';
    }

    if (function_exists('mb_substr')) {
        return mb_substr($texto, 0, $longitud, 'UTF-8');
    }

    return substr($texto, 0, $longitud);
}

function obtenerLineasOrigenAlbaran(): array
{
    return ALBARAN_ORIGEN_FIJO;
}

function obtenerResumenMercanciaAlbaran(array $lineas): array
{
    $totalBultos = 0;

    foreach ($lineas as $linea) {
        $totalBultos += (int) ($linea['bultos'] ?? 0);
    }

    return [
        'lineas' => count($lineas),
        'bultos' => $totalBultos,
    ];
}

function columnasTablaAlbaranSalida(): array
{
    return [
        ['key' => 'id', 'label' => 'ID', 'width' => 16.0, 'max' => 10, 'align' => 'C'],
        ['key' => 'bultos', 'label' => 'Cantidad de bultos', 'width' => 28.0, 'max' => 12, 'align' => 'C'],
        ['key' => 'colegio', 'label' => 'Colegio', 'width' => 50.0, 'max' => 34, 'align' => 'L'],
        ['key' => 'editorial', 'label' => 'Editorial', 'width' => 34.0, 'max' => 24, 'align' => 'L'],
        ['key' => 'fecha_entrada', 'label' => 'Fecha de entrada', 'width' => 26.0, 'max' => 10, 'align' => 'C'],
        ['key' => 'orden', 'label' => 'Orden', 'width' => 28.0, 'max' => 18, 'align' => 'L'],
    ];
}

function construirNombreArchivoAlbaran(DateTimeInterface $fechaGeneracion): string
{
    return 'albaran_salida_' . $fechaGeneracion->format('Ymd_His') . '.pdf';
}
