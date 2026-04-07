<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/auth.php';
require_once dirname(__DIR__) . '/app/conexion.php';

require_login();
requierePermiso(PERMISO_ETIQUETAS);

$id = (int) ($_GET['id'] ?? 0);

if ($id > 0) {
    header('Location: ' . BASE_URL . '/etiquetar_pdf.php?id=' . rawurlencode((string) $id));
    exit;
}

header('Location: ' . BASE_URL . '/etiquetar.php');
exit;
