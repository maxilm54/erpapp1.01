<h1>Stock de Materias Primas</h1>

<table class="table table-bordered table-hover">
    <thead>
        <tr>
            <th>Materia Prima</th>
            <th>Unidad</th>
            <th class="text-end">Stock</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($stock as $mp): ?>
        <tr>
            <td><?= htmlspecialchars($mp['nombre']) ?></td>
            <td><?= htmlspecialchars($mp['unidad_medida']) ?></td>
            <td class="text-end"><?= number_format($mp['stock'],3) ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>