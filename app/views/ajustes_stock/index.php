<h1>Historial de Movimientos de Stock</h1>
<a href="<?= BASE_URL ?>/ajustesstock/producto" class="btn btn-primary">Ajuste Producto</a>
<a href="<?= BASE_URL ?>/ajustesstock/materiaprima" class="btn btn-secondary">Ajuste Materia Prima</a>
<a href="<?= BASE_URL ?>/stock/productos" class="btn btn-primary">Stock Productos</a>
<a href="<?= BASE_URL ?>/stock/materiasprimas" class="btn btn-secondary">Stock Materias Primas</a>
<div class="table-scroll mt-3">
    <table class="table table-striped table-bordered mt-3 table-hover">
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Origen</th>
                <th>Tipo</th>
                <th>Referencia</th>
                <th>Item</th>
                <th>Cantidad</th>
                <th>Usuario</th>
                <th>Motivo</th>
                <th>Obs</th>
            </tr>
        </thead>
        <tbody class="table-group-divider">
            <?php foreach ($ajustes as $a): ?>
            <tr>
                <td><?= $a['created_at'] ?></td>
                <td><?= $a['origen'] ?></td>
                <td><?= $a['tipo'] ?></td>
                <td><?= $a['referencia_id'] ?></td>
                <td><?= $a['producto'] ?? $a['materia_prima'] ?></td>
                <td><?= number_format($a['cantidad'],2,',','.') ?></td>
                <td><?= htmlspecialchars($a['usuario']) ?></td>
                <td><?= htmlspecialchars($a['motivo']) ?></td>
                <td><?= htmlspecialchars($a['observaciones']) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
