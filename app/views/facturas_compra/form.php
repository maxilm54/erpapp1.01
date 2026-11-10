<?php
$factura = $factura ?? null;
$oc = $oc ?? null;
$proveedores = $proveedores ?? [];
$ocs = $ocs ?? [];
$monedas = $monedas ?? [];
$csrf = $csrf ?? Csrf::generate();

// Si viene una OC por URL, se prefiere el saldo (total - ya facturado).
$ocTotal = 0.0;
$ocFacturado = 0.0;
if ($oc && !empty($oc['detalle'])) {
    foreach ($oc['detalle'] as $d) {
        $ocTotal += (float)$d['pedida'] * (float)$d['precio_unitario'];
    }
}
if ($oc) {
    require_once BASE_PATH . '/app/models/Facturacompra.php';
    $ocFacturado = (new FacturaCompra())->facturadoOC((int)$oc['id']);
}
$ocSaldo = max(0, $ocTotal - $ocFacturado);
?>

<h3><i class="bi bi-receipt"></i> <?= htmlspecialchars($title) ?></h3>

<?php if ($oc): ?>
<div class="alert alert-info d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div>
        Facturando la <strong>Orden de Compra #<?= (int)$oc['id'] ?></strong> de
        <strong><?= htmlspecialchars($oc['razon_social']) ?></strong>.
        Total de la OC: <strong>$ <?= number_format($ocTotal, 2, ',', '.') ?></strong>
        <?php if ($ocFacturado > 0): ?>
            — ya facturado: <strong>$ <?= number_format($ocFacturado, 2, ',', '.') ?></strong>
        <?php endif; ?>
        — saldo por facturar: <strong>$ <?= number_format($ocSaldo, 2, ',', '.') ?></strong>.
        <?php if ($ocSaldo <= 0.02): ?>
            <span class="badge bg-success">OC completamente facturada</span>
        <?php endif; ?>
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
                        <select name="proveedor_id" id="proveedor_id" class="form-select" required>
                            <option value="">Seleccione...</option>
                            <?php foreach ($proveedores as $p): ?>
                                <option value="<?= (int)$p['id'] ?>" <?= (int)($factura['proveedor_id'] ?? 0) === (int)$p['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($p['razon_social']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Elegí el proveedor para ver sus OCs con saldo por facturar.</div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if (!$oc): ?>
    <!-- OCs que cubre la factura: una o varias (el proveedor manda una factura con muchos pedidos) -->
    <div class="card mb-4">
        <div class="card-header"><h6 class="mb-0"><i class="bi bi-diagram-3"></i> Órdenes de Compra que cubre (opcional)</h6></div>
        <div class="card-body">
            <div id="ocs_container" class="border rounded p-2 bg-light" style="max-height: 260px; overflow-y: auto;">
                <span class="text-muted small">Seleccione un proveedor para listar sus OCs con saldo pendiente.</span>
            </div>
            <div class="form-text">
                Marcá las OCs que cubre la factura y ajustá el monto asignado a cada una (por defecto, el saldo pendiente).
                Si no marcás ninguna, queda como factura sin OC (servicio u otro gasto).
            </div>
        </div>
    </div>
    <?php endif; ?>

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
                           value="<?= $oc ? number_format($ocSaldo, 2, '.', '') : '0.00' ?>">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label fw-bold">Alicuota IVA</label>
                    <select name="impuesto_id" id="alicuotaIva" class="form-select">
                        <option value="0">Sin IVA (0%)</option>
                        <?php foreach (($impuestos ?? []) as $imp): ?>
                            <?php if ((float)$imp['porcentaje'] <= 0) continue; ?>
                            <option value="<?= (int)$imp['id'] ?>" data-porcentaje="<?= (float)$imp['porcentaje'] ?>">
                                <?= htmlspecialchars($imp['nombre']) ?> (<?= rtrim(rtrim(number_format((float)$imp['porcentaje'], 2, ',', '.'), '0'), ',') ?>%)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">Comprobantes X/C: elegí "Sin IVA (0%)".</div>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label fw-bold">IVA generado</label>
                    <div class="form-control bg-light" id="ivaGenerado" style="text-align:right">$ 0,00</div>
                    <input type="hidden" name="monto_iva" id="montoIva" value="0.00">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label fw-bold">Total *</label>
                    <input type="number" step="0.01" min="0" name="monto_total" id="montoTotal" class="form-control" required value="0.00">
                    <div class="form-text">Neto + IVA, automático. Ajustalo solo si la factura difiere.</div>
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
const alicuota = document.getElementById('alicuotaIva');
const ivaGenerado = document.getElementById('ivaGenerado');

function recalcular() {
    const b = parseFloat(base.value) || 0;
    const opt = alicuota ? alicuota.selectedOptions[0] : null;
    const pct = opt && opt.value !== '' && opt.value !== '0' ? (parseFloat(opt.dataset.porcentaje) || 0) : 0;
    const montoIva = Math.round(b * pct) / 100; // 2 decimales, sin arrastre
    iva.value = montoIva.toFixed(2);
    if (ivaGenerado) {
        ivaGenerado.textContent = '$ ' + montoIva.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    total.value = (b + montoIva).toFixed(2);
    actualizarResumenOcs();
}
base.addEventListener('input', recalcular);
if (alicuota) alicuota.addEventListener('change', recalcular);

// El neto se completa con lo asignado a las OCs (montos sin IVA)
function actualizarNetoDesdeOcs() {
    if (!contOcs) return;
    const asignado = Object.values(ocsAsignadas()).reduce((a, b) => a + b, 0);
    if (asignado > 0) {
        base.value = asignado.toFixed(2);
        recalcular();
    }
}

// ===== OCs que cubre la factura (multi-OC) =====
const contOcs = document.getElementById('ocs_container');
const selProveedor = document.getElementById('proveedor_id');
const fmt = n => number_format_js(n);
function number_format_js(n) {
    return (parseFloat(n) || 0).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

async function cargarOcs() {
    const pid = selProveedor ? selProveedor.value : '';
    if (!pid) {
        contOcs.innerHTML = '<span class="text-muted small">Seleccione un proveedor para listar sus OCs con saldo pendiente.</span>';
        return;
    }
    contOcs.innerHTML = '<span class="text-muted small">Cargando OCs...</span>';
    try {
        const r = await fetch(`<?= BASE_URL ?>/facturascompra/ocs-proveedor/${pid}`);
        const ocs = await r.json();
        if (!Array.isArray(ocs) || ocs.length === 0) {
            contOcs.innerHTML = '<span class="text-muted small">Este proveedor no tiene OCs con saldo por facturar.</span>';
            return;
        }
        contOcs.innerHTML = ocs.map(o => {
            const saldo = parseFloat(o.saldo_oc) || 0;
            if (saldo <= 0.02) return '';
            return `
            <div class="d-flex align-items-center gap-2 border-bottom py-1 oc-fila" data-saldo="${saldo}">
                <div class="form-check flex-grow-1">
                    <input class="form-check-input oc-check" type="checkbox" value="${o.id}" id="oc_${o.id}">
                    <label class="form-check-label" for="oc_${o.id}">
                        OC #${o.id} — ${o.estado} — Total $ ${fmt(o.total_oc)}
                        — facturado $ ${fmt(o.total_facturado)} — saldo $ ${fmt(saldo)}
                    </label>
                </div>
                <input type="number" step="0.01" min="0" max="${saldo}" class="form-control form-control-sm oc-monto"
                       style="width: 140px" value="${saldo.toFixed(2)}" name="ocs[${o.id}]" disabled>
            </div>`;
        }).join('') || '<span class="text-muted small">Este proveedor no tiene OCs con saldo por facturar.</span>';

        contOcs.querySelectorAll('.oc-check').forEach(chk => {
            chk.addEventListener('change', () => {
                const fila = chk.closest('.oc-fila');
                const monto = fila.querySelector('.oc-monto');
                monto.disabled = !chk.checked;
                if (chk.checked && (parseFloat(monto.value) || 0) <= 0) {
                    monto.value = fila.dataset.saldo;
                }
                actualizarNetoDesdeOcs();
                actualizarResumenOcs();
            });
        });
        contOcs.querySelectorAll('.oc-monto').forEach(inp => {
            inp.addEventListener('input', () => {
                actualizarNetoDesdeOcs();
                actualizarResumenOcs();
            });
        });
    } catch (e) {
        contOcs.innerHTML = '<span class="text-danger small">Error cargando las OCs.</span>';
    }
}

function ocsAsignadas() {
    const out = {};
    if (!contOcs) return out;
    contOcs.querySelectorAll('.oc-fila').forEach(fila => {
        const chk = fila.querySelector('.oc-check');
        if (chk && chk.checked) {
            out[chk.value] = parseFloat(fila.querySelector('.oc-monto').value) || 0;
        }
    });
    return out;
}

function actualizarResumenOcs() {
    if (!contOcs) return;
    let resumen = document.getElementById('resumen_ocs');
    const asignado = Object.values(ocsAsignadas()).reduce((a, b) => a + b, 0);
    if (asignado <= 0) {
        if (resumen) resumen.remove();
        return;
    }
    if (!resumen) {
        resumen = document.createElement('div');
        resumen.id = 'resumen_ocs';
        resumen.className = 'small mt-2';
        contOcs.insertAdjacentElement('afterend', resumen);
    }
    const totalFactura = parseFloat(total.value) || 0;
    const clase = asignado > totalFactura + 0.02 ? 'text-danger fw-bold' : 'text-muted';
    resumen.className = `small mt-2 ${clase}`;
    resumen.innerHTML = `Asignado a OCs: $ ${fmt(asignado)} de un total de factura $ ${fmt(totalFactura)}`
        + (asignado > totalFactura + 0.02 ? ' — supera el total de la factura.' : '')
        + (asignado < totalFactura - 0.02 ? ` — el resto ($ ${fmt(totalFactura - asignado)}) queda sin asignar a OCs.` : '');
}

if (selProveedor) {
    selProveedor.addEventListener('change', cargarOcs);
    if (selProveedor.value) cargarOcs();
}

// Comprobantes X y C no discriminan IVA: alicuota 0
const selTipoFiscal = document.querySelector('select[name=tipo_fiscal]');
if (selTipoFiscal) {
    selTipoFiscal.addEventListener('change', () => {
        const t = selTipoFiscal.value.toUpperCase();
        if ((t === 'X' || t === 'C') && alicuota) {
            alicuota.value = '0';
            recalcular();
        }
    });
}

recalcular();

// Al enviar: solo se envian las OCs marcadas con su monto
document.querySelector('form').addEventListener('submit', function () {
    contOcs && contOcs.querySelectorAll('.oc-fila').forEach(fila => {
        const chk = fila.querySelector('.oc-check');
        const monto = fila.querySelector('.oc-monto');
        if (chk && !chk.checked) { monto.disabled = true; monto.value = ''; }
    });
});
</script>
