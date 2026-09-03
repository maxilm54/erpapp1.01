<div class="d-flex justify-content-between mb-3">
    <h3><?= $title ?></h3>
    <a href="<?= BASE_URL ?>/categoriamaterial/create" class="btn btn-primary">Nueva Categoría</a>
</div>

<div class="table-responsive table-scroll mt-3">
    <table class="table table-striped table-hover">
        <thead class="table-dark">
            <tr>
                <th>Nombre</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $item): ?>
            <tr>
                <td><?= htmlspecialchars($item['categoria_nombre']) ?></td>
                <td>
                    <?php if ($item['activo']): ?>
                        <i class="bi bi-check-circle-fill text-success"></i> Activo
                    <?php else: ?>
                        <i class="bi bi-ban text-danger"></i> Inactivo
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($item['activo']): ?>
                        <a href="<?= BASE_URL ?>/categoriamaterial/edit/<?= $item['id_categoria'] ?>" class="btn btn-sm btn-warning"><i class="bi bi-pencil-square"></i></a>
                        <a href="<?= BASE_URL ?>/categoriamaterial/delete/<?= $item['id_categoria'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('¿Inactivar esta categoría?')"><i class="bi bi-trash"></i></a>
                    <?php else: ?>
                        <a href="<?= BASE_URL ?>/categoriamaterial/activar/<?= $item['id_categoria'] ?>" class="btn btn-sm btn-success" onclick="return confirm('¿Activar esta categoría?')"><i class="bi bi-check-lg"></i> Activar</a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
