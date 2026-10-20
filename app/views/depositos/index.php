<h3><?= $title ?></h3>

<a href="<?= BASE_URL ?>/depositos/create" class="btn btn-primary mb-3">Nuevo Depósito</a>

<table class="table table-bordered">
    <thead class="table-dark">
        <tr>
            <th>#</th>
            <th>Nombre</th>
            <th>Descripción</th>
            <th>Acciones</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($depositos)): ?>
        <tr><td colspan="4" class="text-center">No hay depósitos registrados</td></tr>
        <?php else: ?>
        <?php foreach ($depositos as $d): ?>
        <tr>
            <td><?= $d['id'] ?></td>
            <td><?= htmlspecialchars($d['nombre']) ?></td>
            <td><?= htmlspecialchars($d['descripcion'] ?? '') ?></td>
            <td>
                <a href="<?= BASE_URL ?>/depositos/edit/<?= $d['id'] ?>" class="btn btn-sm btn-warning">Editar</a>
                <a href="<?= BASE_URL ?>/depositos/delete/<?= $d['id'] ?>" class="btn btn-sm btn-danger btn-confirm">Desactivar</a>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>