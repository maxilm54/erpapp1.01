<?php
$totalProducido = 0;
foreach ($orden_det as $r) {
    $totalProducido += $r['cantidad_producida'];
}
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h3><i class="bi bi-gear"></i> Orden de Produccion #<?= $orden['id'] ?></h3>
    <span class="badge bg-<?= $orden['estado'] === 'FINALIZADA' ? 'success' : ($orden['estado'] === 'EN_PRODUCCION' ? 'primary' : ($orden['estado'] === 'CANCELADA' ? 'danger' : 'warning')) ?> fs-6">
        <?= $orden['estado'] ?>
    </span>
</div>

<div class="row mb-4">
    <div class="col-md-8">
        <p><strong>Creado por:</strong> <?= htmlspecialchars($orden['nombre_user']) ?></p>
        <p><strong>Fecha creada:</strong> <?= htmlspecialchars($orden['created_at']) ?> &mdash; <strong>Entrega:</strong> <?= htmlspecialchars($orden['fecha_entrega']) ?></p>
        <p>
            <strong>Producto:</strong> <?= htmlspecialchars($orden['producto']) ?>
            <span class="badge bg-warning text-dark fs-6 ms-2">Cantidad: <?= number_format($orden['cantidad'], 2, ',', '.') ?></span>
        </p>
        <p><strong>Estado:</strong> <?= $orden['estado'] ?></p>
        <p><strong>Producido:</strong> <?= number_format($totalProducido, 2, ',', '.') ?> / <?= number_format($orden['cantidad'], 2, ',', '.') ?></p>

        <?php if ($orden['observaciones']): ?>
        <p><strong>Observaciones:</strong><br>
        <?= nl2br(htmlspecialchars($orden['observaciones'])) ?>
        </p>
        <?php endif ?>
    </div>
    <div class="col-md-4 text-end">
        <a href="<?= BASE_URL ?>/ordenproduccion" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Volver
        </a>
        <?php if ($orden['estado'] === 'PENDIENTE'): ?>
        <a href="<?= BASE_URL ?>/ordenproduccion/producir/<?= $orden['id'] ?>" class="btn btn-success">
            <i class="bi bi-play"></i> Iniciar Produccion
        </a>
        <?php endif ?>
        <?php if ($orden['estado'] === 'EN_PRODUCCION'): ?>
        <a href="<?= BASE_URL ?>/ordenproduccion/avance/<?= $orden['id'] ?>" class="btn btn-primary">
            <i class="bi bi-clipboard-plus"></i> Registrar Avance
        </a>
        <button class="btn btn-danger" onclick="confirmarAnulacionOP(<?= $orden['id'] ?>)">
            <i class="bi bi-x-circle"></i> Cancelar
        </button>
        <?php endif ?>
        <?php if ($orden['estado'] === 'EN_PRODUCCION' && $totalProducido >= $orden['cantidad']): ?>
        <a href="<?= BASE_URL ?>/ordenproduccion/finalizarproduccion/<?= $orden['id'] ?>" class="btn btn-success">
            <i class="bi bi-check-circle"></i> Finalizar
        </a>
        <?php endif ?>
    </div>
</div>

<hr>

