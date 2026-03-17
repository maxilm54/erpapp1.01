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
<div class="table-scroll mt-3">
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Materia Prima</th>
                <th>Cantidad</th>
                <th>Precio U</th>
                <th>SubTotal</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $total=0;
            foreach ($reservas as $r):
                $total += $r['cantidad'] * ($r['precio_unitario'] ?? 0);
            ?>
            <tr>
                <td><?= htmlspecialchars($r['nombre']) ?></td>
                <td><?= number_format($r['cantidad'], 3) ?></td>
                <td><?= number_format($r['precio_unitario'] ?? 0, 2) ?></td>
                <td><?= number_format($r['cantidad'] * ($r['precio_unitario'] ?? 0), 2) ?></td>
            </tr>
            <?php endforeach ?>
            <tr>
                <td colspan="3" class="text-end"><strong>Total:</strong></td>
                <td><strong><?= number_format($total, 2) ?></strong></td>
            </tr>
        </tbody>
    </table>
</div>
<a href="<?= BASE_URL ?>/ordenproduccion" class="btn btn-secondary">Volver</a>
<?php if ($orden['estado'] === 'PENDIENTE'): ?>
<a href="<?= BASE_URL ?>/ordenproduccion/producir/<?= $orden['id'] ?>" class="btn btn-success">Producir</a>
<?php endif ?>
<?php if ($orden['estado'] === 'EN_PRODUCCION'): ?>
<a href="<?= BASE_URL ?>/ordenproduccion/finalizarproduccion/<?= $orden['id'] ?>" class="btn btn-primary">Finalizar Producción</a>
<?php endif ?>
<?php if ($orden['estado'] !== 'CANCELADA' && $orden['estado'] !== 'FINALIZADA' && $orden['estado'] !== 'EN_PRODUCCION'): ?>
<a href="<?= BASE_URL ?>/ordenproduccion/cancelarproduccion/<?= $orden['id'] ?>" class="btn btn-danger">Cancelar Producción</a>
<?php endif ?>