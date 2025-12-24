<div class="d-flex justify-content-between mb-3">
    <h3>Clientes</h3>
    <a href="<?= BASE_URL ?>/clientes/create" class="btn btn-primary">
        Nuevo Cliente
    </a>
</div>

<div class="table-responsive">
<table class="table table-striped table-hover">
    <thead class="table-dark">
        <tr>
            <th>Razón Social</th>
            <th>CUIT</th>
            <th>Email</th>
            <th>Acciones</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($clientes as $c): ?>
        <tr>
            <td><?= htmlspecialchars($c['razon_social']) ?></td>
            <td><?= $c['cuit'] ?></td>
            <td><?= $c['email'] ?></td>
            <td>
                <a class="btn btn-sm btn-warning"
                   href="<?= BASE_URL ?>/clientes/edit/<?= $c['id'] ?>">Editar</a>

                <a class="btn btn-sm btn-danger"
                   href="<?= BASE_URL ?>/clientes/delete/<?= $c['id'] ?>"
                   onclick="return confirm('¿Eliminar cliente?')">Eliminar</a>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
</div>