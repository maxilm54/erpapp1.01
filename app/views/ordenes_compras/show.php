<div class="d-flex justify-content-between align-items-center mb-3">
    <h3>Orden de Compra #<?= $orden['id'] ?></h3>

    <span class="badge bg-<?= 
        $orden['estado'] === 'PENDIENTE' ? 'warning' :
        ($orden['estado'] === 'APROBADA' ? 'success' : 'secondary')
    ?>">
        <?= $orden['estado'] ?>
    </span>
</div>

<div class="card mb-4">
    <div class="card-body">
        <p><strong>Proveedor:</strong> <?= htmlspecialchars($orden['razon_social']) ?></p>
        <p><strong>Fecha:</strong> <?= date('d/m/Y', strtotime($orden['created_at'])) ?></p>
    </div>
</div>

<h5>Detalle</h5>

<table class="table table-bordered table-striped">
<thead class="table-dark">
<tr>
    <th>Materia Prima</th>
    <th>Cantidad</th>
    <th>Unidad</th>
</tr>
</thead>
<tbody>
<?php foreach ($orden['detalle'] as $d): ?>
<tr>
    <td><?= htmlspecialchars($d['nombre']) ?></td>
    <td><?= number_format($d['cantidad'], 3) ?></td>
    <td><?= htmlspecialchars($d['unidad_medida']) ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>

<div class="mt-4 d-flex gap-2">
    <a href="<?= BASE_URL ?>/ordenescompra"
       class="btn btn-secondary">
       Volver
    </a>

    <?php if ($orden['estado'] === 'PENDIENTE'): ?>
        <a href="<?= BASE_URL ?>/ordenescompra/edit/<?= $orden['id'] ?>"
           class="btn btn-warning">
           Editar
        </a>

        <a href="<?= BASE_URL ?>/ordenescompra/aprobar/<?= $orden['id'] ?>"
           class="btn btn-success"
           onclick="return confirm('¿Aprobar esta orden?');">
           Aprobar
        </a>
    <?php endif; ?>
</div>