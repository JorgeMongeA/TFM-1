<?php

declare(strict_types=1);

return [
    'base_url' => '/CON/public',
    'db_host' => 'localhost',
    'db_name' => 'tfm_inventario',
    'db_user' => 'root',
    'db_pass' => '',
    'db_charset' => 'utf8mb4',

    // URLs opcionales usadas por los modulos de sincronizacion.
    'inventario_csv_url' => '',
    'centros_csv_url' => '',

    // Email opcional para avisar a almacen cuando Edelvives cree un pedido.
    // Solo se usa si el hosting soporta mail() y se activa explicitamente.
    'pedido_email_enabled' => false,
    'pedido_email_to' => 'almacen@maximosl.com',
    'pedido_email_from' => '',
];
