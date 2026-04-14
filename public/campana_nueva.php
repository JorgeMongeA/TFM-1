<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/conexion.php';
require_once dirname(__DIR__) . '/app/auth.php';
require_once dirname(__DIR__) . '/app/layout.php';
require_once dirname(__DIR__) . '/app/actividad.php';
require_once dirname(__DIR__) . '/app/confirmacion_fuerte.php';

require_login();
requierePermiso(PERMISO_CAMPANAS, 'No tienes permisos para preparar una nueva campana.');

$mensaje = '';
$error = '';
$datosConfirmacion = [
    'confirmado' => false,
    'password_actual' => '',
    'frase' => '',
];

try {
    $pdo = conectar();

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $datosConfirmacion = leerConfirmacionFuerteDesdeRequest($_POST);
        validarConfirmacionFuerte($pdo, (int) ($_SESSION['user_id'] ?? 0), $datosConfirmacion, [
            'requiere_checkbox' => true,
            'requiere_password' => true,
        ]);

        registrarActividadSistema($pdo, [
            'usuario_id' => (int) ($_SESSION['user_id'] ?? 0),
            'usuario' => (string) ($_SESSION['username'] ?? $_SESSION['usuario'] ?? ''),
            'tipo_evento' => 'campana_confirmacion_validada',
            'entidad' => 'campana',
            'descripcion' => 'Validacion de confirmacion fuerte para iniciar nueva campana.',
            'metadata' => [
                'estado' => 'placeholder',
                'accion_ejecutada' => false,
            ],
        ]);

        $mensaje = 'La confirmacion fuerte se ha validado correctamente. La logica de inicio de campana sigue deshabilitada hasta su implementacion definitiva.';
        $datosConfirmacion = [
            'confirmado' => false,
            'password_actual' => '',
            'frase' => '',
        ];
    }
} catch (Throwable $e) {
    $mensajeError = trim($e->getMessage());
    $error = $mensajeError !== '' ? $mensajeError : 'No se ha podido validar la confirmacion fuerte.';
}

renderAppLayoutStart(
    'Preparar nueva campana',
    'dashboard',
    'Iniciar nueva campana',
    'Base preparada para una accion critica con confirmacion fuerte y trazabilidad'
);
?>
<section class="panel panel-card">
    <?php if ($mensaje !== ''): ?>
        <div class="alert alert-success"><?= htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <div class="row g-3">
        <div class="col-12 col-xl-7">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <p class="eyebrow">Estado actual</p>
                    <h2 class="section-title">Operacion preparada, no activada</h2>
                    <p class="texto">
                        Esta pantalla no inicia todavia ninguna campana. Deja definido el patron de seguridad para una futura accion de alto impacto.
                    </p>
                    <div class="alert alert-warning mb-0">
                        Antes de habilitar la ejecucion real, la accion exigira confirmacion explicita y reintroduccion de la contrasena del usuario autenticado.
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-5">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <p class="eyebrow">Patron elegido</p>
                    <h2 class="section-title">Modal + checkbox + contrasena</h2>
                    <p class="texto mb-3">
                        Es la mejor combinacion para esta accion: confirma intencion, verifica identidad y deja una base defendible en el TFM sin deteriorar usabilidad.
                    </p>
                    <button class="btn btn-danger" type="button" data-bs-toggle="modal" data-bs-target="#modalIniciarCampana">
                        Iniciar nueva campana
                    </button>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <p class="eyebrow">Criterio tecnico</p>
                    <div class="row g-3">
                        <div class="col-12 col-lg-4">
                            <div class="border rounded-3 p-3 h-100">
                                <h3 class="h6 mb-2">Seguridad</h3>
                                <p class="mb-0 text-body-secondary">La contrasena actual confirma que la persona autenticada sigue siendo quien ejecuta la accion critica.</p>
                            </div>
                        </div>
                        <div class="col-12 col-lg-4">
                            <div class="border rounded-3 p-3 h-100">
                                <h3 class="h6 mb-2">Usabilidad</h3>
                                <p class="mb-0 text-body-secondary">El modal concentra el riesgo, explica consecuencias y evita disparos accidentales sin convertir el flujo en una operacion pesada.</p>
                            </div>
                        </div>
                        <div class="col-12 col-lg-4">
                            <div class="border rounded-3 p-3 h-100">
                                <h3 class="h6 mb-2">TFM</h3>
                                <p class="mb-0 text-body-secondary">El patron es claro de justificar: confirmacion expresa, validacion de identidad y trazabilidad antes de una accion sensible.</p>
                            </div>
                        </div>
                    </div>
                    <p class="mt-3 mb-0 text-body-secondary">
                        La confirmacion escribiendo una frase queda disponible en el helper para escenarios todavia mas irreversibles, pero no es necesaria por defecto en este caso.
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>

<div class="modal fade" id="modalIniciarCampana" tabindex="-1" aria-labelledby="modalIniciarCampanaLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/campana_nueva.php">
                <div class="modal-header">
                    <h2 class="modal-title fs-5" id="modalIniciarCampanaLabel">Confirmacion fuerte</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-3">
                        Esta accion abrira el flujo de nueva campana cuando la logica definitiva quede habilitada. Por ahora solo valida el patron de seguridad.
                    </p>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" value="1" id="confirmacion_check" name="confirmacion_check"<?= ($datosConfirmacion['confirmado'] ?? false) ? ' checked' : '' ?>>
                        <label class="form-check-label" for="confirmacion_check">
                            Entiendo que esta sera una accion operativa sensible y que requerira trazabilidad.
                        </label>
                    </div>
                    <div>
                        <label class="form-label" for="password_actual">Contrasena actual</label>
                        <input class="form-control" id="password_actual" name="password_actual" type="password" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger">Validar confirmacion</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if ($error !== ''): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var modal = new bootstrap.Modal(document.getElementById('modalIniciarCampana'));
            modal.show();
        });
    </script>
<?php endif; ?>
<?php renderAppLayoutEnd(); ?>
