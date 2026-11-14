<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">
        Materias Primas
        <?php if (!empty($inactivos)): ?>
            <span class="badge bg-secondary fs-6">INACTIVAS</span>
        <?php endif; ?>
    </h3>
    <div>
        <a href="<?= BASE_URL ?>/materiasprimas/create" class="btn btn-primary">
            <i class="bi bi-plus-lg"></i> Nueva Materia Prima
        </a>
        <a href="<?= BASE_URL ?>/materiasprimas/import" class="btn btn-success">
            <i class="bi bi-upload"></i> Importar
        </a>
        <?php if (!empty($inactivos)): ?>
            <a href="<?= BASE_URL ?>/materiasprimas" class="btn btn-outline-primary">
                <i class="bi bi-arrow-left"></i> Ver activas
            </a>
        <?php else: ?>
            <a href="<?= BASE_URL ?>/materiasprimas?estado=inactivos" class="btn btn-outline-secondary">
                <i class="bi bi-archive"></i> Inactivas
                <span class="badge bg-secondary"><?= (int)($cantInactivas ?? 0) ?></span>
            </a>
        <?php endif; ?>
    </div>
</div>

<?php
$buscar = $buscar ?? '';
$inactivos = $inactivos ?? false;
$csrf = $csrf ?? Csrf::generate();
$urlFiltro = $inactivos ? '?estado=inactivos' : '';
$badgesTipo = [
    'UNIDAD'     => 'bg-secondary',
    'LINEAL'     => 'bg-info text-dark',
    'SUPERFICIE' => 'bg-warning text-dark',
    'GRANEL'     => 'bg-success',
];
?>

<!-- Buscador: nombre, SKU, descripción/categoría, precio y código de barra -->
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="get" action="<?= BASE_URL ?>/materiasprimas" class="row g-2 align-items-end">
            <?php if ($inactivos): ?>
                <input type="hidden" name="estado" value="inactivos">
            <?php endif; ?>
            <div class="col">
                <label class="form-label mb-0 small">Buscar</label>
                <input type="text" name="q" class="form-control form-control-sm"
                       placeholder="Nombre, SKU, descripción/categoría, precio o código de barra..."
                       value="<?= htmlspecialchars($buscar) ?>">
            </div>
            <div class="col-auto">
                <button class="btn btn-primary btn-sm"><i class="bi bi-search"></i> Buscar</button>
                <?php if ($buscar !== ''): ?>
                    <a href="<?= BASE_URL ?>/materiasprimas<?= $urlFiltro ?>" class="btn btn-secondary btn-sm">Limpiar</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<?php if ($buscar !== ''): ?>
    <p class="text-muted small"><?= count($items) ?> resultado(s) para "<?= htmlspecialchars($buscar) ?>".</p>
<?php endif; ?>

