<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/auth.php';
require_once dirname(__DIR__) . '/app/layout.php';

require_login();

$id = trim((string) ($_GET['id'] ?? ''));

renderAppLayoutStart(
    'Etiqueta PDF',
    'entrada',
    'Etiqueta PDF',
    'Placeholder preparado para la futura generación de etiquetas'
);
?>
<section class="panel panel-card">
    <div class="alert alert-info mb-0">
        La generación real de la etiqueta PDF se implementará en una siguiente iteración.
        <?php if ($id !== ''): ?>
            Referencia de inventario: <strong><?= htmlspecialchars($id, ENT_QUOTES, 'UTF-8') ?></strong>.
        <?php endif; ?>
    </div>
</section>
<?php renderAppLayoutEnd(); ?>
