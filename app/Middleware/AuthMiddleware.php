<?php

declare(strict_types=1);

class AuthMiddleware
{
    public static function check(): void
    {
        if (!isset($_SESSION['user'])) {
            header('Location: /login');
            exit;
        }
    }
}
