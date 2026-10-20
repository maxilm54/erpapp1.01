<h3><?= $title ?></h3>

<?php if ($transferencia['estado'] === 'ANULADA'): ?>
<div class="alert alert-danger">Esta transferencia fue anulada.</div>
<?php elseif ($transferencia['estado'] === 'EN_TRANSITO'): ?>
<div class="alert alert-warning">
    <i class="bi bi-truck"></i> <strong>EN TRÁNSITO</strong> — La mercadería salió del depósito
    <?= htmlspecialchars($transferencia['deposito_origen']) ?> y aún no fue acreditada en
    <?= htmlspecialchars($transferencia['deposito_destino']) ?>. Confirme la recepción cuando llegue a destino.
</div>
<?php else: ?>
<div class="alert alert-success">
    <i class="bi bi-check-circle"></i> <strong>COMPLETADA</strong> — Mercadería acreditada en destino
    <?= $transferencia['recibida_at'] ? 'el ' . $transferencia['recibida_at'] : '' ?>.
</div>
<?php endif; ?>

<div class="card p-4 mb-3">
    <div class="row">
        <div class="col-md-4">
            <label class="form-label fw-bold">Depósito Origen</label>
            <div><?= htmlspecialchars($transferencia['deposito_origen']) ?></div>
        </div>
        <div class="col-md-4">
            <label class="form-label fw-bold">Depósito Destino</label>
            <div><?= htmlspecialchars($transferencia['deposito_destino']) ?></div>
        </div>
        <div class="col-md-4">
            <label class="form-label fw-bold">Estado</label>
            <div>
                <?php if ($transferencia['estado'] === 'EN_TRANSITO'): ?>
                    <span class="badge bg-warning text-dark">EN TRÁNSITO</span>
                <?php elseif ($transferencia['estado'] === 'COMPLETADA'): ?>
                    <span class="badge bg-success">COMPLETADA</span>
                <?php else: ?>
                    <span class="badge bg-danger">ANULADA</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="row mt-3">
        <div class="col-md-4">
            <label class="form-label fw-bold">Fecha</label>
            <div><?= $transferencia['created_at'] ?> (<?= htmlspecialchars($transferencia['usuario']) ?>)</div>
        </div>
        <div class="col-md-4">
            <label class="form-label fw-bold">Recibida</label>
            <div><?= $transferencia['recibida_at'] ?? 'Pendiente' ?></div>
        </div>
    </div>
    <?php if ($transferencia['observaciones']): ?>
    <div class="row mt-3">
        <div class="col-12">
            <label class="form-label fw-bold">Observaciones</label>
            <div><?= htmlspecialchars($transferencia['observaciones']) ?></div>
        </div>
    </div>
    <?php endif; ?>
</div>

<h5>Detalle</h5>
<table class="table table-bordered table-hover">
    <thead class="table-dark">
        <tr>
            <th>Tipo</th>
            <th>Ítem</th>
            <th>Lote</th>
            <th>Vencimiento</th>
            <th class="text-end">Cantidad</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($transferencia['detalle'])): ?>
        <tr><td colspan="5" class="text-center">Sin detalle</td></tr>
        <?php else: ?>
        <?php foreach ($transferencia['detalle'] as $item): ?>
        <tr>
            <td>
                <span class="badge bg-<?= $item['tipo'] === 'producto' ? 'info' : 'warning' ?>">
                    <?= $item['tipo'] === 'producto' ? 'PRODUCTO' : 'MATERIA PRIMA' ?>
                </span>
            </td>
            <td><?= htmlspecialchars($item['nombre']) ?></td>
            <td><?= htmlspecialchars($item['codigo_lote'] ?? '') ?></td>
            <td><?= $item['fecha_vencimiento'] ?? '' ?></td>
            <td class="text-end"><?= number_format($item['cantidad'], 3, ',', '.') ?></td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>

<div class="d-flex gap-2">
    <a href="<?= BASE_URL ?>/transferencias" class="btn btn-secondary">Volver</a>
    <?php if ($transferencia['estado'] === 'EN_TRANSITO'): ?>
    <a href="<?= BASE_URL ?>/transferencias/recibir/<?= $transferencia['id'] ?>" class="btn btn-success btn-confirm">Confirmar Recepción</a>
    <?php endif; ?>
    <?php if ($transferencia['estado'] !== 'ANULADA'): ?>
    <a href="<?= BASE_URL ?>/transferencias/anular/<?= $transferencia['id'] ?>" class="btn btn-danger btn-confirm">Anular</a>
    <?php endif; ?>
</div>