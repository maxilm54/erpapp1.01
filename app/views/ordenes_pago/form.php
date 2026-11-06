<?php
$orden = $orden ?? null;
$factura = $factura ?? null;
$facturas = $facturas ?? [];
$csrf = $csrf ?? Csrf::generate();

$prefijada = $factura && !empty($factura['id']);
?>
<h3><i class="bi bi-cash-coin"></i> <?= htmlspecialchars($title) ?></h3>

<?php if ($prefijada): ?>
<div class="alert alert-info d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div>
        Pagando la factura <strong><?= htmlspecialchars(($factura['tipo_fiscal'] ?? '') !== '' ? $factura['tipo_fiscal'] . ' ' : '') ?><?= htmlspecialchars($factura['numero']) ?></strong>
        de <strong><?= htmlspecialchars($factura['proveedor_nombre'] ?? '') ?></strong>.
        Saldo pendiente: <strong>$ <?= number_format((float)$factura['saldo'], 2, ',', '.') ?></strong>.
    </div>
    <a href="<?= BASE_URL ?>/ordenespago/create" class="btn btn-sm btn-outline-secondary">Elegir otra</a>
</div>
<?php endif; ?>

<form method="POST" action="<?= BASE_URL ?>/ordenespago/store" class="mt-3">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
    <?php if ($prefijada): ?>
        <input type="hidden" name="factura_compra_id" value="<?= (int)$factura['id'] ?>">
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-header"><h6 class="mb-0"><i class="bi bi-file-earmark-text"></i> Factura a pagar</h6></div>
        <div class="card-body">
            <div class="row">
                <?php if (!$prefijada): ?>
                    <div class="col-md-8 mb-3">
                        <label class="form-label fw-bold">Factura de compra *</label>
                        <select name="factura_compra_id" class="form-select" required>
                            <option value="">Seleccione una factura con saldo...</option>
                            <?php foreach ($facturas as $fc): ?>
                                <option value="<?= (int)$fc['id'] ?>" <?= (int)($orden['factura_compra_id'] ?? 0) === (int)$fc['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars(($fc['tipo_fiscal'] ?? '') !== '' ? $fc['tipo_fiscal'] . ' ' : '') ?><?= htmlspecialchars($fc['numero']) ?>
                                    — <?= htmlspecialchars($fc['proveedor_nombre']) ?>
                                    (<?= date('d/m/Y', strtotime($fc['fecha_factura'])) ?>, saldo $ <?= number_format((float)$fc['saldo'], 2, ',', '.') ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (empty($facturas)): ?>
                            <div class="form-text text-warning">No hay facturas con saldo pendiente. Registre una factura de compra primero.</div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <div class="col-md-4 mb-3">
                    <label class="form-label fw-bold">Monto a pagar *</label>
                    <input type="number" step="0.01" min="0.01" name="monto" class="form-control" required
                           value="<?= $prefijada ? number_format((float)$factura['saldo'], 2, '.', '') : '' ?>"
                           <?= $prefijada ? "max=\"" . number_format((float)$factura['saldo'], 2, '.', '') . "\"" : '' ?>>
                    <?php if ($prefijada): ?>
                        <div class="form-text">Prefijado con el saldo de la factura. Podés pagar una parte.</div>
                    <?php endif; ?>
                </div>

                <div class="col-md-4 mb-3">
                    <label class="form-label fw-bold">Medio de pago *</label>
                    <select name="medio_pago" class="form-select">
                        <option value="TRANSFERENCIA">Transferencia</option>
                        <option value="EFECTIVO">Efectivo</option>
                        <option value="CHEQUE">Cheque</option>
                        <option value="TARJETA_CREDITO">Tarjeta de crédito</option>
                        <option value="TARJETA_DEBITO">Tarjeta de débito</option>
                        <option value="OTRO">Otro</option>
                    </select>
                    <div class="form-text">La caja/banco se elige al momento de marcarla como pagada.</div>
                </div>

                <div class="col-md-4 mb-3">
                    <label class="form-label fw-bold">Fecha *</label>
                    <input type="date" name="fecha" class="form-control" required value="<?= date('Y-m-d') ?>">
                </div>

                <div class="col-md-12 mb-3">
                    <label class="form-label fw-bold">Observaciones</label>
                    <textarea name="observaciones" class="form-control" rows="2"></textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>/ordenespago" class="btn btn-secondary">Volver</a>
        <button type="submit" class="btn btn-primary" <?= (!$prefijada && empty($facturas)) ? 'disabled' : '' ?>>
            <i class="bi bi-check-lg"></i> Crear orden de pago
        </button>
    </div>
</form>
