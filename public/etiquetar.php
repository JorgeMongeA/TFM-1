<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/auth.php';
require_once dirname(__DIR__) . '/app/layout.php';

require_login();

renderAppLayoutStart(
    'Inventario - Etiquetar',
    'etiquetar',
    'Etiquetar mercancía',
    'Pantalla preparada para seleccionar mercancía y generar etiquetas en la siguiente iteración'
);
?>
<section class="panel panel-card">
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <p class="eyebrow">Preparación</p>
            <div class="row g-3">
                <div class="col-12 col-lg-6">
                    <label class="form-label" for="busqueda_mercancia">Buscar mercancía</label>
                    <input class="form-control" id="busqueda_mercancia" type="text" placeholder="Editorial, centro, código o referencia">
                </div>
                <div class="col-12 col-lg-6">
                    <label class="form-label" for="seleccion_etiquetado">Selección preparada</label>
                    <input class="form-control" id="seleccion_etiquetado" type="text" placeholder="Bloque base para la selección futura">
                </div>
                <div class="col-12">
                    <div class="alert alert-light border mb-0">
                        Esta sección queda preparada para incorporar la selección de mercancía y la generación real de etiquetas PDF.
                    </div>
                </div>
                <div class="col-12">
                    <a class="btn btn-outline-primary" href="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/etiqueta.php">Generar etiqueta PDF</a>
                </div>
            </div>
        </div>
    </div>
</section>
<?php renderAppLayoutEnd(); ?>
