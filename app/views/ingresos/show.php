<h2>Ingreso de Mercadería</h2>

<div class="card mb-3">
    <div class="card-body">
        <p><strong>Proveedor:</strong> <?= $ingreso['proveedor'] ?></p>
        <p><strong>Remito:</strong> <?= $ingreso['remito'] ?></p>
        <p><strong>Fecha:</strong> <?= date('d/m/Y', strtotime($ingreso['fecha'])) ?></p>
        <p><strong>Orden:</strong> OC-<?= $ingreso['orden_compra_id'] ?></p>
        <p><strong>Ingreso:</strong> #-<?= $ingreso['ing_num_indicador'] ?></p>
    </div>
</div>

<table class="table table-bordered">
    <thead>
        <tr>
            <th>Materia Prima</th>
            <th>Pedida</th>
            <th>Ingresado</th>
            <th>Ingreso OC-<?= $ingreso['orden_compra_id'] ?> Ing #-<?=$ingreso['ing_num_indicador'] ?></th>
            <th>Faltante</th>
        </tr>
    </thead>
    <tbody>
        <?php 
        $i=0;
        foreach ($ingreso['detalle'] as $d): ?>
        <tr>
            <td><?= $d['nombre'] ?></td>
            <td><?= $d['pedida'] ?></td>
            <td><?= $faltante[$i]['total_cantidad'] ?? '0.000' ?></td>
            <td><?= $d['ingresada'] ?></td>
            <td>
                <?php if (($d['pedida'] - (($faltante[$i]['total_cantidad'] ?? 0) + $d['ingresada'])) > 0): ?>
                    <span class="badge bg-warning">
                        <?= ($d['pedida'] - (($faltante[$i]['total_cantidad'] ?? 0) + $d['ingresada'])) ?>
                    </span>
                <?php else: ?>
                    <span class="badge bg-success">OK</span>
                <?php endif ?>
            </td>
        </tr>
        <?php 
        $i++;
                endforeach; 
        $i=0;
        ?>
    </tbody>
</table>
<div class="mt-4 d-flex gap-2">
    <a href="<?= BASE_URL ?>/ingresosmercaderia"
       class="btn btn-secondary">
       Volver
    </a>    
</div>