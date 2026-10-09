<?php
/**
 * Paginación reutilizable (Bootstrap 5.3).
 * Variables requeridas en el scope del include:
 *  - $page (int)       → página actual
 *  - $totalPages (int) → total de páginas
 *  - $baseUrl (string) → ruta base sin query, ej: BASE_URL . '/ctacte'
 * Los filtros actuales se preservan automáticamente desde $_GET (sin 'page' ni 'url').
 */
if ((int)($totalPages ?? 1) <= 1) return;

$page       = (int)$page;
$totalPages = (int)$totalPages;

$queryParams = $_GET;
unset($queryParams['page'], $queryParams['url']);

$buildUrl = function (int $p) use ($baseUrl, $queryParams): string {
    $qs = http_build_query(array_merge($queryParams, ['page' => $p]));
    return $baseUrl . '?' . $qs;
};

$window = 2;
$start  = max(1, $page - $window);
$end    = min($totalPages, $page + $window);
?>
<nav aria-label="Paginación" class="d-flex justify-content-end">
    <ul class="pagination pagination-sm mb-0">
        <?php if ($page > 1): ?>
        <li class="page-item">
            <a class="page-link" href="<?= htmlspecialchars($buildUrl(1)) ?>" title="Primera">&laquo;</a>
        </li>
        <li class="page-item">
            <a class="page-link" href="<?= htmlspecialchars($buildUrl($page - 1)) ?>" title="Anterior">&lsaquo;</a>
        </li>
        <?php endif; ?>

        <?php if ($start > 1): ?>
        <li class="page-item disabled"><span class="page-link">&hellip;</span></li>
        <?php endif; ?>

        <?php for ($i = $start; $i <= $end; $i++): ?>
        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
            <?php if ($i === $page): ?>
            <span class="page-link"><?= $i ?></span>
            <?php else: ?>
            <a class="page-link" href="<?= htmlspecialchars($buildUrl($i)) ?>"><?= $i ?></a>
            <?php endif; ?>
        </li>
        <?php endfor; ?>

        <?php if ($end < $totalPages): ?>
        <li class="page-item disabled"><span class="page-link">&hellip;</span></li>
        <?php endif; ?>

        <?php if ($page < $totalPages): ?>
        <li class="page-item">
            <a class="page-link" href="<?= htmlspecialchars($buildUrl($page + 1)) ?>" title="Siguiente">&rsaquo;</a>
        </li>
        <li class="page-item">
            <a class="page-link" href="<?= htmlspecialchars($buildUrl($totalPages)) ?>" title="Última">&raquo;</a>
        </li>
        <?php endif; ?>
    </ul>
</nav>
