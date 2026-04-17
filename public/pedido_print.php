<?php

/**
 * Autor: Jorge Monge
 * Trabajo Final de Máster (TFM)
 * UOC - 2026
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/conexion.php';
require_once dirname(__DIR__) . '/app/auth.php';
require_once dirname(__DIR__) . '/app/pedidos.php';

require_login();
requierePermiso(PERMISO_PEDIDOS, 'No tienes permisos para imprimir este pedido.');

$pedidoId = (int) ($_GET['id'] ?? 0);
$usuarioPedido = obtenerUsuarioPedidoActual();
$pedido = null;
$lineasPedido = [];
$error = '';

try {
    $pdo = conectar();
    $pedido = consultarPedidoPorId($pdo, $pedidoId);

    if ($pedido === null) {
        throw new RuntimeException('El pedido solicitado no existe.');
    }

    if (!usuarioPuedeVerPedido($pedido, $usuarioPedido)) {
        renderizarAccesoDenegado('No tienes permisos para imprimir este pedido.');
    }

    $lineasPedido = consultarLineasPedido($pdo, $pedidoId);
} catch (Throwable $e) {
    $mensajeError = trim($e->getMessage());
    $error = $mensajeError !== '' ? $mensajeError : 'No se ha podido preparar la impresion del pedido.';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pedido !== null ? 'Impresion ' . (string) ($pedido['codigo_pedido'] ?? '') : 'Impresion pedido', ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <style>
        body { background: #f6f8fb; }
        .print-shell { max-width: 1100px; margin: 24px auto; background: #fff; padding: 32px; border-radius: 16px; }
        @media print {
            body { background: #fff; }
            .print-actions { display: none !important; }
            .print-shell { margin: 0; max-width: none; border-radius: 0; padding: 0; }
        }
    </style>
</head>
<body>
    <main class="print-shell shadow-sm">
        <div class="print-actions d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0">Hoja de preparacion de pedido</h1>
            <div class="d-flex gap-2">
                <button class="btn btn-primary" type="button" onclick="window.print()">Imprimir</button>
                <button class="btn btn-outline-secondary" type="button" onclick="window.close()">Cerrar</button>
            </div>
        </div>

        <?php if ($error !== ''): ?>
            <div class="alert alert-danger mb-0"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php elseif ($pedido !== null): ?>
            <div class="mb-4">
                <p class="text-uppercase text-secondary small fw-bold mb-1">Pedido interno</p>
                <h2 class="h4 mb-2"><?= htmlspecialchars((string) ($pedido['codigo_pedido'] ?? ''), ENT_QUOTES, 'UTF-8') ?></h2>
                <div class="row g-3">
                    <div class="col-md-4"><strong>Solicitante:</strong> <?= htmlspecialchars((string) ($pedido['usuario_creacion'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></div>
                    <div class="col-md-4"><strong>Fecha:</strong> <?= htmlspecialchars((string) ($pedido['fecha_creacion'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></div>
                    <div class="col-md-4"><strong>Estado:</strong> <?= htmlspecialchars(etiquetaEstadoPedido((string) ($pedido['estado'] ?? '')), ENT_QUOTES, 'UTF-8') ?></div>
                    <div class="col-md-4"><strong>Total lineas:</strong> <?= htmlspecialchars((string) ($pedido['total_lineas'] ?? 0), ENT_QUOTES, 'UTF-8') ?></div>
                    <div class="col-md-4"><strong>Total bultos:</strong> <?= htmlspecialchars((string) ($pedido['total_bultos'] ?? 0), ENT_QUOTES, 'UTF-8') ?></div>
                    <div class="col-md-4"><strong>Ultima gestion:</strong> <?= htmlspecialchars((string) (($pedido['fecha_ultima_gestion'] ?? '') !== '' ? $pedido['fecha_ultima_gestion'] : '-'), ENT_QUOTES, 'UTF-8') ?></div>
                </div>
            </div>

            <?php if (((string) ($pedido['observaciones'] ?? '')) !== ''): ?>
                <div class="border rounded-3 p-3 bg-light mb-4">
                    <strong>Observaciones:</strong>
                    <div><?= nl2br(htmlspecialchars((string) $pedido['observaciones'], ENT_QUOTES, 'UTF-8')) ?></div>
                </div>
            <?php endif; ?>

            <div class="table-responsive">
                <table class="table table-bordered align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ID inventario</th>
                            <th>Editorial</th>
                            <th>Colegio</th>
                            <th>Codigo centro</th>
                            <th>Ubicacion</th>
                            <th>Fecha entrada</th>
                            <th>Bultos</th>
                            <th>Destino</th>
                            <th>Orden</th>
                            <th style="width: 120px;">Preparado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lineasPedido as $linea): ?>
                            <tr>
                                <td><?= htmlspecialchars((string) ($linea['inventario_id'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string) (($linea['editorial'] ?? '') !== '' ? $linea['editorial'] : '-'), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string) (($linea['colegio'] ?? '') !== '' ? $linea['colegio'] : '-'), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string) (($linea['codigo_centro'] ?? '') !== '' ? $linea['codigo_centro'] : '-'), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string) (($linea['ubicacion'] ?? '') !== '' ? $linea['ubicacion'] : '-'), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string) (($linea['fecha_entrada'] ?? '') !== '' ? $linea['fecha_entrada'] : '-'), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string) ($linea['bultos'] ?? 0), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string) (($linea['destino'] ?? '') !== '' ? $linea['destino'] : '-'), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string) (($linea['orden'] ?? '') !== '' ? $linea['orden'] : '-'), ENT_QUOTES, 'UTF-8') ?></td>
                                <td></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>
