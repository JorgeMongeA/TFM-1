<?php

/**
 * Autor: Jorge Monge
 * Trabajo Final de Máster (TFM)
 * UOC - 2026
 */

declare(strict_types=1);

const PAGINACION_TAMANOS_PERMITIDOS = [25, 50, 100];
const PAGINACION_POR_PAGINA_POR_DEFECTO = 25;

function leerPaginacionDesdeRequest(array $source, int $porPaginaPorDefecto = PAGINACION_POR_PAGINA_POR_DEFECTO): array
{
    $pagina = filter_var($source['page'] ?? 1, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1],
    ]);
    $porPagina = filter_var($source['per_page'] ?? $porPaginaPorDefecto, FILTER_VALIDATE_INT);

    if ($pagina === false) {
        $pagina = 1;
    }

    if ($porPagina === false || !in_array($porPagina, PAGINACION_TAMANOS_PERMITIDOS, true)) {
        $porPagina = PAGINACION_POR_PAGINA_POR_DEFECTO;
    }

    return [
        'page' => $pagina,
        'per_page' => $porPagina,
        'offset' => ($pagina - 1) * $porPagina,
    ];
}

function construirPaginacion(int $totalRegistros, int $paginaSolicitada, int $porPagina): array
{
    $total = max(0, $totalRegistros);
    $porPaginaNormalizada = in_array($porPagina, PAGINACION_TAMANOS_PERMITIDOS, true)
        ? $porPagina
        : PAGINACION_POR_PAGINA_POR_DEFECTO;
    $totalPaginas = max(1, (int) ceil($total / $porPaginaNormalizada));
    $pagina = max(1, min($paginaSolicitada, $totalPaginas));
    $offset = ($pagina - 1) * $porPaginaNormalizada;
    $desde = $total > 0 ? $offset + 1 : 0;
    $hasta = $total > 0 ? min($total, $offset + $porPaginaNormalizada) : 0;

    return [
        'page' => $pagina,
        'per_page' => $porPaginaNormalizada,
        'offset' => $offset,
        'total' => $total,
        'total_pages' => $totalPaginas,
        'from' => $desde,
        'to' => $hasta,
        'has_previous' => $pagina > 1,
        'has_next' => $pagina < $totalPaginas,
        'previous_page' => $pagina > 1 ? $pagina - 1 : 1,
        'next_page' => $pagina < $totalPaginas ? $pagina + 1 : $totalPaginas,
    ];
}

function renderCamposOcultosPaginacion(array $params, string $prefijo = ''): void
{
    foreach ($params as $clave => $valor) {
        $nombre = $prefijo === '' ? (string) $clave : $prefijo . '[' . $clave . ']';

        if (is_array($valor)) {
            renderCamposOcultosPaginacion($valor, $nombre);
            continue;
        }

        if ($valor === null) {
            continue;
        }

        ?>
        <input type="hidden" name="<?= htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8') ?>" value="<?= htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8') ?>">
        <?php
    }
}

function renderPaginacionListado(string $baseUrl, array $paginacion, array $queryActual = []): void
{
    $total = (int) ($paginacion['total'] ?? 0);
    if ($total <= 0) {
        return;
    }

    $queryBase = $queryActual;
    unset($queryBase['page'], $queryBase['per_page']);

    $pagina = (int) ($paginacion['page'] ?? 1);
    $porPagina = (int) ($paginacion['per_page'] ?? PAGINACION_POR_PAGINA_POR_DEFECTO);
    $totalPaginas = (int) ($paginacion['total_pages'] ?? 1);
    $primeraUrl = $baseUrl . '?' . http_build_query(array_merge($queryBase, ['page' => 1, 'per_page' => $porPagina]));
    $anteriorUrl = $baseUrl . '?' . http_build_query(array_merge($queryBase, ['page' => max(1, $pagina - 1), 'per_page' => $porPagina]));
    $siguienteUrl = $baseUrl . '?' . http_build_query(array_merge($queryBase, ['page' => min($totalPaginas, $pagina + 1), 'per_page' => $porPagina]));
    $ultimaUrl = $baseUrl . '?' . http_build_query(array_merge($queryBase, ['page' => $totalPaginas, 'per_page' => $porPagina]));
    ?>
    <div class="table-pagination">
        <div class="table-pagination-summary">
            Mostrando <?= htmlspecialchars((string) ($paginacion['from'] ?? 0), ENT_QUOTES, 'UTF-8') ?>-<?= htmlspecialchars((string) ($paginacion['to'] ?? 0), ENT_QUOTES, 'UTF-8') ?> de <?= htmlspecialchars((string) $total, ENT_QUOTES, 'UTF-8') ?> registros
        </div>
        <div class="table-pagination-controls">
            <form method="GET" action="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>" class="table-pagination-form">
                <?php renderCamposOcultosPaginacion($queryBase); ?>
                <input type="hidden" name="page" value="1">
                <label class="table-pagination-label" for="per-page-<?= htmlspecialchars(md5($baseUrl . serialize($queryBase)), ENT_QUOTES, 'UTF-8') ?>">Registros por pagina</label>
                <select
                    class="form-select form-select-sm table-pagination-select"
                    id="per-page-<?= htmlspecialchars(md5($baseUrl . serialize($queryBase)), ENT_QUOTES, 'UTF-8') ?>"
                    name="per_page"
                    onchange="this.form.submit()"
                >
                    <?php foreach (PAGINACION_TAMANOS_PERMITIDOS as $tamano): ?>
                        <option value="<?= htmlspecialchars((string) $tamano, ENT_QUOTES, 'UTF-8') ?>"<?= $porPagina === $tamano ? ' selected' : '' ?>>
                            <?= htmlspecialchars((string) $tamano, ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
            <div class="table-pagination-nav">
                <?php if ((bool) ($paginacion['has_previous'] ?? false)): ?>
                    <a class="btn btn-sm btn-outline-secondary" href="<?= htmlspecialchars($primeraUrl, ENT_QUOTES, 'UTF-8') ?>">Primera</a>
                    <a class="btn btn-sm btn-outline-secondary" href="<?= htmlspecialchars($anteriorUrl, ENT_QUOTES, 'UTF-8') ?>">Anterior</a>
                <?php else: ?>
                    <span class="btn btn-sm btn-outline-secondary disabled">Primera</span>
                    <span class="btn btn-sm btn-outline-secondary disabled">Anterior</span>
                <?php endif; ?>
                <span class="table-pagination-page">Pagina <?= htmlspecialchars((string) $pagina, ENT_QUOTES, 'UTF-8') ?> de <?= htmlspecialchars((string) $totalPaginas, ENT_QUOTES, 'UTF-8') ?></span>
                <?php if ((bool) ($paginacion['has_next'] ?? false)): ?>
                    <a class="btn btn-sm btn-outline-secondary" href="<?= htmlspecialchars($siguienteUrl, ENT_QUOTES, 'UTF-8') ?>">Siguiente</a>
                    <a class="btn btn-sm btn-outline-secondary" href="<?= htmlspecialchars($ultimaUrl, ENT_QUOTES, 'UTF-8') ?>">Ultima</a>
                <?php else: ?>
                    <span class="btn btn-sm btn-outline-secondary disabled">Siguiente</span>
                    <span class="btn btn-sm btn-outline-secondary disabled">Ultima</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
}
