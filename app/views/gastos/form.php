<?php
$isEdit = !empty($gasto);
$action = $isEdit ? BASE_URL . "/gastos/update/{$gasto['id']}" : BASE_URL . '/gastos/store';
?>

<div class="mb-3">
    <a href="<?= BASE_URL ?>/gastos" class="text-decoration-none">
        <i class="bi bi-arrow-left"></i> Volver a Gastos
    </a>
</div>

<h3><i class="bi bi-wallet2"></i> <?= htmlspecialchars($title) ?></h3>

<form method="POST" action="<?= $action ?>" class="mt-3" id="formGasto">
    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

    <div class="row">
        <!-- Fecha -->
        <div class="col-md-3 mb-3">
            <label for="fecha" class="form-label">Fecha *</label>
            <input type="date" id="fecha" name="fecha" class="form-control" required
                   value="<?= htmlspecialchars($gasto['fecha'] ?? date('Y-m-d')) ?>">
        </div>

        <!-- Categoría -->
        <div class="col-md-3 mb-3">
            <label for="categoria" class="form-label">Categoría *</label>
            <select id="categoria" name="categoria" class="form-select" required>
                <option value="">Seleccionar...</option>
                <?php
                $cats = ['PROVEEDORES','SUELDOS','SERVICIOS','ALQUILER','IMPUESTOS','OTROS'];
                $selectedCat = $gasto['categoria'] ?? '';
                foreach ($cats as $cat):
                ?>
                    <option value="<?= $cat ?>" <?= $selectedCat === $cat ? 'selected' : '' ?>>
                        <?= $cat ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Medio de pago -->
        <div class="col-md-3 mb-3">
            <label for="medio_pago" class="form-label">Medio de Pago</label>
            <select id="medio_pago" name="medio_pago" class="form-select">
                <?php
                $medios = ['TRANSFERENCIA','EFECTIVO','TARJETA_CREDITO','TARJETA_DEBITO','CHEQUE','OTRO'];
                $selectedMedio = $gasto['medio_pago'] ?? 'TRANSFERENCIA';
                foreach ($medios as $m):
                ?>
                    <option value="<?= $m ?>" <?= $selectedMedio === $m ? 'selected' : '' ?>>
                        <?= str_replace('_', ' ', $m) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Monto -->
        <div class="col-md-3 mb-3">
            <label for="monto_total" class="form-label">Monto Total *</label>
            <input type="number" id="monto_total" name="monto_total" class="form-control"
                   step="0.01" min="0.01" required
                   value="<?= htmlspecialchars($gasto['monto_total'] ?? '') ?>">
            <div id="saldoInfo" class="form-text text-muted" style="display:none;"></div>
        </div>
    </div>

    <div class="row">
        <!-- Descripción -->
        <div class="col-md-6 mb-3">
            <label for="descripcion" class="form-label">Descripción *</label>
            <input type="text" id="descripcion" name="descripcion" class="form-control" required
                   maxlength="255"
                   value="<?= htmlspecialchars($gasto['descripcion'] ?? '') ?>"
                   placeholder="Ej: Pago proveedor XYZ, Servicio de luz, etc.">
        </div>

        <!-- Comprobante -->
        <div class="col-md-3 mb-3">
            <label for="comprobante" class="form-label">N° Comprobante</label>
            <input type="text" id="comprobante" name="comprobante" class="form-control"
                   value="<?= htmlspecialchars($gasto['comprobante'] ?? '') ?>"
                   placeholder="Factura, recibo, etc.">
        </div>

        <!-- Orden de Compra -->
        <div class="col-md-3 mb-3">
            <label for="orden_compra_id" class="form-label">Orden de Compra</label>
            <select id="orden_compra_id" name="orden_compra_id" class="form-select">
                <option value="">Sin vincular</option>
                <?php foreach ($ocPendientes as $oc): ?>
                    <option value="<?= $oc['id'] ?>"
                            data-total="<?= $oc['total_oc'] ?>"
                            data-pagado="<?= $oc['total_pagado'] ?>"
                            data-saldo="<?= $oc['saldo_pendiente'] ?>"
                        <?= (int)($gasto['orden_compra_id'] ?? 0) === (int)$oc['id'] ? 'selected' : '' ?>>
                        OC #<?= $oc['id'] ?> - <?= htmlspecialchars($oc['proveedor_nombre'] ?? 'S/Proveedor') ?>
                        | Saldo: $ <?= number_format($oc['saldo_pendiente'], 2, ',', '.') ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <div id="ocDetalle" class="form-text" style="display:none;"></div>
        </div>
    </div>

    <!-- Observaciones -->
    <div class="mb-3">
        <label for="observaciones" class="form-label">Observaciones</label>
        <textarea id="observaciones" name="observaciones" class="form-control" rows="3"
                  placeholder="Notas adicionales..."><?= htmlspecialchars($gasto['observaciones'] ?? '') ?></textarea>
    </div>

    <!-- Botones -->
    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary" id="btnSubmit">
            <i class="bi bi-check-lg"></i> <?= $isEdit ? 'Actualizar' : 'Registrar Gasto' ?>
        </button>
        <a href="<?= BASE_URL ?>/gastos" class="btn btn-outline-secondary">Cancelar</a>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const selectOC = document.getElementById('orden_compra_id');
    const inputMonto = document.getElementById('monto_total');
    const saldoInfo = document.getElementById('saldoInfo');
    const ocDetalle = document.getElementById('ocDetalle');
    const form = document.getElementById('formGasto');

    function formatMoney(val) {
        return '$ ' + val.toLocaleString('es-AR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    }

    function actualizarInfoOC() {
        const selected = selectOC.options[selectOC.selectedIndex];
        if (!selected || !selected.value) {
            saldoInfo.style.display = 'none';
            ocDetalle.style.display = 'none';
            inputMonto.removeAttribute('max');
            inputMonto.placeholder = '';
            return;
        }

        const total = parseFloat(selected.dataset.total) || 0;
        const pagado = parseFloat(selected.dataset.pagado) || 0;
        const saldo = parseFloat(selected.dataset.saldo) || 0;

        ocDetalle.innerHTML = '<strong>Total OC:</strong> ' + formatMoney(total) +
            ' &nbsp;|&nbsp; <strong>Pagado:</strong> ' + formatMoney(pagado) +
            ' &nbsp;|&nbsp; <strong>Saldo:</strong> <span class="text-success fw-bold">' + formatMoney(saldo) + '</span>';
        ocDetalle.style.display = 'block';

        saldoInfo.textContent = 'Saldo pendiente de esta OC: ' + formatMoney(saldo) + '. El monto no puede exceder este valor.';
        saldoInfo.style.display = 'block';
        saldoInfo.className = 'form-text text-info fw-bold';

        inputMonto.setAttribute('max', saldo);
        inputMonto.placeholder = 'Máximo ' + formatMoney(saldo);

        const montoActual = parseFloat(inputMonto.value) || 0;
        if (montoActual > saldo) {
            inputMonto.value = saldo.toFixed(2);
        }
    }

    selectOC.addEventListener('change', actualizarInfoOC);

    form.addEventListener('submit', function(e) {
        const selected = selectOC.options[selectOC.selectedIndex];
        if (selected && selected.value) {
            const saldo = parseFloat(selected.dataset.saldo) || 0;
            const monto = parseFloat(inputMonto.value) || 0;
            if (monto > saldo) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Monto excedido',
                    text: 'El monto (' + formatMoney(monto) + ') excede el saldo pendiente de la OC (' + formatMoney(saldo) + ').'
                });
                return false;
            }
            if (monto <= 0) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Monto inválido',
                    text: 'El monto debe ser mayor a cero.'
                });
                return false;
            }
        }
    });

    actualizarInfoOC();
});
</script>
