<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/conexion.php';
require_once dirname(__DIR__) . '/app/auth.php';
require_once dirname(__DIR__) . '/app/inventario.php';
require_once dirname(__DIR__) . '/app/albaranes.php';
require_once dirname(__DIR__) . '/app/layout.php';

ini_set('display_errors', '0');
ini_set('html_errors', '0');

require_login();

function registrarErrorEtiquetas(string $contexto, array $extra = [], ?Throwable $e = null): void
{
    $partes = ['[ETIQUETAS_PDF] ' . $contexto];

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

function mostrarErrorGenericoEtiquetas(): void
{
    http_response_code(500);

    renderAppLayoutStart(
        'Etiquetas PDF',
        'etiquetar',
        'Etiquetas PDF',
        'Generacion de etiquetas'
    );
    ?>
    <section class="panel panel-card">
        <div class="alert alert-danger mb-0">No se han podido generar las etiquetas en este momento.</div>
    </section>
    <?php
    renderAppLayoutEnd();
}

function cargarTcpdfLocalEtiquetas(): bool
{
    $tcpdfPath = dirname(__DIR__) . '/lib/tcpdf/tcpdf.php';

    if (!is_file($tcpdfPath)) {
        registrarErrorEtiquetas('TCPDF no encontrada.', ['ruta_esperada' => 'lib/tcpdf/tcpdf.php']);
        return false;
    }

    require_once $tcpdfPath;

    if (!class_exists('TCPDF')) {
        registrarErrorEtiquetas('La clase TCPDF no esta disponible tras la carga local.');
        return false;
    }

    return true;
}

function leerIdsEtiquetasDesdeRequest(array $source): array
{
    $ids = $source['seleccionados'] ?? ($source['id'] ?? []);

    if (!is_array($ids)) {
        $ids = [$ids];
    }

    $ids = array_map(static fn(mixed $valor): int => (int) $valor, $ids);
    $ids = array_values(array_filter($ids, static fn(int $valor): bool => $valor > 0));

    return array_values(array_unique($ids));
}

function limpiarTextoEtiqueta(mixed $valor, string $fallback = '-'): string
{
    $texto = trim(strip_tags((string) $valor));
    $texto = preg_replace('/\s+/u', ' ', $texto) ?? '';

    return $texto !== '' ? $texto : $fallback;
}

function ajustarTextoEtiqueta(string $texto): string
{
    if (function_exists('mb_strtoupper')) {
        return mb_strtoupper($texto, 'UTF-8');
    }

    return strtoupper($texto);
}

function construirNombreArchivoEtiquetas(array $lineas): string
{
    $primera = $lineas[0]['id'] ?? 'lote';
    $identificador = preg_replace('/[^A-Z0-9_-]+/i', '_', (string) $primera) ?? 'lote';

    return 'etiquetas_' . $identificador . '.pdf';
}

function longitudTextoEtiqueta(string $texto): int
{
    if (function_exists('mb_strlen')) {
        return mb_strlen($texto, 'UTF-8');
    }

    return strlen($texto);
}

function calcularTamanoFuenteEtiqueta(string $texto, float $maximo, float $minimo): float
{
    $longitud = longitudTextoEtiqueta($texto);

    if ($longitud <= 12) {
        return $maximo;
    }

    if ($longitud >= 42) {
        return $minimo;
    }

    $rango = $maximo - $minimo;
    $proporcion = ($longitud - 12) / 30;

    return max($minimo, $maximo - ($rango * $proporcion));
}

function dibujarCajaEtiqueta($pdf, float $x, float $y, float $w, float $h, string $titulo, float $altoCabecera = 7.5): void
{
    $pdf->SetDrawColor(0, 0, 0);
    $pdf->SetLineWidth(0.8);
    $pdf->Rect($x, $y, $w, $h, 'D');

    $pdf->SetFillColor(0, 0, 0);
    $pdf->Rect($x, $y, $w, $altoCabecera, 'F');

    $pdf->SetXY($x + 1.5, $y + 1.2);
    $pdf->SetFont('dejavusans', 'B', 8);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->Cell($w - 3, 4.5, $titulo, 0, 1, 'L');
}

function dibujarValorCentradoEtiqueta(
    $pdf,
    float $x,
    float $y,
    float $w,
    float $h,
    string $texto,
    float $tamano,
    string $alineacion = 'C'
): void {
    $pdf->SetXY($x, $y);
    $pdf->SetFont('dejavusans', 'B', $tamano);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->MultiCell($w, $h, $texto, 0, $alineacion, false, 1, '', '', true, 0, false, true, $h, 'M');
}

function dibujarValorLineaEtiqueta(
    $pdf,
    float $x,
    float $y,
    float $w,
    float $h,
    string $texto,
    float $tamano,
    string $alineacion = 'C',
    float $tamanoMinimoCompacto = 10.0
): void {
    $tamanoAjustado = min($tamano, max($tamanoMinimoCompacto, $h - 1.5));

    $pdf->SetXY($x, $y);
    $pdf->SetFont('dejavusans', 'B', $tamanoAjustado);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell($w, $h, $texto, 0, 1, $alineacion, false, '', 1, false, 'M', 'M');
}

function dibujarBloqueEtiqueta($pdf, array $fila, float $x, float $y, float $w, float $h, bool $superior): void
{
    $colegio = ajustarTextoEtiqueta(limpiarTextoEtiqueta($fila['colegio'] ?? null));
    $editorial = ajustarTextoEtiqueta(limpiarTextoEtiqueta($fila['editorial'] ?? null));
    $id = limpiarTextoEtiqueta($fila['id'] ?? null);
    $orden = ajustarTextoEtiqueta(limpiarTextoEtiqueta($fila['orden'] ?? null));
    $bultos = limpiarTextoEtiqueta($fila['bultos'] ?? null);
    $ubicacion = ajustarTextoEtiqueta(limpiarTextoEtiqueta($fila['ubicacion'] ?? null));

    $margenExterior = 7.0;
    $cajaX = $x + $margenExterior;
    $cajaY = $y + $margenExterior;
    $cajaW = $w - ($margenExterior * 2);
    $cajaH = $h - ($margenExterior * 2);

    $separacion = 3.5;
    $filaSuperiorH = 20.0;
    $filaCentralH = 38.0;
    $filaEditorialH = 32.0;
    $filaInferiorH = 20.0;
    $bloqueIdW = 42.0;
    $bloqueBultosW = 40.0;
    $bloqueUbicacionW = 86.0;
    $altoCabecera = 6.5;
    $logoPath = dirname(__DIR__) . '/public/assets/img/logo_maximos.png';

    $pdf->SetDrawColor(0, 0, 0);
    $pdf->SetLineWidth(1.2);
    $pdf->Rect($cajaX, $cajaY, $cajaW, $cajaH, 'D');

    $pdf->SetLineWidth(0.35);
    $pdf->Line($cajaX + 3, $y + ($superior ? $h - 2.5 : 2.5), $cajaX + $cajaW - 3, $y + ($superior ? $h - 2.5 : 2.5));

    $topX = $cajaX + 5;
    $topY = $cajaY + 5;
    $topW = $cajaW - 10;

    dibujarCajaEtiqueta($pdf, $topX, $topY, $bloqueIdW, $filaSuperiorH, 'ID', $altoCabecera);
    dibujarValorLineaEtiqueta(
        $pdf,
        $topX + 2,
        $topY + $altoCabecera + 2.8,
        $bloqueIdW - 4,
        $filaSuperiorH - $altoCabecera - 5.0,
        $id,
        calcularTamanoFuenteEtiqueta($id, 33, 23),
        'C',
        16.0
    );

    $ordenX = $topX + $bloqueIdW + $separacion;
    $ordenW = $topW - $bloqueIdW - $separacion;
    dibujarCajaEtiqueta($pdf, $ordenX, $topY, $ordenW, $filaSuperiorH, 'ORDEN', $altoCabecera);
    dibujarValorLineaEtiqueta(
        $pdf,
        $ordenX + 2,
        $topY + $altoCabecera + 2.8,
        $ordenW - 4,
        $filaSuperiorH - $altoCabecera - 5.0,
        $orden,
        calcularTamanoFuenteEtiqueta($orden, 26, 18),
        'C',
        14.5
    );

    $centralY = $topY + $filaSuperiorH + $separacion;
    $colegioW = $topW - $bloqueBultosW - $separacion;

    dibujarCajaEtiqueta($pdf, $topX, $centralY, $colegioW, $filaCentralH, 'COLEGIO', $altoCabecera);
    dibujarValorCentradoEtiqueta(
        $pdf,
        $topX + 3,
        $centralY + $altoCabecera + 2,
        $colegioW - 6,
        $filaCentralH - $altoCabecera - 4,
        $colegio,
        calcularTamanoFuenteEtiqueta($colegio, 24, 14)
    );

    $bultosX = $topX + $colegioW + $separacion;
    dibujarCajaEtiqueta($pdf, $bultosX, $centralY, $bloqueBultosW, $filaCentralH, 'CANTIDAD DE BULTOS', $altoCabecera);
    dibujarValorLineaEtiqueta(
        $pdf,
        $bultosX + 2,
        $centralY + $altoCabecera + 5.4,
        $bloqueBultosW - 4,
        $filaCentralH - $altoCabecera - 10.8,
        $bultos,
        calcularTamanoFuenteEtiqueta($bultos, 30, 20)
    );

    $editorialY = $centralY + $filaCentralH + $separacion;
    dibujarCajaEtiqueta($pdf, $topX, $editorialY, $topW, $filaEditorialH, 'EDITORIAL', $altoCabecera);
    dibujarValorCentradoEtiqueta(
        $pdf,
        $topX + 4,
        $editorialY + $altoCabecera + 2,
        $topW - 8,
        $filaEditorialH - $altoCabecera - 4,
        $editorial,
        calcularTamanoFuenteEtiqueta($editorial, 24.5, 15)
    );

    $inferiorY = $editorialY + $filaEditorialH + $separacion;
    dibujarCajaEtiqueta($pdf, $topX, $inferiorY, $bloqueUbicacionW, $filaInferiorH, 'UBICACION', $altoCabecera);
    dibujarValorLineaEtiqueta(
        $pdf,
        $topX + 3,
        $inferiorY + $altoCabecera + 2.9,
        $bloqueUbicacionW - 6,
        $filaInferiorH - $altoCabecera - 5.2,
        $ubicacion,
        calcularTamanoFuenteEtiqueta($ubicacion, 25, 17),
        'C',
        14.5
    );

    if (is_file($logoPath)) {
        $logoW = 22.0;
        $logoX = $cajaX + $cajaW - $logoW - 6.0;
        $logoY = $cajaY + $cajaH - 18.0;
        $pdf->Image($logoPath, $logoX, $logoY, $logoW, 0, '', '', '', false, 300, '', false, false, 0, false, false, false);
    }

}

function renderizarPaginaEtiquetas($pdf, array $fila): void
{
    $pdf->AddPage();
    dibujarBloqueEtiqueta($pdf, $fila, 0, 0, 210, 148.5, true);
    dibujarBloqueEtiqueta($pdf, $fila, 0, 148.5, 210, 148.5, false);
}

$ids = leerIdsEtiquetasDesdeRequest($_REQUEST);

if ($ids === []) {
    registrarErrorEtiquetas('Peticion de etiquetas invalida.', ['ids' => $ids]);
    mostrarErrorGenericoEtiquetas();
    return;
}

if (!cargarTcpdfLocalEtiquetas()) {
    mostrarErrorGenericoEtiquetas();
    return;
}

try {
    $pdo = conectar();
    $lineas = consultarInventarioPorIds($pdo, $ids, INVENTARIO_ESTADO_ACTIVO);

    if ($lineas === []) {
        throw new RuntimeException('No hay lineas activas disponibles para las etiquetas.');
    }

    $idsEncontrados = array_map(static fn(array $fila): int => (int) ($fila['id'] ?? 0), $lineas);
    $idsNoEncontrados = array_values(array_diff($ids, $idsEncontrados));

    if ($idsNoEncontrados !== []) {
        throw new RuntimeException('La seleccion contiene registros no disponibles.');
    }

    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator('Sistema interno de inventario');
    $pdf->SetAuthor('MAXIMO SERVICIOS LOGISTICOS S.L.U.');
    $pdf->SetTitle('Etiquetas logisticas');
    $pdf->SetSubject('Etiquetas DIN A5 para inventario');
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetMargins(0, 0, 0);
    $pdf->SetAutoPageBreak(false, 0);
    $pdf->SetFont('dejavusans', '', 10);

    foreach ($lineas as $fila) {
        renderizarPaginaEtiquetas($pdf, $fila);
    }

    $pdf->Output(construirNombreArchivoEtiquetas($lineas), 'I');
    exit;
} catch (Throwable $e) {
    registrarErrorEtiquetas('Fallo al generar etiquetas.', ['ids' => $ids], $e);
    mostrarErrorGenericoEtiquetas();
    return;
}

