<h1>Remito de Salida #<?= $remito['NumRem'] ?></h1>

<p><strong>Nota de Pedido:</strong> #<?= $remito['idNpRem'] ?> <a href="<?= BASE_URL ?>/notaspedido/show/<?= $remito['idNpRem'] ?>" class="btn btn-secondary">NP</a></p> 
<p><strong>Cliente:</strong> <?= $remito['RazonSocial'] ?></p>
<p><strong>Fecha:</strong> <?= $remito['fecha'] ?></p>
<p><strong>Usuario:</strong> <?= htmlspecialchars($remito['UserRem']) ?></p>

<?php if ($remito['obsRemRem']): ?>
    <div class="alert alert-info">
        <?= nl2br(htmlspecialchars($remito['obsRemRem'])) ?>
    </div>
<?php endif; ?>
<div class="table-scroll mt-3">
    <table class="table table-striped">
        <thead>
            <tr>
                <th>Producto</th>
                <th width="120">Cantidad</th>
            </tr>
        </thead>
        <tbody> 
        <?php foreach ($remito['detalle'] as $item):?>
            <tr>
                <td><?= htmlspecialchars($item['ProdRem']) ?></td>
                <td><?= number_format($item['CantRem'], 2) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<a href="<?= BASE_URL ?>/remitossalida" class="btn btn-secondary">Volver</a>
<a href="<?= BASE_URL ?>/remitossalida/pdf/<?= $remito['NumRem'] ?>" target="_blank"
   class="btn btn-outline-primary">
   Descargar PDF
</a>
<a href="<?= BASE_URL ?>/remitossalida/reenviar/<?= $remito['NumRem'] ?>"
   class="btn btn-outline-primary"
   onclick="return confirm('¿Reenviar remito por mail?')">
   Reenviar Remito
</a>