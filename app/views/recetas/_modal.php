<h5>Producto: <?= htmlspecialchars($receta['producto']) ?></h5>
<p><strong>Proceso:</strong></p>
<p><?= nl2br(htmlspecialchars($receta['proceso_fabrica'])) ?></p>

<table class="table table-sm table-bordered mt-3">
    <thead>
        <tr>
            <th>Materia Prima</th>
            <th>Cantidad</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($receta['detalle'] as $d): ?>
            <tr>
                <td><?= htmlspecialchars($d['nombre']) ?></td>
                <td><?= number_format($d['cantidad'], 3) ?></td>
            </tr>
        <?php endforeach ?>
    </tbody>
</table>