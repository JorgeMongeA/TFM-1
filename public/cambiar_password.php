<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/conexion.php';
require_once dirname(__DIR__) . '/app/auth.php';
require_once dirname(__DIR__) . '/app/layout.php';

require_login();

$error = '';
$mensaje = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $actual = (string) ($_POST['password_actual'] ?? '');
    $nueva = (string) ($_POST['password_nueva'] ?? '');
    $confirmacion = (string) ($_POST['password_confirmacion'] ?? '');

    if ($actual === '' || $nueva === '' || $confirmacion === '') {
        $error = 'Completa todos los campos.';
    } elseif ($nueva !== $confirmacion) {
        $error = 'La nueva contraseña y su confirmación no coinciden.';
    } elseif (strlen($nueva) < 6) {
        $error = 'La nueva contraseña debe tener al menos 6 caracteres.';
    } else {
        try {
            $pdo = conectar();
            $stmt = $pdo->prepare('SELECT password FROM usuarios WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => (int) ($_SESSION['user_id'] ?? 0)]);
            $hashActual = $stmt->fetchColumn();

            if (!is_string($hashActual) || !password_verify($actual, $hashActual)) {
                $error = 'La contraseña actual no es correcta.';
            } else {
                $stmt = $pdo->prepare('UPDATE usuarios SET password = :password WHERE id = :id');
                $stmt->execute([
                    ':password' => password_hash($nueva, PASSWORD_DEFAULT),
                    ':id' => (int) ($_SESSION['user_id'] ?? 0),
                ]);
                $mensaje = 'Contraseña actualizada correctamente.';
            }
        } catch (Throwable $e) {
            $mensajeError = trim($e->getMessage());
            $error = $mensajeError !== '' ? $mensajeError : 'No se pudo actualizar la contraseña.';
        }
    }
}

renderAppLayoutStart(
    'Cambiar contraseña',
    'dashboard',
    'Cambiar contraseña',
    'Actualización segura de la contraseña del usuario autenticado'
);
?>
<section class="panel panel-card">
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <?php if ($error !== ''): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
            <?php if ($mensaje !== ''): ?>
                <div class="alert alert-success"><?= htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <form method="POST" action="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/cambiar_password.php" class="row g-3">
                <div class="col-12 col-md-6">
                    <label class="form-label" for="password_actual">Contraseña actual</label>
                    <input class="form-control" id="password_actual" name="password_actual" type="password" required>
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label" for="password_nueva">Nueva contraseña</label>
                    <input class="form-control" id="password_nueva" name="password_nueva" type="password" required>
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label" for="password_confirmacion">Confirmar nueva contraseña</label>
                    <input class="form-control" id="password_confirmacion" name="password_confirmacion" type="password" required>
                </div>
                <div class="col-12 d-flex flex-wrap gap-2">
                    <button class="btn btn-primary mt-0" type="submit">Actualizar contraseña</button>
                    <a class="btn btn-outline-secondary" href="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/dashboard.php">Volver</a>
                </div>
            </form>
        </div>
    </div>
</section>
<?php renderAppLayoutEnd(); ?>
