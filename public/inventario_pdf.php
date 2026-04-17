<?php

/**
 * Autor: Jorge Monge
 * Trabajo Final de Máster (TFM)
 * UOC - 2026
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/conexion.php';
require_once dirname(__DIR__) . '/app/auth.php';
require_once dirname(__DIR__) . '/app/inventario.php';
require_once dirname(__DIR__) . '/app/actividad.php';

ini_set('display_errors', '0');
ini_set('html_errors', '0');

require_login();
requierePermiso(PERMISO_INVENTARIO_CONSULTA, 'No tienes permisos para imprimir el inventario.');

function registrarErrorInventarioPdf(string $contexto, array $extra = [], ?Throwable $e = null): void
{
    $partes = ['[INVENTARIO_PDF] ' . $contexto];

    if ($extra !== []) {
        $json = json_encode($extra, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json !== false) {
            $partes[] = $json;
        }
    }

    if ($e !== null) {
        $partes[] = $e->getMessage();
    }

    error_log(implode(' | ', $partes));
}

function cargarTcpdfInventario(): bool
{
    $tcpdfPath = dirname(__DIR__) . '/lib/tcpdf/tcpdf.php';

    if (!is_file($tcpdfPath)) {
        registrarErrorInventarioPdf('TCPDF no encontrada.');
        return false;
    }

    require_once $tcpdfPath;

    return class_exists('TCPDF');
}

function mostrarErrorGenericoInventarioPdf(): void
{
    http_response_code(500);
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Inventario PDF</title></head><body><p>No se ha podido generar el PDF del inventario.</p></body></html>';
}

function valorPlanoPdfInventario(mixed $valor, string $fallback = '-'): string
{
    $texto = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $valor)) ?? '');
    return $texto !== '' ? $texto : $fallback;
}

function formatearFechaPdfInventario(?string $fecha): string
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

function resumenFiltrosInventarioPdf(array $filtros): string
{
    $etiquetas = [
        'editorial' => 'Editorial',
        'colegio' => 'Colegio',
        'codigo_centro' => 'Codigo centro',
        'destino' => 'Destino',
    ];

    $partes = [];
    foreach ($etiquetas as $clave => $etiqueta) {
        $valor = trim((string) ($filtros[$clave] ?? ''));
        if ($valor !== '') {
            $partes[] = $etiqueta . ': ' . $valor;
        }
    }

    return $partes === [] ? 'Sin filtros' : implode(' | ', $partes);
}

function descripcionOrdenInventarioPdf(string $ordenar, string $direccion): string
{
    $columnas = columnasInventarioTabla();
    $label = $columnas[$ordenar] ?? ucfirst(str_replace('_', ' ', $ordenar));

    return $label . ' (' . strtoupper($direccion) . ')';
}

function columnasPdfInventario(): array
{
    return [
        ['key' => 'id', 'label' => 'ID', 'width' => 12.0, 'align' => 'C'],
        ['key' => 'ubicacion', 'label' => 'Ubicacion', 'width' => 28.0, 'align' => 'L'],
        ['key' => 'destino', 'label' => 'Destino', 'width' => 16.0, 'align' => 'C'],
        ['key' => 'editorial', 'label' => 'Editorial', 'width' => 28.0, 'align' => 'L'],
        ['key' => 'fecha_entrada', 'label' => 'Fecha entrada', 'width' => 22.0, 'align' => 'C'],
        ['key' => 'codigo_centro', 'label' => 'Codigo centro', 'width' => 22.0, 'align' => 'C'],
        ['key' => 'colegio', 'label' => 'Centro / Colegio', 'width' => 44.0, 'align' => 'L'],
        ['key' => 'fecha_salida', 'label' => 'Fecha salida', 'width' => 20.0, 'align' => 'C'],
        ['key' => 'orden', 'label' => 'Orden', 'width' => 22.0, 'align' => 'L'],
        ['key' => 'bultos', 'label' => 'Bultos', 'width' => 14.0, 'align' => 'C'],
        ['key' => 'indicador_completa', 'label' => 'Indicador', 'width' => 18.0, 'align' => 'L'],
        ['key' => 'revision', 'label' => 'Rev.', 'width' => 12.0, 'align' => 'C'],
    ];
}

function cabeceraTablaPdfInventario(TCPDF $pdf, array $columnas): void
{
    $pdf->SetFillColor(233, 240, 247);
    $pdf->SetDrawColor(184, 198, 214);
    $pdf->SetTextColor(18, 43, 66);
    $pdf->SetFont('dejavusans', 'B', 7.5);

    foreach ($columnas as $columna) {
        $pdf->MultiCell(
            (float) $columna['width'],
            9,
            $columna['label'],
            1,
            'C',
            true,
            0,
            '',
            '',
            true,
            0,
            false,
            true,
            9,
            'M'
        );
    }

    $pdf->Ln();
}

function filaTablaPdfInventario(TCPDF $pdf, array $columnas, array $fila, bool $fill): void
{
    $pdf->SetFont('dejavusans', '', 7.2);
    $pdf->SetTextColor(31, 41, 55);
    $pdf->SetFillColor($fill ? 248 : 255, $fill ? 250 : 255, $fill ? 252 : 255);
    $pdf->SetDrawColor(196, 208, 221);

    foreach ($columnas as $columna) {
        $valor = match ($columna['key']) {
            'fecha_entrada', 'fecha_salida' => formatearFechaPdfInventario((string) ($fila[$columna['key']] ?? '')),
            'revision' => '',
            default => valorPlanoPdfInventario($fila[$columna['key']] ?? null),
        };

        $pdf->MultiCell(
            (float) $columna['width'],
            8,
            $valor,
            1,
            (string) $columna['align'],
            true,
            0,
            '',
            '',
            true,
            0,
            false,
            true,
            8,
            'M'
        );
    }

    $pdf->Ln();
}

function dibujarCabeceraInventarioPdf(
    TCPDF $pdf,
    DateTimeInterface $fechaGeneracion,
    string $usuario,
    array $filtros,
    string $ordenar,
    string $direccion,
    int $totalRegistros
): float {
    $pdf->SetTextColor(16, 42, 67);
    $pdf->SetFont('dejavusans', 'B', 18);
    $pdf->SetXY(12, 10);
    $pdf->Cell(0, 8, 'Listado de stock', 0, 1, 'L');

    $pdf->SetFont('dejavusans', '', 8.5);
    $pdf->SetTextColor(82, 96, 109);
    $pdf->SetX(12);
    $pdf->Cell(0, 5, 'Generado: ' . $fechaGeneracion->format('d/m/Y H:i') . ' | Usuario: ' . ($usuario !== '' ? $usuario : 'sistema'), 0, 1, 'L');
    $pdf->SetX(12);
    $pdf->Cell(0, 5, 'Filtros: ' . resumenFiltrosInventarioPdf($filtros), 0, 1, 'L');
    $pdf->SetX(12);
    $pdf->Cell(0, 5, 'Orden aplicado: ' . descripcionOrdenInventarioPdf($ordenar, $direccion) . ' | Total lineas: ' . $totalRegistros, 0, 1, 'L');

    $pdf->SetDrawColor(205, 215, 226);
    $pdf->Line(12, 31, 285, 31);

    return 35.0;
}

function construirNombreArchivoInventarioPdf(DateTimeInterface $fechaGeneracion): string
{
    return 'inventario_stock_' . $fechaGeneracion->format('Ymd_His') . '.pdf';
}

$filtros = leerFiltrosInventarioDesdeRequest($_GET);
[$ordenar, $direccion] = leerOrdenInventarioDesdeRequest($_GET);

if (!cargarTcpdfInventario()) {
    mostrarErrorGenericoInventarioPdf();
    return;
}

try {
    $pdo = conectar();
    $registros = consultarInventario($pdo, $filtros, $ordenar, $direccion);
    $fechaGeneracion = new DateTimeImmutable('now', new DateTimeZone('Europe/Madrid'));
    $usuario = trim((string) ($_SESSION['username'] ?? $_SESSION['usuario'] ?? ''));

    $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator('Sistema interno de inventario');
    $pdf->SetAuthor('CONGREGACIONES');
    $pdf->SetTitle('Listado de stock');
    $pdf->SetSubject('Listado operativo de inventario');
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(true);
    $pdf->SetMargins(12, 10, 12);
    $pdf->SetHeaderMargin(0);
    $pdf->SetFooterMargin(8);
    $pdf->SetAutoPageBreak(true, 12);
    $pdf->SetFont('dejavusans', '', 8);
    $pdf->AddPage();

    $columnas = columnasPdfInventario();
    $inicioTabla = dibujarCabeceraInventarioPdf($pdf, $fechaGeneracion, $usuario, $filtros, $ordenar, $direccion, count($registros));
    $pdf->SetY($inicioTabla);
    cabeceraTablaPdfInventario($pdf, $columnas);

    $filaPar = false;
    foreach ($registros as $fila) {
        if ($pdf->GetY() + 9 > 195) {
            $pdf->AddPage();
            $pdf->SetY(dibujarCabeceraInventarioPdf($pdf, $fechaGeneracion, $usuario, $filtros, $ordenar, $direccion, count($registros)));
            cabeceraTablaPdfInventario($pdf, $columnas);
        }

        $pdf->SetX(12);
        filaTablaPdfInventario($pdf, $columnas, $fila, $filaPar);
        $filaPar = !$filaPar;
    }

    registrarActividadSistema($pdo, [
        'usuario_id' => isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null,
        'usuario' => $usuario,
        'tipo_evento' => ACTIVIDAD_TIPO_INVENTARIO_PDF,
        'entidad' => 'inventario',
        'descripcion' => 'Generacion de listado PDF de stock ordenado por ' . $ordenar,
        'metadata' => [
            'filtros' => $filtros,
            'ordenar' => $ordenar,
            'direccion' => $direccion,
            'total_lineas' => count($registros),
        ],
        'fecha_evento' => $fechaGeneracion,
    ]);

    $pdf->Output(construirNombreArchivoInventarioPdf($fechaGeneracion), 'I');
    exit;
} catch (Throwable $e) {
    registrarErrorInventarioPdf('Fallo al generar el listado PDF.', [
        'filtros' => $filtros,
        'ordenar' => $ordenar,
        'direccion' => $direccion,
    ], $e);
    mostrarErrorGenericoInventarioPdf();
    return;
}
