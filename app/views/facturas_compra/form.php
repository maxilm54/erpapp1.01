<?php
$factura = $factura ?? null;
$oc = $oc ?? null;
$proveedores = $proveedores ?? [];
$ocs = $ocs ?? [];
$monedas = $monedas ?? [];
$csrf = $csrf ?? Csrf::generate();

// Si viene una OC por URL, se fija el proveedor y se prefiere el total.
$ocTotal = 0.0;
if ($oc && !empty($oc['detalle'])) {
    foreach ($oc['detalle'] as $d) {
        $ocTotal += (float)$d['pedida'] * (float)$d['precio_unitario'];
    }
}
?>

<h3><i class="bi bi-receipt"></i> <?= htmlspecialchars($title) ?></h3>

<?php if ($oc): ?>
<div class="alert alert-info d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div>
        Facturando la <strong>Orden de Compra #<?= (int)$oc['id'] ?></strong> de
        <strong><?= htmlspecialchars($oc['razon_social']) ?></strong>.
        Total de la OC: <strong>$ <?= number_format($ocTotal, 2, ',', '.') ?></strong>.
    </div>
    <a href="<?= BASE_URL ?>/facturascompra/create" class="btn btn-sm btn-outline-secondary">Quitar OC</a>
</div>
<?php endif; ?>

<form method="POST" action="<?= BASE_URL ?>/facturascompra/store" class="mt-3">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
    <?php if ($oc): ?>
        <input type="hidden" name="orden_compra_id" value="<?= (int)$oc['id'] ?>">
        <input type="hidden" name="proveedor_id" value="<?= (int)$oc['proveedor_id'] ?>">
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-header"><h6 class="mb-0"><i class="bi bi-person-badge"></i> Datos del proveedor</h6></div>
        <div class="card-body">
            <div class="row">
                <?php if ($oc): ?>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Proveedor</label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($oc['razon_social']) ?>" readonly>
                    </div>
                <?php else: ?>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Proveedor *</label>
                        <select name="proveedor_id" class="form-select" required>
                            <option value="">Seleccione...</option>
                            <?php foreach ($proveedores as $p): ?>
                                <option value="<?= (int)$p['id'] ?>" <?= (int)($factura['proveedor_id'] ?? 0) === (int)$p['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($p['razon_social']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Orden de Compra (opcional)</label>
                        <select name="orden_compra_id" class="form-select">
                            <option value="">Sin OC (servicio u otro gasto)</option>
                            <?php foreach ($ocs as $o): ?>
                                <option value="<?= (int)$o['id'] ?>" <?= (int)($factura['orden_compra_id'] ?? 0) === (int)$o['id'] ? 'selected' : '' ?>>
                                    OC #<?= (int)$o['id'] ?> — <?= htmlspecialchars($o['proveedor_nombre']) ?>
                                    (<?= htmlspecialchars($o['estado']) ?>, saldo $ <?= number_format((float)$o['saldo_oc'], 2, ',', '.') ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header"><h6 class="mb-0"><i class="bi bi-file-earmark-text"></i> Datos de la factura</h6></div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-3 mb-3">
                    <label class="form-label fw-bold">Tipo</label>
                    <select name="tipo_fiscal" class="form-select">
                        <option value="">Sin tipo</option>
                        <option value="A">A</option>
                        <option value="B">B</option>
                        <option value="C">C</option>
                        <option value="M">M</option>
                        <option value="X">X</option>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label fw-bold">Número *</label>
                    <input type="text" name="numero" class="form-control" maxlength="50" required
                           value="<?= htmlspecialchars($factura['numero'] ?? '') ?>"
                           placeholder="Ej: 0001-00012345">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label fw-bold">Fecha de factura *</label>
                    <input type="date" name="fecha_factura" class="form-control" required
                           value="<?= htmlspecialchars($factura['fecha_factura'] ?? date('Y-m-d')) ?>">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label fw-bold">Vencimiento (opcional)</label>
                    <input type="date" name="fecha_vencimiento" class="form-control"
                           value="<?= htmlspecialchars($factura['fecha_vencimiento'] ?? '') ?>">
                </div>

                <div class="col-md-3 mb-3">
                    <label class="form-label fw-bold">Moneda *</label>
                    <select name="moneda" class="form-select">
                        <?php $monedaDefault = (int)($monedaDefault ?? 1); ?>
                        <?php foreach ($monedas as $m): ?>
                            <option value="<?= (int)$m['id_moned'] ?>" <?= (int)($factura['moneda'] ?? $monedaDefault) === (int)$m['id_moned'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($m['nombre']) ?> (<?= htmlspecialchars($m['simbolo']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label fw-bold">Neto gravado</label>
                    <input type="number" step="0.01" min="0" name="monto_base" id="montoBase" class="form-control"
                           value="<?= $oc ? number_format($ocTotal, 2, '.', '') : '0.00' ?>">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label fw-bold">IVA</label>
                    <input type="number" step="0.01" min="0" name="monto_iva" id="montoIva" class="form-control" value="0.00">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label fw-bold">Total *</label>
                    <input type="number" step="0.01" min="0" name="monto_total" id="montoTotal" class="form-control" required value="0.00">
                    <div class="form-text">Se calcula como neto + IVA. Ajustalo si difiere.</div>
                </div>

                <div class="col-md-12 mb-3">
                    <label class="form-label fw-bold">Observaciones</label>
                    <textarea name="observaciones" class="form-control" rows="2"><?= htmlspecialchars($factura['observaciones'] ?? '') ?></textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>/facturascompra" class="btn btn-secondary">Volver</a>
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-check-lg"></i> Registrar factura
        </button>
    </div>
</form>

<script>
const base = document.getElementById('montoBase');
const iva = document.getElementById('montoIva');
const total = document.getElementById('montoTotal');
function recalcular() {
    const b = parseFloat(base.value) || 0;
    const i = parseFloat(iva.value) || 0;
    total.value = (b + i).toFixed(2);
}
base.addEventListener('input', recalcular);
iva.addEventListener('input', recalcular);
recalcular();
</script>
