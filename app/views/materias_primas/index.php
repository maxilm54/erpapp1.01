<a href="<?= BASE_URL ?>/materiasprimas/create"
   class="btn btn-primary mb-3">Nueva</a>
<div class="table-scroll mt-3">
    <table class="table table-striped color-table">
        <thead class="table-dark">
            <tr>
                <th>Nombre</th>
                <th>SKU</th>
                <th>Stock</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($items as $i): ?>
                <tr>
                    <td><?= $i['nombre'] ?></td>
                    <td><?= $i['sku'] ?></td>
                    <td><?= $i['stock_actual'].' '.$i['unidad_medida'] ?></td>
                </tr>
            <?php endforeach ?>
        </tbody>
    </table>
</div>