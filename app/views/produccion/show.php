<?php
$totalProducido = 0;
$totalPendiente = 0;
foreach ($orden_det as $r) {
    if (!empty($r['confirma_produccion'])) {
        $totalProducido += (float)$r['cantidad_producida'];
    } else {
        $totalPendiente += (float)$r['cantidad_producida'];
    }
}
$inicioProd = $orden['produccion_inicio_at'] ?? null;
$finProd = $orden['finalizada_at'] ?? null;
$horasProd = null;
if ($inicioProd && $finProd) {
    $horasProd = (strtotime($finProd) - strtotime($inicioProd)) / 3600;
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
        <p>
            <strong>Inicio producción:</strong>
            <?= $inicioProd ? date('d/m/Y H:i', strtotime($inicioProd)) : '—' ?>
            &mdash;
            <strong>Fin producción:</strong>
            <?= $finProd ? date('d/m/Y H:i', strtotime($finProd)) : '—' ?>
            &mdash;
            <strong>Horas totales:</strong>
            <?= $horasProd !== null ? number_format($horasProd, 2, ',', '.') . ' h' : '—' ?>
        </p>
        <?php if ($totalPendiente > 0): ?>
            <p><strong>Pendiente de confirmar:</strong> <?= number_format($totalPendiente, 2, ',', '.') ?></p>
        <?php endif; ?>

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
            <?php if (!empty($tieneProcesos)): ?>
                <a href="<?= BASE_URL ?>/ordenproduccion/procesos/<?= $orden['id'] ?>" class="btn btn-primary">
                    <i class="bi bi-diagram-3"></i> Procesos
                </a>
            <?php else: ?>
                <a href="<?= BASE_URL ?>/ordenproduccion/producir/<?= $orden['id'] ?>" class="btn btn-success">
                    <i class="bi bi-play"></i> Iniciar Produccion
                </a>
            <?php endif ?>
        <?php endif ?>
        <?php if ($orden['estado'] === 'EN_PRODUCCION'): ?>
            <?php if (!empty($tieneProcesos)): ?>
                <a href="<?= BASE_URL ?>/ordenproduccion/procesos/<?= $orden['id'] ?>" class="btn btn-primary">
                    <i class="bi bi-diagram-3"></i> Procesos
                </a>
            <?php else: ?>
                <a href="<?= BASE_URL ?>/ordenproduccion/avance/<?= $orden['id'] ?>" class="btn btn-primary">
                    <i class="bi bi-clipboard-plus"></i> Registrar Avance
                </a>
            <?php endif ?>
        <button class="btn btn-danger" onclick="confirmarAnulacionOP(<?= $orden['id'] ?>)">
            <i class="bi bi-x-circle"></i> Cancelar
        </button>
        <?php endif ?>
        <?php if (empty($tieneProcesos) && $orden['estado'] === 'EN_PRODUCCION' && $totalProducido >= $orden['cantidad']): ?>
        <a href="<?= BASE_URL ?>/ordenproduccion/finalizarproduccion/<?= $orden['id'] ?>" class="btn btn-success">
            <i class="bi bi-check-circle"></i> Finalizar
        </a>
        <?php endif ?>
    </div>
</div>

<hr>

<?php if (!empty($validacionProductos['faltantes'])): ?>
<div class="alert alert-danger">
    <h6 class="alert-heading"><i class="bi bi-exclamation-triangle"></i> Faltan productos componentes</h6>
    <p class="mb-2">
        La receta incluye productos que no tienen stock suficiente para
        <?= number_format((float)$orden['cantidad'], 3, ',', '.') ?> unidades. No se puede iniciar la producción hasta reponerlos.
    </p>
    <ul class="mb-2">
        <?php foreach ($validacionProductos['faltantes'] as $faltante): ?>
            <li>
                <strong><?= htmlspecialchars($faltante['producto']) ?></strong>:
                faltan <?= number_format((float)$faltante['faltante'], 3, ',', '.') ?>
                <?= htmlspecialchars($faltante['unidad'] ?? '') ?>
                (necesario <?= number_format((float)$faltante['necesario'], 3, ',', '.') ?>,
                disponible <?= number_format((float)$faltante['disponible'], 3, ',', '.') ?>)
            </li>
        <?php endforeach; ?>
    </ul>
    <?php if ($validacionProductos['max_producible'] !== null): ?>
        <p class="mb-0 small">
            Con el stock actual de productos componentes se pueden producir como máximo
            <strong><?= number_format((float)$validacionProductos['max_producible'], 3, ',', '.') ?></strong> unidades.
        </p>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php $adjModulo = 'PRODUCCION'; $adjDocId = (int)$orden['id'];
      require BASE_PATH . '/app/views/adjuntos/panel.php'; ?>

<!-- Materia Prima Reservada -->
<h5><i class="bi bi-box-seam"></i> Materia Prima Reservada</h5>
<div class="table-responsive mt-3">
    <table class="table table-bordered table-hover">
        <thead class="table-dark">
            <tr>
                <th>Materia Prima</th>
                <th class="text-end">Cantidad</th>
                <th>Un. Medida</th>
                <th>Depósito</th>
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
                <td><?= htmlspecialchars($r['deposito_nombre'] ?? '—') ?></td>
                <td class="text-end">$ <?= number_format($r['precio_unitario'] ?? 0, 2, ',', '.') ?></td>
                <td class="text-end"><strong>$ <?= number_format($subtotal, 2, ',', '.') ?></strong></td>
            </tr>
            <?php endforeach ?>
            <?php if (empty($reservas)): ?>
            <tr><td colspan="6" class="text-center text-muted">No hay materia prima reservada.</td></tr>
            <?php endif; ?>
        </tbody>
        <tfoot class="table-secondary">
            <tr>
                <td colspan="5" class="text-end"><strong>Total Materias Primas:</strong></td>
                <td class="text-end"><strong class="fs-5">$ <?= number_format($total, 2, ',', '.') ?></strong></td>
            </tr>
        </tfoot>
    </table>
</div>

<?php if (!empty($productos_receta)): ?>
<h5 class="mt-4"><i class="bi bi-box"></i> Productos componentes de la receta</h5>
<div class="table-responsive mt-3">
    <table class="table table-bordered table-hover">
        <thead class="table-dark">
            <tr>
                <th>Producto</th>
                <th class="text-end">Cantidad necesaria</th>
                <th class="text-end">Precio de venta</th>
                <th class="text-end">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($productos_receta as $prod): ?>
            <tr>
                <td>
                    <?= htmlspecialchars($prod['nombre']) ?>
                    <?php if (!empty($prod['sku'])): ?>
                        <small class="text-muted">(<?= htmlspecialchars($prod['sku']) ?>)</small>
                    <?php endif; ?>
                </td>
                <td class="text-end">
                    <?= number_format((float)$prod['cantidad_total'], 3, ',', '.') ?>
                    <?= htmlspecialchars($prod['unidad_medida'] ?? '') ?>
                </td>
                <td class="text-end">$ <?= number_format((float)$prod['precio_unitario'], 2, ',', '.') ?></td>
                <td class="text-end"><strong>$ <?= number_format((float)$prod['subtotal'], 2, ',', '.') ?></strong></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot class="table-secondary">
            <tr>
                <td colspan="3" class="text-end"><strong>Total productos componentes:</strong></td>
                <td class="text-end"><strong class="fs-5">$ <?= number_format((float)$total_productos, 2, ',', '.') ?></strong></td>
            </tr>
        </tfoot>
    </table>
</div>
<?php endif; ?>

<?php $totalMateriales = $total + (float)($total_productos ?? 0); ?>
<?php if (!empty($reservas) || !empty($productos_receta)): ?>
<div class="row mt-3">
    <div class="col-md-8">
        <?php
        $precioVenta = (float)($orden['precio_venta'] ?? 0);
        $cantidadOrden = (float)($orden['cantidad'] ?? 1);
        $costoUnitario = $totalMateriales / $cantidadOrden;
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
                        <strong>$ <?= number_format($totalMateriales, 2, ',', '.') ?></strong>
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
