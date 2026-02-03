<h1>Órdenes de Producción</h1>

<a href="<?= BASE_URL ?>/ordenproduccion/create" class="btn btn-success mb-3">
    Nueva Orden
</a>

<table class="table table-bordered">
    <thead>
        <tr>
            <th>#</th>
            <th>Producto</th>
            <th>Cantidad</th>
            <th>Estado</th>
            <th>Acciones</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($ordenes as $o): ?>
        <tr>
            <td><?= $o['id'] ?></td>
            <td><?= htmlspecialchars($o['producto']) ?></td>
            <td><?= number_format($o['cantidad'], 2) ?></td>
            <td>
                <span class="badge bg-info"><?= $o['estado'] ?></span>
            </td>
            <td>
                <a href="<?= BASE_URL ?>/ordenproduccion/show/<?= $o['id'] ?>"
                   class="btn btn-primary btn-sm">
                   Ver
                </a>
            </td>
        </tr>
        <?php endforeach ?>
    </tbody>
</table>