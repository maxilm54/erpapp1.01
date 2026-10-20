<div class="d-flex justify-content-between align-items-center mb-3">
    <h3><?= htmlspecialchars($producto['nombre']) ?></h3>
    <a href="<?= BASE_URL ?>/productos" class="btn btn-secondary btn-sm">Volver</a>
</div>

<?php if (!empty($vencidos)): ?>
<div class="alert alert-danger">
    <i class="bi bi-exclamation-triangle"></i> <strong>¡Lotes vencidos con stock!</strong> Hay <?= count($vencidos) ?> lote(s) vencido(s) que aún tienen stock. Revise los detalles a continuación.
</div>
<?php endif; ?>
<?php if (!empty($proximos)): ?>
<div class="alert alert-warning">
    <i class="bi bi-clock"></i> <strong>Próximos a vencer</strong> <?= count($proximos) ?> lote(s) vencen dentro de los próximos 30 días.
</div>
<?php endif; ?>

<div class="card p-3 mb-3">
    <div class="row">
        <div class="col-md-3"><strong>SKU:</strong> <?= htmlspecialchars($producto['sku'] ?? '') ?></div>
        <div class="col-md-3"><strong>Precio Venta:</strong> $ <?= number_format($producto['precio_venta'] ?? 0, 2, ',', '.') ?></div>
        <div class="col-md-3"><strong>Stock Total:</strong> <?= number_format($stock, 3, ',', '.') ?></div>
        <div class="col-md-3"><strong>Unidad Medida:</strong> <?= htmlspecialchars($producto['detalle_medida'] ?? $producto['nombre_medida'] ?? '') ?></div>
    </div>
    <div class="row mt-2">
        <div class="col-md-12">
            <?php if (!empty($producto['usa_serie'])): ?>
                <span class="badge bg-danger fs-6"><i class="bi bi-upc-scan"></i> Controla N° de Serie</span>
            <?php else: ?>
                <span class="badge bg-secondary fs-6">Sin control de serie</span>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if (!empty($codigos)): ?>
<div class="mb-2"><strong>Códigos de barra:</strong>
    <?php foreach ($codigos as $c): ?>
        <span class="badge bg-secondary me-1"><?= htmlspecialchars($c['codigo']) ?> (<?= $c['tipo'] ?? '' ?>)</span>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<h5>Lotes / Series</h5>
<table class="table table-bordered table-hover">
    <thead class="table-dark">
        <tr>
            <th>Código Lote</th>
            <th>N° Serie</th>
            <th class="text-end">Stock</th>
            <th>Vencimiento</th>
            <th>Estado</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($lotes)): ?>
        <tr><td colspan="5" class="text-center text-muted">No hay lotes registrados para este producto</td></tr>
        <?php else: ?>
        <?php foreach ($lotes as $l): ?>
            <?php
                $vigente = !$l['fecha_vencimiento'] || $l['fecha_vencimiento'] >= date('Y-m-d');
                $proximo = $l['fecha_vencimiento'] && $l['fecha_vencimiento'] >= date('Y-m-d') && $l['fecha_vencimiento'] <= date('Y-m-d', strtotime('+30 days'));
            ?>
            <tr class="<?= $l['fecha_vencimiento'] && $l['fecha_vencimiento'] < date('Y-m-d') ? 'table-danger' : ($proximo ? 'table-warning' : '') ?>">
                <td><?= htmlspecialchars($l['codigo_lote']) ?></td>
                <td><?= htmlspecialchars($l['numero_serie'] ?? '') ?></td>
                <td class="text-end"><?= number_format($l['stock_actual'] ?? 0, 3, ',', '.') ?></td>
                <td><?= $l['fecha_vencimiento'] ? date('d/m/Y', strtotime($l['fecha_vencimiento'])) : '—' ?></td>
                <td>
                    <?php if ($l['fecha_vencimiento'] && $l['fecha_vencimiento'] < date('Y-m-d')): ?>
                        <span class="badge bg-danger">VENCIDO</span>
                    <?php elseif ($proximo): ?>
                        <span class="badge bg-warning text-dark">PRÓXIMO</span>
                    <?php else: ?>
                        <span class="badge bg-success">VIGENTE</span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>

<?php if (!empty($producto['usa_serie'])): ?>
<h5 class="mt-4">Unidades con N° de Serie (<?= count($series) ?>)</h5>
<table class="table table-bordered table-hover">
    <thead class="table-dark">
        <tr>
            <th>N° Serie</th>
            <th>Lote</th>
            <th>Depósito</th>
            <th>Estado</th>
            <th>Ingreso</th>
            <th>Remito</th>
            <th>Registrado</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($series)): ?>
        <tr><td colspan="7" class="text-center text-muted">No hay unidades con serie registradas para este producto</td></tr>
        <?php else: ?>
        <?php foreach ($series as $s): ?>
            <?php
                $estados = [
                    'DISPONIBLE'    => ['bg-success', 'DISPONIBLE'],
                    'REMITIDO'      => ['bg-primary', 'REMITIDO'],
                    'EN_DEVOLUCION' => ['bg-warning text-dark', 'EN DEVOLUCIÓN'],
                    'BAJA'          => ['bg-danger', 'BAJA'],
                ];
                $est = $estados[$s['estado']] ?? ['bg-secondary', $s['estado']];
            ?>
            <tr>
                <td><strong><?= htmlspecialchars($s['numero_serie']) ?></strong></td>
                <td><?= htmlspecialchars($s['codigo_lote'] ?? '—') ?></td>
                <td><?= htmlspecialchars($s['deposito_nombre'] ?? '—') ?></td>
                <td><span class="badge <?= $est[0] ?>"><?= $est[1] ?></span></td>
                <td><?= $s['ingreso_id'] ? '#' . $s['ingreso_id'] : '—' ?></td>
                <td><?= $s['remito_salida_id'] ? '#' . $s['remito_salida_id'] : '—' ?></td>
                <td><?= date('d/m/Y H:i', strtotime($s['created_at'])) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>
<?php endif; ?>

<p class="text-muted small">Creado: <?= $producto['created_at'] ?? '' ?></p>