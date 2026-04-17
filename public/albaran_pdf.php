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
require_once dirname(__DIR__) . '/app/albaranes.php';
require_once dirname(__DIR__) . '/app/layout.php';

ini_set('display_errors', '0');
ini_set('html_errors', '0');

require_login();
requierePermiso(PERMISO_ALBARANES);

function registrarErrorAlbaran(string $contexto, array $extra = [], ?Throwable $e = null): void
{
    $partes = ['[ALBARAN_PDF] ' . $contexto];

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

function mostrarErrorGenericoAlbaran(): void
{
    http_response_code(500);

    renderAppLayoutStart(
        'Albaran PDF',
        'albaran',
        'Albaran PDF',
        'Generacion de albaran'
    );
    ?>
    <section class="panel panel-card">
        <div class="alert alert-danger mb-0">No se ha podido generar el albaran en este momento.</div>
    </section>
    <?php
    renderAppLayoutEnd();
}

function cargarTcpdfLocal(): bool
{
    $tcpdfPath = dirname(__DIR__) . '/lib/tcpdf/tcpdf.php';

    if (!is_file($tcpdfPath)) {
        registrarErrorAlbaran('TCPDF no encontrada.', ['ruta_esperada' => 'lib/tcpdf/tcpdf.php']);
        return false;
    }

    require_once $tcpdfPath;

    if (!class_exists('TCPDF')) {
        registrarErrorAlbaran('La clase TCPDF no esta disponible tras la carga local.');
        return false;
    }

    return true;
}

function dibujarCajaResumenAlbaran($pdf, float $x, float $y, float $w, float $h, string $titulo, string $valor, string $texto): void
{
    $pdf->SetDrawColor(188, 204, 220);
    $pdf->SetFillColor(248, 251, 255);
    $pdf->Rect($x, $y, $w, $h, 'DF');

    $pdf->SetXY($x + 3, $y + 3);
    $pdf->SetFont('dejavusans', 'B', 8);
    $pdf->SetTextColor(72, 101, 129);
    $pdf->Cell($w - 6, 4, textoMayusculasAlbaran($titulo), 0, 1, 'L', false, '', 0, false, 'T', 'M');

    $pdf->SetX($x + 3);
    $pdf->SetFont('dejavusans', 'B', 16);
    $pdf->SetTextColor(16, 42, 67);
    $pdf->Cell($w - 6, 8, $valor, 0, 1, 'L', false, '', 0, false, 'T', 'M');

    $pdf->SetX($x + 3);
    $pdf->SetFont('dejavusans', '', 8);
    $pdf->SetTextColor(82, 96, 109);
    $pdf->MultiCell($w - 6, 4, $texto, 0, 'L', false, 1, '', '', true, 0, false, true);
}

function dibujarCajaTextoAlbaran($pdf, float $x, float $y, float $w, float $h, string $etiqueta, string $titulo, array $lineas): void
{
    $pdf->SetDrawColor(188, 204, 220);
    $pdf->SetFillColor(248, 251, 255);
    $pdf->Rect($x, $y, $w, $h, 'DF');

    $pdf->SetXY($x + 3, $y + 3);
    $pdf->SetFont('dejavusans', 'B', 8);
    $pdf->SetTextColor(72, 101, 129);
    $pdf->Cell($w - 6, 4, textoMayusculasAlbaran($etiqueta), 0, 1, 'L', false, '', 0, false, 'T', 'M');

    $pdf->SetX($x + 3);
    $pdf->SetFont('dejavusans', 'B', 11);
    $pdf->SetTextColor(16, 42, 67);
    $pdf->Cell($w - 6, 6, $titulo, 0, 1, 'L', false, '', 0, false, 'T', 'M');

    $pdf->SetFont('dejavusans', '', 9);
    foreach ($lineas as $linea) {
        $pdf->SetX($x + 3);
        $pdf->Cell($w - 6, 5, $linea, 0, 1, 'L', false, '', 0, false, 'T', 'M');
    }
}

function dibujarCabeceraTablaAlbaran($pdf, array $columnas): void
{
    $pdf->SetFillColor(234, 242, 249);
    $pdf->SetDrawColor(188, 204, 220);
    $pdf->SetTextColor(16, 42, 67);
    $pdf->SetFont('dejavusans', 'B', 8);

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

function dibujarFilaTablaAlbaran($pdf, array $columnas, array $fila, bool $fill): void
{
    $pdf->SetFont('dejavusans', '', 8.5);
    $pdf->SetTextColor(31, 41, 51);
    $pdf->SetFillColor($fill ? 249 : 255, $fill ? 251 : 255, $fill ? 252 : 255);
    $pdf->SetDrawColor(188, 204, 220);

    foreach ($columnas as $columna) {
        $valor = $fila[$columna['key']] ?? '-';

        if ($columna['key'] === 'fecha_entrada') {
            $valor = formatearFechaAlbaran((string) $valor);
        }

        $texto = limpiarTextoPlanoAlbaran($valor, '-', (int) $columna['max']);

        $pdf->MultiCell(
            (float) $columna['width'],
            8,
            $texto,
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

function dibujarCabeceraPaginaAlbaran(
    $pdf,
    string $codigoDestino,
    DateTimeInterface $fechaGeneracion,
    ?string $logoPath,
    ?string $numeroAlbaran = null,
    bool $continuacion = false
): float {
    $destino = obtenerDestinoAlbaran($codigoDestino);
    $origen = obtenerLineasOrigenAlbaran();

    $pdf->SetTextColor(16, 42, 67);

    if ($logoPath !== null && is_file($logoPath)) {
        $pdf->Image($logoPath, 14, 12, 38, 0, '', '', '', false, 300, '', false, false, 0, false, false, false);
    } else {
        $pdf->SetXY(14, 14);
        $pdf->SetFont('dejavusans', 'B', 18);
        $pdf->Cell(38, 10, 'MAXIMO', 0, 0, 'L');
    }

    $pdf->SetXY(118, 14);
    $pdf->SetFont('dejavusans', 'B', 18);
    $pdf->Cell(78, 8, 'Albaran de salida', 0, 1, 'R');

    $pdf->SetX(118);
    $pdf->SetFont('dejavusans', '', 9);
    $pdf->Cell(78, 5, 'Fecha de generacion: ' . $fechaGeneracion->format('d/m/Y H:i'), 0, 1, 'R');
    $pdf->SetX(118);
    $pdf->Cell(78, 5, 'Destino: ' . $destino['codigo'] . ' (' . $destino['nombre'] . ')', 0, 1, 'R');

    if ($numeroAlbaran !== null && trim($numeroAlbaran) !== '') {
        $pdf->SetX(118);
        $pdf->Cell(78, 5, 'Numero: ' . $numeroAlbaran, 0, 1, 'R');
    }

    if ($continuacion) {
        $pdf->SetX(118);
        $pdf->SetFont('dejavusans', 'B', 8);
        $pdf->SetTextColor(72, 101, 129);
        $pdf->Cell(78, 5, 'Continuacion del albaran', 0, 1, 'R');
    }

    $pdf->SetTextColor(31, 41, 51);

    dibujarCajaTextoAlbaran(
        $pdf,
        14,
        38,
        88,
        36,
        'Origen',
        $origen[0],
        array_slice($origen, 1)
    );

    dibujarCajaTextoAlbaran(
        $pdf,
        108,
        38,
        88,
        36,
        'Destino',
        $destino['codigo'] . ' - ' . $destino['nombre'],
        [$destino['direccion']]
    );

    return 80.0;
}

function dibujarFirmasAlbaran($pdf, string $codigoDestino, DateTimeInterface $fechaGeneracion, ?string $logoPath, ?string $numeroAlbaran = null): void
{
    $y = $pdf->GetY() + 8;
    $limite = 257.0;

    if ($y > $limite) {
        $pdf->AddPage();
        $y = dibujarCabeceraPaginaAlbaran($pdf, $codigoDestino, $fechaGeneracion, $logoPath, $numeroAlbaran, true) + 12;
    }

    $ancho = 88.0;
    $alto = 28.0;

    foreach ([14.0 => 'Preparado por', 108.0 => 'Recibido / validado'] as $x => $titulo) {
        $pdf->SetDrawColor(188, 204, 220);
        $pdf->SetFillColor(255, 255, 255);
        $pdf->Rect($x, $y, $ancho, $alto, 'D');
        $pdf->SetXY($x + 3, $y + 3);
        $pdf->SetFont('dejavusans', 'B', 8);
        $pdf->SetTextColor(72, 101, 129);
        $pdf->Cell($ancho - 6, 4, textoMayusculasAlbaran($titulo), 0, 1, 'L');
        $pdf->Line($x + 5, $y + 21, $x + $ancho - 5, $y + 21);
    }
}

function renderizarBloqueDestinoAlbaran(
    $pdf,
    string $codigoDestino,
    array $lineas,
    DateTimeInterface $fechaGeneracion,
    ?string $logoPath,
    ?string $numeroAlbaran = null
): void {
    $resumen = obtenerResumenMercanciaAlbaran($lineas);
    $columnas = columnasTablaAlbaranSalida();
    $filaPar = false;

    $pdf->AddPage();
    $inicioTabla = dibujarCabeceraPaginaAlbaran($pdf, $codigoDestino, $fechaGeneracion, $logoPath, $numeroAlbaran);

    dibujarCajaResumenAlbaran(
        $pdf,
        14,
        $inicioTabla,
        88,
        20,
        'Resumen',
        (string) $resumen['bultos'],
        'Cantidad total de bultos incluidos en este albaran.'
    );

    dibujarCajaResumenAlbaran(
        $pdf,
        108,
        $inicioTabla,
        88,
        20,
        'Lineas',
        (string) $resumen['lineas'],
        'Lineas de mercancia incluidas en este albaran.'
    );

    $pdf->SetXY(14, $inicioTabla + 27);
    $pdf->SetFont('dejavusans', 'B', 11);
    $pdf->SetTextColor(16, 42, 67);
    $pdf->Cell(182, 6, 'Detalle de mercancia', 0, 1, 'L');

    $pdf->SetX(14);
    dibujarCabeceraTablaAlbaran($pdf, $columnas);

    foreach ($lineas as $fila) {
        if ($pdf->GetY() + 10 > 272) {
            $pdf->AddPage();
            $pdf->SetY(dibujarCabeceraPaginaAlbaran($pdf, $codigoDestino, $fechaGeneracion, $logoPath, $numeroAlbaran, true));
            $pdf->Ln(27);
            $pdf->SetX(14);
            dibujarCabeceraTablaAlbaran($pdf, $columnas);
        }

        $pdf->SetX(14);
        dibujarFilaTablaAlbaran($pdf, $columnas, $fila, $filaPar);
        $filaPar = !$filaPar;
    }

    dibujarFirmasAlbaran($pdf, $codigoDestino, $fechaGeneracion, $logoPath, $numeroAlbaran);
}

$tipo = trim((string) ($_REQUEST['tipo'] ?? ''));
$numeroAlbaran = trim((string) ($_REQUEST['numero_albaran'] ?? ''));
$seleccionadosIds = leerIdsSeleccionadosDesdeRequest($_REQUEST);

if ($tipo !== 'salida' || ($seleccionadosIds === [] && $numeroAlbaran === '')) {
    registrarErrorAlbaran('Peticion de albaran invalida.', ['tipo' => $tipo, 'ids' => $seleccionadosIds, 'numero_albaran' => $numeroAlbaran]);
    mostrarErrorGenericoAlbaran();
    return;
}

if (!cargarTcpdfLocal()) {
    mostrarErrorGenericoAlbaran();
    return;
}

try {
    $pdo = conectar();

    if ($numeroAlbaran !== '') {
        $mercanciaSeleccionada = consultarHistoricoPorNumeroAlbaran($pdo, $numeroAlbaran);
    } else {
        $mercanciaSeleccionada = consultarInventarioPorIds($pdo, $seleccionadosIds, INVENTARIO_ESTADO_ACTIVO);
    }

    if ($mercanciaSeleccionada === []) {
        throw new RuntimeException('No hay mercancia disponible para la solicitud indicada.');
    }

    if ($numeroAlbaran === '') {
        $idsEncontrados = array_map(static fn(array $fila): int => (int) ($fila['id'] ?? 0), $mercanciaSeleccionada);
        $idsNoEncontrados = array_values(array_diff($seleccionadosIds, $idsEncontrados));

        if ($idsNoEncontrados !== []) {
            throw new RuntimeException('La seleccion contiene mercancias no disponibles.');
        }
    }

    $gruposPorDestino = agruparMercanciaPorDestinoAlbaran($mercanciaSeleccionada);
    $fechaGeneracion = new DateTimeImmutable('now', new DateTimeZone('Europe/Madrid'));
    $logoPath = dirname(__DIR__) . '/public/assets/img/logo_maximos.png';
    $logoDisponible = is_file($logoPath) ? $logoPath : null;

    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator('Sistema interno de inventario');
    $pdf->SetAuthor('MAXIMO SERVICIOS LOGISTICOS S.L.U.');
    $pdf->SetTitle('Albaran de salida');
    $pdf->SetSubject('Documento logistico de salida');
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetMargins(14, 12, 14);
    $pdf->SetAutoPageBreak(true, 18);
    $pdf->SetFont('dejavusans', '', 9);

    foreach ($gruposPorDestino as $codigoDestino => $lineas) {
        renderizarBloqueDestinoAlbaran($pdf, $codigoDestino, $lineas, $fechaGeneracion, $logoDisponible, $numeroAlbaran !== '' ? $numeroAlbaran : null);
    }

    $pdf->Output(construirNombreArchivoAlbaran($fechaGeneracion, $numeroAlbaran !== '' ? $numeroAlbaran : null), 'I');
    exit;
} catch (Throwable $e) {
    registrarErrorAlbaran(
        'Fallo al generar el albaran.',
        ['tipo' => $tipo, 'ids' => $seleccionadosIds, 'numero_albaran' => $numeroAlbaran],
        $e
    );
    mostrarErrorGenericoAlbaran();
    return;
}
