<h1>Recetas de Producción</h1>

<a href="<?= BASE_URL ?>/recetas/create" class="btn btn-success mb-3">
    Nueva Receta
</a>

<table class="table table-bordered">
    <thead>
        <tr>
            <th>#</th>
            <th>Producto</th>
            <th>Receta</th>
            <th>Insumos</th>
            <th>Acciones</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($recetas as $r): ?>
        <tr>
            <td><?= $r['id'] ?></td>
            <td><?= htmlspecialchars($r['producto']) ?></td>
            <td><?= htmlspecialchars($r['nombre']) ?></td>
            <td><?= htmlspecialchars($r['mat_prim']) ?></td>
            <td>
                <a href="<?= BASE_URL ?>/recetas/show/<?= htmlspecialchars($r['id']) ?>"
                   class="btn btn-primary btn-sm">
                   Ver
                </a>
            </td>
        </tr>
        <?php endforeach ?>
    </tbody>
</table>