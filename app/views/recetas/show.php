<h1>Receta #<?= $receta['id'] ?></h1>

<p><strong>Producto:</strong> <?= htmlspecialchars($receta['producto']) ?></p>

<table class="table table-bordered">
    <thead>
        <tr>
            <th>Materia Prima</th>
            <th>Cantidad</th>
            <th>Unidad de Medida</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($rec_det as $d): ?>
        <tr>
            <td><?= htmlspecialchars($d['nombre']) ?></td>
            <td><?= number_format($d['cantidad'], 3) ?></td>
            <td><?= $d['unidad_medida'] ?></td>
        </tr>
        <?php endforeach ?>
    </tbody>
</table>
<p><strong>Procedimiento u Observaciones de Fabricacion:</strong></p>
<p><?= nl2br(htmlspecialchars($receta['proceso_fabrica'] ?? 'Sin procedimiento especificado')) ?></p>
<hr>

<a href="<?= BASE_URL ?>/recetas" class="btn btn-secondary">Volver</a>
<a href="<?= BASE_URL ?>/recetas/edit/<?= $receta['id'] ?>" class="btn btn-warning" onclick="return confirm('¿Editar Receta?')">Editar</a>
<a href="<?= BASE_URL ?>/recetas/delete/<?= $receta['id'] ?>" class="btn btn-danger" onclick="return confirm('¿Eliminar Receta?')">Eliminar</a>