<!-- Materia Prima Reservada -->
<h5><i class="bi bi-box-seam"></i> Materia Prima Reservada</h5>
<div class="table-responsive mt-3">
    <table class="table table-bordered table-hover">
        <thead class="table-dark">
            <tr>
                <th>Materia Prima</th>
                <th class="text-end">Cantidad</th>
                <th>Un. Medida</th>
                <th class="text-end">Precio Unit.</th>
                <th class="text-end">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $total = 0;
            foreach ($reservas as $r):
                $subtotal = $r['cantidad'] * ($r['precio_unitario'] ?? 0);
                $total += $subtotal;
            ?>
            <tr>
                <td><?= htmlspecialchars($r['nombre']) ?></td>
                <td class="text-end"><?= number_format($r['cantidad'], 3, ',', '.') ?></td>
                <td><?= htmlspecialchars($r['unidad_medida']) ?></td>
                <td class="text-end">$ <?= number_format($r['precio_unitario'] ?? 0, 2, ',', '.') ?></td>
                <td class="text-end"><strong>$ <?= number_format($subtotal, 2, ',', '.') ?></strong></td>
            </tr>
            <?php endforeach ?>
            <?php if (empty($reservas)): ?>
            <tr><td colspan="5" class="text-center text-muted">No hay materia prima reservada.</td></tr>
            <?php endif; ?>
        </tbody>
        <tfoot class="table-secondary">
            <tr>
                <td colspan="4" class="text-end"><strong>Total Materiales:</strong></td>
                <td class="text-end"><strong class="fs-5">$ <?= number_format($total, 2, ',', '.') ?></strong></td>
            </tr>
        </tfoot>
    </table>
</div>

<?php if (!empty($reservas)): ?>
<div class="row mt-3">
    <div class="col-md-8">
        <?php
        $precioVenta = (float)($orden['precio_venta'] ?? 0);
        $cantidadOrden = (float)($orden['cantidad'] ?? 1);
        $costoUnitario = $total / $cantidadOrden;
        $gananciaUnitaria = $precioVenta - $costoUnitario;
        $margenUnitario = $precioVenta > 0 ? ($gananciaUnitaria / $precioVenta * 100) : 0;
        $gananciaTotal = $gananciaUnitaria * $cantidadOrden;
        ?>
        <div class="card border-info">
            <div class="card-body">
                <h6 class="card-title"><i class="bi bi-calculator"></i> Analisis de Costos</h6>
                <div class="row text-center mb-3">
                    <div class="col">
                        <small class="text-muted d-block">Costo Total Materiales</small>
                        <strong>$ <?= number_format($total, 2, ',', '.') ?></strong>
                    </div>
                    <div class="col">
                        <small class="text-muted d-block">Cant. a Producir</small>
                        <strong><?= number_format($cantidadOrden, 0, ',', '.') ?></strong>
                    </div>
                    <div class="col">
                        <small class="text-muted d-block">Costo Unit. Materiales</small>
                        <strong>$ <?= number_format($costoUnitario, 2, ',', '.') ?></strong>
                    </div>
                    <div class="col">
                        <small class="text-muted d-block">Precio Venta Unit.</small>
                        <strong>$ <?= number_format($precioVenta, 2, ',', '.') ?></strong>
                    </div>
                </div>
                <hr>
                <div class="row text-center">
                    <div class="col">
                        <small class="text-muted d-block">Ganancia Unitaria</small>
                        <strong class="<?= $gananciaUnitaria >= 0 ? 'text-success' : 'text-danger' ?>">
                            $ <?= number_format($gananciaUnitaria, 2, ',', '.') ?>
                        </strong>
                    </div>
                    <div class="col">
                        <small class="text-muted d-block">Margen Unitario</small>
                        <strong class="<?= $margenUnitario >= 0 ? 'text-success' : 'text-danger' ?>">
                            <?= number_format($margenUnitario, 1, ',', '.') ?>%
                        </strong>
                    </div>
                    <div class="col">
                        <small class="text-muted d-block">Ganancia Total (<?= number_format($cantidadOrden, 0, ',', '.') ?> u.)</small>
                        <strong class="<?= $gananciaTotal >= 0 ? 'text-success' : 'text-danger' ?>">
                            $ <?= number_format($gananciaTotal, 2, ',', '.') ?>
                        </strong>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
function confirmarAnulacionOP(id) {
    Swal.fire({
        title: 'Cancelar Produccion?',
        text: 'Se devolvera a stock la materia prima no utilizada.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Si, cancelar',
        cancelButtonText: 'No'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = '<?= BASE_URL ?>/ordenproduccion/cancelarproduccion/' + id;
        }
    });
}
</script>
