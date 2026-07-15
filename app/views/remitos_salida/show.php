<?php
$esManual = empty($remito['idNpRem']);
?>

<h3><i class="bi bi-truck"></i> Remito de Salida #<?= $remito['NumRem'] ?></h3>

<?php if ($esManual): ?>
    <span class="badge bg-info mb-2">REMITO MANUAL</span>
<?php else: ?>
    <span class="badge bg-primary mb-2">REMITO CON NP</span>
<?php endif; ?>

<?php if (!$esManual): ?>
<p><strong>Nota de Pedido:</strong> #<?= $remito['idNpRem'] ?> <a href="<?= BASE_URL ?>/notaspedido/show/<?= $remito['idNpRem'] ?>" class="btn btn-sm btn-secondary">Ver NP</a></p>
<?php endif; ?>

<p><strong>Cliente:</strong> <?= htmlspecialchars($remito['RazonSocial']) ?></p>

<?php if (!empty($remito['cuit'])): ?>
    <p><strong>CUIT:</strong> <?= htmlspecialchars($remito['cuit']) ?></p>
<?php endif; ?>

<?php if (!empty($remito['direccion'])): ?>
    <p><strong>Dirección:</strong> <?= htmlspecialchars($remito['direccion']) ?></p>
<?php endif; ?>

<?php if (!empty($remito['email'])): ?>
    <p><strong>Email:</strong> <?= htmlspecialchars($remito['email']) ?></p>
<?php endif; ?>

<p><strong>Fecha:</strong> <?= date('d/m/Y H:i', strtotime($remito['fecha'])) ?></p>
<p><strong>Usuario:</strong> <?= htmlspecialchars($remito['UserRem']) ?></p>

<?php if (!empty($remito['obsRemRem'])): ?>
    <div class="alert alert-info">
        <?= nl2br(htmlspecialchars($remito['obsRemRem'])) ?>
    </div>
<?php endif; ?>

<div class="table-responsive mt-3">
    <table class="table table-striped">
        <thead class="table-dark">
            <tr>
                <th>Producto</th>
                <th class="text-end">Precio U.</th>
                <th width="120">Cantidad</th>
                <th class="text-end">Subtotal</th>
            </tr>
        </thead>
        <tbody>
        <?php 
        $totalGeneral = 0;
        foreach ($remito['detalle'] as $item):
            $precio = (float)($item['precioUnitario'] ?? 0);
            $cant = (float)$item['CantRem'];
            $subtotal = $precio * $cant;
            $totalGeneral += $subtotal;
        ?>
            <tr>
                <td><?= htmlspecialchars($item['ProdRem']) ?></td>
                <td class="text-end">$ <?= number_format($precio, 2, ',', '.') ?></td>
                <td><?= number_format($cant, 2) ?></td>
                <td class="text-end fw-bold">$ <?= number_format($subtotal, 2, ',', '.') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr class="table-success fw-bold">
                <td colspan="3" class="text-end">TOTAL:</td>
                <td class="text-end">$ <?= number_format($totalGeneral, 2, ',', '.') ?></td>
            </tr>
        </tfoot>
    </table>
</div>

<div class="d-flex gap-2 mt-3">
    <a href="<?= BASE_URL ?>/remitossalida" class="btn btn-secondary">Volver</a>
    <a href="<?= BASE_URL ?>/remitossalida/pdf/<?= $remito['NumRem'] ?>" target="_blank"
       class="btn btn-outline-primary">
       <i class="bi bi-file-pdf"></i> Descargar PDF
    </a>
    <a href="<?= BASE_URL ?>/remitossalida/reenviar/<?= $remito['NumRem'] ?>"
       class="btn btn-outline-primary"
       onclick="return confirm('¿Reenviar remito por mail?')">
       <i class="bi bi-envelope"></i> Reenviar Remito
    </a>
</div>
