<a href="<?= BASE_URL ?>/productos/create" class="btn btn-primary mb-3">
    Nuevo Producto
</a>

<div class="table-responsive table-scroll mt-3">
    <table class="table table-striped">
        <thead class="table-dark">
            <tr>
                <th>Imagen</th>
                <th>Nombre</th>
                <th>SKU</th>
                <th>Precio</th>
                <th>Acciones</th>
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
                <td>
                    <a href="<?= BASE_URL ?>/productos/update/<?= $p['id'] ?>" class="btn btn-sm btn-info"><i class="bi bi-pencil-square"></i></a>
                    <a href="<?= BASE_URL ?>/productos/updatebarcode/<?= $p['id'] ?>" class="btn btn-sm btn-info"><i class="bi bi-upc-scan"></i></a>
                    <!-- <a href="<?php //BASE_URL ?>/productos/uploadImagen/<?php //$p['id'] ?>" class="btn btn-sm btn-info"><i class="bi bi-image"></i></a> -->
                    <a href="<?= BASE_URL ?>/productos/stockdata/<?= $p['id'] ?>" class="btn btn-sm btn-info"><i class="bi bi-boxes"></i></a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>