<h2>Ingreso de Mercadería</h2>

<div class="card mb-3">
    <div class="card-body">
        <p><strong>Proveedor:</strong> <?= $ingreso['proveedor'] ?></p>
        <p><strong>Remito:</strong> <?= $ingreso['remito'] ?></p>
        <p><strong>Fecha:</strong> <?= date('d/m/Y', strtotime($ingreso['fecha'])) ?></p>
        <p><strong>Orden:</strong> #<?= $ingreso['orden_compra_id'] ?></p>
    </div>
</div>

<table class="table table-bordered">
    <thead>
        <tr>
            <th>Materia Prima</th>
            <th>Pedida</th>
            <th>Ingresada</th>
            <th>Faltante</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($ingreso['detalle'] as $d): ?>
        <tr>
            <td><?= $d['nombre'] ?></td>
            <td><?= $d['pedida'] ?></td>
            <td><?= $d['ingresada'] ?></td>
            <td>
                <?php if ($d['faltante'] > 0): ?>
                    <span class="badge bg-warning">
                        <?= $d['faltante'] ?>
                    </span>
                <?php else: ?>
                    <span class="badge bg-success">OK</span>
                <?php endif ?>
            </td>
        </tr>
        <?php endforeach ?>
    </tbody>
</table>