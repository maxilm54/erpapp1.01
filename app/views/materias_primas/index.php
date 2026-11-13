<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">Materias Primas</h3>
    <div>
        <a href="<?= BASE_URL ?>/materiasprimas/create" class="btn btn-primary">
            <i class="bi bi-plus-lg"></i> Nueva Materia Prima
        </a>
        <a href="<?= BASE_URL ?>/materiasprimas/import" class="btn btn-success">
            <i class="bi bi-upload"></i> Importar
        </a>
    </div>
</div>

<?php
$badgesTipo = [
    'UNIDAD'     => 'bg-secondary',
    'LINEAL'     => 'bg-info text-dark',
    'SUPERFICIE' => 'bg-warning text-dark',
    'GRANEL'     => 'bg-success',
];
?>

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
                <tr><td colspan="9" class="text-center text-muted">No hay materias primas activas.</td></tr>
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
                    <a href="<?= BASE_URL ?>/materiasprimas/updatebarcode/<?= (int)$p['id'] ?>" class="btn btn-sm btn-secondary" title="Códigos de barras"><i class="bi bi-upc-scan"></i></a>
                    <a href="<?= BASE_URL ?>/materiasprimas/stockdata/<?= (int)$p['id'] ?>" class="btn btn-sm btn-info" title="Parámetros de stock"><i class="bi bi-boxes"></i></a>
                    <a href="<?= BASE_URL ?>/materiasprimas/delete/<?= (int)$p['id'] ?>" class="btn btn-sm btn-danger" title="Inactivar"
                       onclick="return confirm('¿Inactivar esta materia prima?')"><i class="bi bi-trash"></i></a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
