<a href="<?= BASE_URL ?>/productos/create" class="btn btn-primary mb-3">
    Nuevo Producto
</a>
<a href="<?= BASE_URL ?>/productos/import" class="btn btn-success mb-3">
    <i class="bi bi-upload"></i> Importar
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
                    <?php
                    $imgPath = $p['imagen'] ?? null;
                    $imgUrl = $imgPath ? empresaUploadUrl('productos') . '/' . htmlspecialchars($imgPath) : empresaUploadUrl('productos') . '/sin-imagen.jpg';
                    ?>
                    <img src="<?= $imgUrl ?>" width="50" height="50" style="object-fit: cover;" alt="img">
                </td>
                <td><?= $p['nombre'] ?></td>
                <td><?= $p['sku'] ?></td>
                <td>$<?= $p['precio_venta'] ?></td>
                <td>
                    <a href="<?= BASE_URL ?>/productos/update/<?= $p['id'] ?>" class="btn btn-sm btn-info"><i class="bi bi-pencil-square"></i></a>
                    <a href="<?= BASE_URL ?>/productos/updatebarcode/<?= $p['id'] ?>" class="btn btn-sm btn-info"><i class="bi bi-upc-scan"></i></a>
                    <!-- <a href="<?php //BASE_URL ?>/productos/uploadImagen/<?php //$p['id'] ?>" class="btn btn-sm btn-info"><i class="bi bi-image"></i></a> -->
                    <a href="<?= BASE_URL ?>/productos/stockdata/<?= $p['id'] ?>" class="btn btn-sm btn-info"><i class="bi bi-boxes"></i></a>
                    <a href="<?= BASE_URL ?>/productos/preciocompra/<?= $p['id'] ?>" class="btn btn-sm btn-success" title="Costos y Precios"><i class="bi bi-tag"></i></a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>