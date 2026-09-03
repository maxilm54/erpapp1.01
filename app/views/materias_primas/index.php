<a href="<?= BASE_URL ?>/materiasprimas/create" class="btn btn-primary mb-3">
    Nueva Materia Prima
</a>
<a href="<?= BASE_URL ?>/materiasprimas/import" class="btn btn-success mb-3">
    <i class="bi bi-upload"></i> Importar
</a>

<div class="table-responsive table-scroll mt-3">
    <table class="table table-striped">
        <thead class="table-dark">
            <tr>
                <th>Imagen</th>
                <th>Nombre</th>
                <th>SKU</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $p): ?>
            <tr>
                <td>
                    <?php
                    $imgPath = $p['imagen'] ?? null;
                    $imgUrl = $imgPath ? empresaUploadUrl('materiasprimas') . '/' . htmlspecialchars($imgPath) : empresaUploadUrl('materiasprimas') . '/sin-imagen.jpg';
                    ?>
                    <img src="<?= $imgUrl ?>" width="50" height="50" style="object-fit: cover;" alt="img">
                </td>
                <td><?= $p['nombre'] ?></td>
                <td><?= $p['sku'] ?></td>
                <td>
                    <a href="<?= BASE_URL ?>/materiasprimas/update/<?= $p['id'] ?>" class="btn btn-sm btn-info"><i class="bi bi-pencil-square"></i></a>
                    <a href="<?= BASE_URL ?>/materiasprimas/updatebarcode/<?= $p['id'] ?>" class="btn btn-sm btn-info"><i class="bi bi-upc-scan"></i></a>
                    <!-- <a href="<?= BASE_URL ?>/materiasprimas/updateimage/<?= $p['id'] ?>" class="btn btn-sm btn-info"><i class="bi bi-image"></i></a> -->
                    <a href="<?= BASE_URL ?>/materiasprimas/stockdata/<?= $p['id'] ?>" class="btn btn-sm btn-info"><i class="bi bi-boxes"></i></a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>