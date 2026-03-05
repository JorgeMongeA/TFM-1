<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Core/helpers.php';

class AuthMiddleware
{
    public static function check(): void
    {
        if (!isset($_SESSION['user'])) {
            header('Location: ' . base_url('/login'));
            exit;
        }
    }
}
