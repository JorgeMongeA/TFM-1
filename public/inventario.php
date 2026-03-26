<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/auth.php';

require_login();

header('Location: ' . BASE_URL . '/inventario_consulta.php');
exit;
