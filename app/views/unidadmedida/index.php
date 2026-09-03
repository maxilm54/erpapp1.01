<div class="d-flex justify-content-between mb-3">
    <h3><?= $title ?></h3>
    <a href="<?= BASE_URL ?>/unidadmedida/create" class="btn btn-primary">Nueva Unidad de Medida</a>
</div>

<div class="table-responsive table-scroll mt-3">
    <table class="table table-striped table-hover">
        <thead class="table-dark">
            <tr>
                <th>Nombre</th>
                <th>Detalle</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $item): ?>
            <tr>
                <td><?= htmlspecialchars($item['nombre']) ?></td>
                <td><?= htmlspecialchars($item['detalle'] ?? '-') ?></td>
                <td>
                    <?php if ($item['activo']): ?>
                        <i class="bi bi-check-circle-fill text-success"></i> Activo
                    <?php else: ?>
                        <i class="bi bi-ban text-danger"></i> Inactivo
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($item['activo']): ?>
                        <a href="<?= BASE_URL ?>/unidadmedida/edit/<?= $item['id_medida'] ?>" class="btn btn-sm btn-warning"><i class="bi bi-pencil-square"></i></a>
                        <a href="<?= BASE_URL ?>/unidadmedida/delete/<?= $item['id_medida'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('¿Inactivar esta unidad de medida?')"><i class="bi bi-trash"></i></a>
                    <?php else: ?>
                        <a href="<?= BASE_URL ?>/unidadmedida/activar/<?= $item['id_medida'] ?>" class="btn btn-sm btn-success" onclick="return confirm('¿Activar esta unidad de medida?')"><i class="bi bi-check-lg"></i> Activar</a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
