<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';

function leerConfirmacionFuerteDesdeRequest(array $source): array
{
    return [
        'confirmado' => (string) ($source['confirmacion_check'] ?? '') === '1',
        'password_actual' => (string) ($source['password_actual'] ?? ''),
        'frase' => trim((string) ($source['frase_confirmacion'] ?? '')),
    ];
}

function validarConfirmacionFuerte(PDO $pdo, int $usuarioId, array $datos, array $opciones = []): void
{
    if ($usuarioId <= 0) {
        throw new RuntimeException('No se ha podido validar la identidad del usuario autenticado.');
    }

    $requiereCheckbox = ($opciones['requiere_checkbox'] ?? true) === true;
    $requierePassword = ($opciones['requiere_password'] ?? true) === true;
    $fraseEsperada = trim((string) ($opciones['frase_esperada'] ?? ''));

    if ($requiereCheckbox && ($datos['confirmado'] ?? false) !== true) {
        throw new RuntimeException('Debes confirmar expresamente la accion antes de continuar.');
    }

    if ($fraseEsperada !== '' && !hash_equals($fraseEsperada, trim((string) ($datos['frase'] ?? '')))) {
        throw new RuntimeException('La frase de confirmacion no coincide.');
    }

    if ($requierePassword && !passwordActualUsuarioValida($pdo, $usuarioId, (string) ($datos['password_actual'] ?? ''))) {
        throw new RuntimeException('La contraseña actual no es correcta.');
    }
}

function passwordActualUsuarioValida(PDO $pdo, int $usuarioId, string $passwordActual): bool
{
    if ($usuarioId <= 0 || trim($passwordActual) === '') {
        return false;
    }

    $stmt = $pdo->prepare('SELECT password FROM usuarios WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $usuarioId]);
    $hash = $stmt->fetchColumn();

    return is_string($hash) && $hash !== '' && validarPasswordLogin($passwordActual, $hash);
}
