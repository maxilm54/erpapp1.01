<h1>Ajustes de Stock</h1>

<a href="<?= BASE_URL ?>/ajustesstock/producto" class="btn btn-primary">Ajuste Producto</a>
<a href="<?= BASE_URL ?>/ajustesstock/materiaprima" class="btn btn-secondary">Ajuste Materia Prima</a>

<table class="table mt-3">
    <thead>
        <tr>
            <th>Fecha</th>
            <th>Tipo</th>
            <th>Item</th>
            <th>Cantidad</th>
            <th>Motivo</th>
            <th>Usuario</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($ajustes as $a): ?>
            <tr>
                <td><?= $a['created_at'] ?></td>
                <td><?= $a['producto_id'] ? 'Producto' : 'Materia Prima' ?></td>
                <td>
                    <?= $a['producto_id'] ?? $a['materia_prima_id'] ?>
                </td>
                <td><?= $a['cantidad'] ?></td>
                <td><?= htmlspecialchars($a['observaciones']) ?></td>
                <td><?= htmlspecialchars($a['usuario']) ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>