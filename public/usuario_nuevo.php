<?php

/**
 * Autor: Jorge Monge
 * Trabajo Final de Máster (TFM)
 * UOC - 2026
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/conexion.php';
require_once dirname(__DIR__) . '/app/auth.php';
require_once dirname(__DIR__) . '/app/usuarios.php';

iniciar_sesion();

if (isset($_SESSION['user_id'])) {
    header('Location: ' . urlInicioSegunPermisos());
    exit;
}

$error = '';
$errores = [];
$mensaje = '';
$datos = [
    'username' => '',
    'email' => '',
    'password' => '',
    'password_confirmacion' => '',
    'rol_id' => 0,
];
$roles = [];

try {
    $pdo = conectar();
    $roles = rolesAsignablesUsuarios($pdo);

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $datos = leerFormularioUsuarioDesdeRequest($_POST);
        $resultadoValidacion = validarNuevoUsuarioDetallado($pdo, $datos);
        $errores = is_array($resultadoValidacion['errores'] ?? null) ? $resultadoValidacion['errores'] : [];

        if ($errores === []) {
            crearSolicitudUsuario($pdo, $datos);
            $mensaje = 'Solicitud enviada correctamente. Quedará pendiente de aprobación por almacén.';
        } else {
            $error = 'Corrige los campos indicados antes de enviar la solicitud.';
        }
    }
} catch (Throwable $e) {
    error_log('[USUARIOS] Error registrando solicitud de usuario: ' . $e->getMessage());
    $error = 'No se ha podido registrar la solicitud de acceso. Inténtalo de nuevo en unos minutos.';
    $errores = [];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Solicitar acceso</title>
    <link rel="stylesheet" href="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/css/estilos.css">
</head>
<body class="auth-body">
    <main class="auth-main">
        <section class="auth-card" style="max-width: 560px;">
            <p class="eyebrow">Nueva cuenta</p>
            <h1>Solicitar acceso</h1>
            <p class="subtitulo">Tu cuenta quedará pendiente de aprobación por almacén.</p>

            <?php if ($error !== ''): ?>
                <div class="alert alert-danger" role="alert" aria-live="polite">
                    <p class="mb-2"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
                    <?php if ($errores !== []): ?>
                        <ul class="mb-0 ps-3">
                            <?php foreach ($errores as $mensajeError): ?>
                                <li><?= htmlspecialchars((string) $mensajeError, ENT_QUOTES, 'UTF-8') ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($mensaje !== ''): ?>
                <div class="alert alert-success"><?= htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <form method="POST" action="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/usuario_nuevo.php" class="row g-3" id="solicitud-acceso-form" novalidate>
                <div class="col-12 col-md-6">
                    <label for="username">Usuario</label>
                    <input id="username" name="username" type="text" required value="<?= htmlspecialchars($datos['username'], ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="col-12 col-md-6">
                    <label for="email">Email</label>
                    <input id="email" name="email" type="email" required value="<?= htmlspecialchars($datos['email'], ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="col-12">
                    <label for="rol_id">Rol solicitado</label>
                    <select id="rol_id" name="rol_id" required>
                        <option value="">Selecciona un rol</option>
                        <?php foreach ($roles as $rol): ?>
                            <option value="<?= htmlspecialchars((string) ($rol['id'] ?? 0), ENT_QUOTES, 'UTF-8') ?>"<?= (int) $datos['rol_id'] === (int) ($rol['id'] ?? 0) ? ' selected' : '' ?>>
                                <?= htmlspecialchars(etiquetaRolUsuario(normalizarRolAplicacion((string) ($rol['nombre'] ?? ''))), ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-md-6">
                    <label for="password">Contraseña</label>
                    <input id="password" name="password" type="password" required minlength="8" autocomplete="new-password" aria-describedby="password-help password-feedback">
                </div>
                <div class="col-12 col-md-6">
                    <label for="password_confirmacion">Confirmar contraseña</label>
                    <input id="password_confirmacion" name="password_confirmacion" type="password" required minlength="8" autocomplete="new-password" aria-describedby="password-help password-feedback">
                </div>
                <div class="col-12">
                    <div id="password-help" class="form-text">
                        La contraseña debe tener al menos 8 caracteres y debe coincidir exactamente en ambos campos.
                    </div>
                    <div id="password-feedback" class="alert alert-danger mt-2 mb-0 d-none" role="alert" aria-live="polite"></div>
                </div>
                <div class="col-12 d-flex flex-wrap gap-2">
                    <button class="btn-primary" type="submit">Enviar solicitud</button>
                    <a class="btn btn-outline-secondary" href="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/login.php">Volver al login</a>
                </div>
            </form>
        </section>
    </main>
    <script>
        (() => {
            const form = document.getElementById('solicitud-acceso-form');
            const password = document.getElementById('password');
            const passwordConfirmacion = document.getElementById('password_confirmacion');
            const feedback = document.getElementById('password-feedback');

            if (!form || !password || !passwordConfirmacion || !feedback) {
                return;
            }

            const mostrarError = (mensaje) => {
                feedback.textContent = mensaje;
                feedback.classList.remove('d-none');
            };

            const ocultarError = () => {
                feedback.textContent = '';
                feedback.classList.add('d-none');
            };

            const validarPasswords = () => {
                password.setCustomValidity('');
                passwordConfirmacion.setCustomValidity('');

                if (password.value === '') {
                    password.setCustomValidity('Debes introducir una contraseña.');
                    mostrarError('Debes introducir una contraseña.');
                    return false;
                }

                if (password.value.length < 8) {
                    password.setCustomValidity('La contraseña debe tener al menos 8 caracteres.');
                    mostrarError('La contraseña debe tener al menos 8 caracteres.');
                    return false;
                }

                if (passwordConfirmacion.value === '') {
                    passwordConfirmacion.setCustomValidity('La confirmación de contraseña es obligatoria.');
                    mostrarError('La confirmación de contraseña es obligatoria.');
                    return false;
                }

                if (password.value !== passwordConfirmacion.value) {
                    passwordConfirmacion.setCustomValidity('La contraseña y su confirmación no coinciden.');
                    mostrarError('La contraseña y su confirmación no coinciden.');
                    return false;
                }

                ocultarError();
                return true;
            };

            password.addEventListener('input', validarPasswords);
            passwordConfirmacion.addEventListener('input', validarPasswords);

            form.addEventListener('submit', (event) => {
                const passwordsValidas = validarPasswords();
                const formularioValido = form.checkValidity();

                if (!passwordsValidas || !formularioValido) {
                    event.preventDefault();
                    form.reportValidity();
                }
            });
        })();
    </script>
</body>
</html>
