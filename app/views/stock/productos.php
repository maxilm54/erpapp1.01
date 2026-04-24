<h1>Stock de Productos</h1>
<div class="table-scroll mt-3">
    <table class="table table-bordered table-hover">
        <thead>
            <tr>
                <th>SKU</th>
                <th>Producto</th>
                <th class="text-center">Stock</th>
                <th class="text-center">Stock Mínimo</th>
                <th class="text-center">Stock Máximo</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($stock as $p): ?>
            <tr>
                <td><?= htmlspecialchars($p['sku']) ?></td>
                <td><?= htmlspecialchars($p['nombre']) ?></td>
                <td class="text-center"><?= number_format($p['stock'],2,',','.') ?></td>
                <td class="text-center"><?= number_format($p['stock_minimo'],2,',','.') ?></td>
                <td class="text-center"><?= number_format($p['stock_maximo'],2,',','.') ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<hr>
<a href="<?= BASE_URL ?>/ajustesstock" class="btn btn-primary">Volver</a>