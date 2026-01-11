<h1>Historial de Ajustes de Stock</h1>

<a href="<?= BASE_URL ?>/ajustesstock/producto" class="btn btn-primary">Ajuste Producto</a>
<a href="<?= BASE_URL ?>/ajustesstock/materiaprima" class="btn btn-secondary">Ajuste Materia Prima</a>

<table class="table table-striped table-bordered">
    <thead>
        <tr>
            <th>Fecha</th>
            <th>Tipo</th>
            <th>Item</th>
            <th>Cantidad</th>
            <th>Usuario</th>
            <th>Motivo</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($ajustes as $a): ?>
        <tr>
            <td><?= $a['created_at'] ?></td>
            <td><?= $a['tipo'] ?></td>
            <td><?= $a['producto'] ?? $a['materia_prima'] ?></td>
            <td><?= number_format($a['cantidad'],2) ?></td>
            <td><?= htmlspecialchars($a['usuario']) ?></td>
            <td><?= htmlspecialchars($a['observaciones']) ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>