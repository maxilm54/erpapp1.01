<h1>Orden de Producción #<?= $orden['id'] ?></h1>

<p><strong>Producto:</strong> <?= htmlspecialchars($orden['producto']) ?></p>
<p><strong>Cantidad:</strong> <?= number_format($orden['cantidad'], 2) ?></p>
<p><strong>Estado:</strong> <?= $orden['estado'] ?></p>

<?php if ($orden['observaciones']): ?>
<p><strong>Observaciones:</strong><br>
<?= nl2br(htmlspecialchars($orden['observaciones'])) ?>
</p>
<?php endif ?>

<hr>

<h5>Materia Prima Reservada</h5>

<table class="table table-bordered">
    <thead>
        <tr>
            <th>Materia Prima</th>
            <th>Cantidad</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($reservas as $r): ?>
        <tr>
            <td><?= htmlspecialchars($r['nombre']) ?></td>
            <td><?= number_format($r['cantidad'], 3) ?></td>
        </tr>
        <?php endforeach ?>
    </tbody>
</table>

<a href="<?= BASE_URL ?>/produccion" class="btn btn-secondary">Volver</a>