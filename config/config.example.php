<?php

/**
 * Archivo de ejemplo de configuracion.
 * No incluye credenciales reales.
 */

declare(strict_types=1);

return [
    'base_url' => '/CON/public',
    'db_host' => 'localhost',
    'db_name' => 'tfm_inventario',
    'db_user' => 'root',
    'db_pass' => '',
    'db_charset' => 'utf8mb4',

    // Integraciones opcionales.
    'inventario_csv_url' => '',
    'centros_csv_url' => '',
    'google_inventory_sync_url' => 'https://script.google.com/macros/s/AKfycbxJXA80jesoay9I0-covN_3co18Gndn2Ck7Q0vbtNt8gWZg42IHW5u2Uu7n6k6b8INR/exec',

    // Avisos por email de pedidos.
    'pedido_email_enabled' => false,
    'pedido_email_to' => 'almacen@maximosl.com',
    'pedido_email_from' => '',

    // Avisos por email de solicitudes de usuario.
    'user_request_email_enabled' => false,
    'user_request_email_to' => 'almacen@maximosl.com',
    'user_request_email_from' => '',

    // Avisos por email de recuperacion de contrasena.
    'password_reset_email_enabled' => false,
    'password_reset_email_from' => '',
];
