<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/auth.php';
require_once dirname(__DIR__) . '/app/layout.php';

require_login();

renderAppLayoutStart(
    'Inventario - Albarán',
    'albaran',
    'Inventario - Albarán',
    'Pantalla base preparada para la siguiente iteración'
);
?>
<section class="panel panel-card">
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <p class="eyebrow">Preparación</p>
            <h2 class="section-title">Generación de albarán</h2>
            <p class="texto mb-4">Esta sección queda preparada para incorporar la lógica y el contenido definitivo del albarán en la siguiente fase.</p>
            <form class="row g-3">
                <div class="col-12 col-md-6">
                    <label class="form-label" for="referencia">Referencia</label>
                    <input class="form-control" id="referencia" type="text" placeholder="Referencia o número de operación">
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label" for="observaciones">Observaciones</label>
                    <input class="form-control" id="observaciones" type="text" placeholder="Campo preparado para ampliación futura">
                </div>
                <div class="col-12">
                    <a class="btn btn-outline-primary" href="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/albaran_pdf.php?tipo=manual">Generar albarán PDF</a>
                </div>
            </form>
        </div>
    </div>
</section>
<?php renderAppLayoutEnd(); ?>
