<a href="<?= BASE_URL ?>/productos/create" class="btn btn-primary mb-3">
    Nuevo Producto
</a>

<div class="table-responsive">
<table class="table table-striped">
<thead class="table-dark">
<tr>
    <th>Imagen</th>
    <th>Nombre</th>
    <th>SKU</th>
    <th>Precio</th>
</tr>
</thead>
<tbody>
<?php foreach ($productos as $p): ?>
<tr>
    <td>
        <?php if ($p['imagen']): ?>
            <img src="<?= BASE_URL ?>/<?= $p['imagen'] ?>" width="50">
        <?php endif; ?>
    </td>
    <td><?= $p['nombre'] ?></td>
    <td><?= $p['sku'] ?></td>
    <td>$<?= $p['precio_venta'] ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>