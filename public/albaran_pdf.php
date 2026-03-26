<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/auth.php';
require_once dirname(__DIR__) . '/app/layout.php';

require_login();

$tipo = trim((string) ($_GET['tipo'] ?? ''));

renderAppLayoutStart(
    'Albarán PDF',
    'albaran',
    'Albarán PDF',
    'Placeholder preparado para la futura generación del PDF'
);
?>
<section class="panel panel-card">
    <div class="alert alert-info mb-0">
        La generación real del albarán PDF todavía no está implementada.
        <?php if ($tipo !== ''): ?>
            Contexto actual: <strong><?= htmlspecialchars($tipo, ENT_QUOTES, 'UTF-8') ?></strong>.
        <?php endif; ?>
    </div>
</section>
<?php renderAppLayoutEnd(); ?>