<div class="table-responsive table-scroll mt-3">
    <table class="table table-striped table-hover">
        <thead class="table-dark">
            <tr>
                <th>Imagen</th>
                <th>Nombre</th>
                <th>SKU</th>
                <th>Tipo de gestión</th>
                <th>Categoría</th>
                <th>Unidad</th>
                <th>Compra</th>
                <th class="text-end">Stock</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($items)): ?>
                <tr><td colspan="9" class="text-center text-muted">
                    <?= $inactivos ? 'No hay materias primas inactivas' : 'No hay materias primas activas' ?><?= $buscar !== '' ? ' que coincidan con la búsqueda' : '' ?>.
                </td></tr>
            <?php endif; ?>
            <?php foreach ($items as $p): ?>
            <tr>
                <td>
                    <?php
                    $imgPath = $p['imagen'] ?? null;
                    $imgUrl = $imgPath
                        ? empresaUploadUrl('materiasprimas') . '/' . htmlspecialchars($imgPath)
                        : empresaUploadUrl('materiasprimas') . '/sin-imagen.jpg';
                    ?>
                    <img src="<?= $imgUrl ?>" width="50" height="50" style="object-fit: cover;" alt="img">
                </td>
                <td><?= htmlspecialchars($p['nombre']) ?></td>
                <td><?= htmlspecialchars($p['sku']) ?></td>
                <td>
                    <?php
                    $tipo = $p['tipo_gestion'] ?? 'UNIDAD';
                    $clase = $badgesTipo[$tipo] ?? 'bg-secondary';
                    ?>
                    <span class="badge <?= $clase ?>"><?= htmlspecialchars($tiposGestion[$tipo] ?? $tipo) ?></span>
                </td>
                <td><?= htmlspecialchars($p['categoria_nombre'] ?? '—') ?></td>
                <td>
                    <?= htmlspecialchars($p['nombre_unidad'] ?? '—') ?>
                    <?php if (!empty($p['presentacion'])): ?>
                        <br><small class="text-muted">Presentación: <?= number_format((float)$p['presentacion'], 2, ',', '.') ?> <?= htmlspecialchars($p['nombre_unidad'] ?? '') ?></small>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if (!empty($p['id_unidadcompra']) && (int)$p['id_unidadcompra'] !== (int)$p['id_unidadmedida']): ?>
                        <?= htmlspecialchars($p['nombre_unidad_compra'] ?? '') ?>
                        <br><small class="text-muted">1 = <?= number_format((float)$p['factor_conversion'], 4, ',', '.') ?> <?= htmlspecialchars($p['nombre_unidad'] ?? '') ?></small>
                    <?php else: ?>
                        <span class="text-muted">—</span>
                    <?php endif; ?>
                </td>
                <td class="text-end"><?= number_format((float)($p['stock'] ?? 0), 3, ',', '.') ?></td>
                <td class="text-nowrap">
                    <a href="<?= BASE_URL ?>/materiasprimas/show/<?= (int)$p['id'] ?>" class="btn btn-sm btn-info" title="Ver"><i class="bi bi-eye"></i></a>
                    <a href="<?= BASE_URL ?>/materiasprimas/update/<?= (int)$p['id'] ?>" class="btn btn-sm btn-warning" title="Editar"><i class="bi bi-pencil-square"></i></a>
                    <?php if ($inactivos): ?>
                        <form method="post" action="<?= BASE_URL ?>/materiasprimas/reactivar/<?= (int)$p['id'] ?>"
                              class="d-inline js-form-reactivar"
                              data-nombre="<?= htmlspecialchars($p['nombre']) ?>">
                            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                            <button type="submit" class="btn btn-sm btn-success" title="Reactivar">
                                <i class="bi bi-arrow-repeat"></i> Reactivar
                            </button>
                        </form>
                    <?php else: ?>
                        <a href="<?= BASE_URL ?>/materiasprimas/updatebarcode/<?= (int)$p['id'] ?>" class="btn btn-sm btn-secondary" title="Códigos de barras"><i class="bi bi-upc-scan"></i></a>
                        <a href="<?= BASE_URL ?>/materiasprimas/stockdata/<?= (int)$p['id'] ?>" class="btn btn-sm btn-info" title="Parámetros de stock"><i class="bi bi-boxes"></i></a>
                        <a href="<?= BASE_URL ?>/materiasprimas/delete/<?= (int)$p['id'] ?>" class="btn btn-sm btn-danger" title="Inactivar"
                           onclick="return confirm('¿Inactivar esta materia prima?')"><i class="bi bi-trash"></i></a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script>
document.querySelectorAll('.js-form-reactivar').forEach(function (form) {
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        Swal.fire({
            icon: 'question',
            title: '¿Reactivar materia prima?',
            text: 'La materia prima "' + (form.dataset.nombre || '') + '" volverá a estar disponible para recetas, compras e ingresos. El stock queda como está (no se repone).',
            showCancelButton: true,
            confirmButtonColor: '#198754',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Sí, reactivar',
            cancelButtonText: 'Cancelar'
        }).then(function (result) {
            if (result.isConfirmed) form.submit();
        });
    });
});
</script>
