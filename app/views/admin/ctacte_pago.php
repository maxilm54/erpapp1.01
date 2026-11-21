<?php
$fmt = fn($v) => number_format((float)$v, 2, ',', '.');
$hoy = $hoy ?? date('Y-m-d');
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0"><i class="bi bi-cash-stack"></i> <?= htmlspecialchars($title) ?></h3>
    <a href="<?= BASE_URL ?>/admin/ctacte" class="btn btn-secondary btn-sm">
        <i class="bi bi-arrow-left"></i> Volver
    </a>
</div>

<?php if (!empty($error)): ?>
<div class="alert alert-warning">
    <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
</div>
<?php endif; ?>

<div class="card mb-3">
    <div class="card-body">
        <form method="get" action="<?= BASE_URL ?>/admin/ctacte-pago" class="row g-2 align-items-end">
            <div class="col-md-6">
                <label class="form-label mb-0 small">Empresa con saldo pendiente</label>
                <select name="tenant_id" class="form-select" onchange="this.form.submit()">
                    <option value="">Seleccionar empresa...</option>
                    <?php foreach ($tenants as $t): ?>
                    <option value="<?= (int)$t['id'] ?>" <?= (int)$tenantId === (int)$t['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($t['nombre']) ?> (<?= htmlspecialchars($t['dbname']) ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>
</div>

<?php if ($tenantId <= 0): ?>
    <div class="alert alert-secondary">
        <i class="bi bi-info-circle"></i> Elegi una empresa para ver sus registros pendientes.
        <?php if (empty($tenants)): ?>
            <div class="mt-1">No hay empresas con saldo pendiente.</div>
        <?php endif; ?>
    </div>
<?php elseif (empty($pendientes)): ?>
    <div class="alert alert-success">
        <i class="bi bi-check-circle"></i> La empresa no tiene registros con saldo pendiente.
    </div>
<?php else: ?>
<form method="POST" action="<?= BASE_URL ?>/admin/ctacte-pagar" class="card shadow-sm">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
    <input type="hidden" name="tenant_id" value="<?= (int)$tenantId ?>">

    <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
            <i class="bi bi-building"></i> <?= htmlspecialchars($tenantNombre ?: 'Empresa') ?>
        </h5>
        <span class="badge bg-light text-dark fs-6">
            Saldo total: $<?= $fmt($totalPendiente) ?>
        </span>
    </div>

    <div class="card-body">
        <p class="small text-muted mb-2">
            Marca los registros a saldar. El pago se aplica siempre del vencimiento
            <strong>mas viejo al mas nuevo</strong>, aunque los marques en otro orden.
        </p>

        <div class="table-responsive mb-3">
            <table class="table table-sm table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th style="width:40px">
                            <input type="checkbox" class="form-check-input" id="checkTodos" checked
                                   title="Marcar / desmarcar todos">
                        </th>
                        <th>Periodo</th>
                        <th>Vencimiento</th>
                        <th class="text-end">Total</th>
                        <th class="text-end">Pagado</th>
                        <th class="text-end">Saldo</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($pendientes as $p):
                    $total = (float)$p['sistema'] + (float)$p['soporte'] + (float)$p['desarrollo'];
                    $saldo = (float)$p['saldo_pendiente'];
                    $vencido = $p['fecha_vencimiento'] < $hoy;
                    $partes = explode('-', $p['periodo']);
                    $periodoTxt = ($partes[1] ?? '') . '/' . ($partes[0] ?? '');
                ?>
                    <tr>
                        <td>
                            <input type="checkbox" class="form-check-input reg-check" name="registros[]"
                                   value="<?= (int)$p['id'] ?>" data-saldo="<?= $saldo ?>" checked>
                        </td>
                        <td class="fw-semibold"><?= htmlspecialchars($periodoTxt) ?></td>
                        <td>
                            <?= date('d/m/Y', strtotime($p['fecha_vencimiento'])) ?>
                            <?php if ($vencido): ?>
                                <span class="badge bg-danger ms-1">Vencido</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">$<?= $fmt($total) ?></td>
                        <td class="text-end text-success">$<?= $fmt($p['pago'] ?? 0) ?></td>
                        <td class="text-end fw-bold text-warning">$<?= $fmt($saldo) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot class="table-light">
                    <tr>
                        <th colspan="5" class="text-end">Total seleccionado:</th>
                        <th class="text-end" id="totalSeleccionado">$<?= $fmt($totalPendiente) ?></th>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Fecha del pago *</label>
                <input type="date" name="fecha" class="form-control" value="<?= htmlspecialchars($hoy) ?>" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Monto *</label>
                <div class="input-group">
                    <span class="input-group-text">$</span>
                    <input type="number" name="monto" id="monto" step="0.01" min="0.01" class="form-control"
                           placeholder="0.00" required>
                    <button class="btn btn-outline-secondary" type="button" id="btnTotal" title="Usar el total seleccionado">
                        <i class="bi bi-arrow-down-square"></i>
                    </button>
                </div>
                <div class="form-text">No puede superar el total seleccionado.</div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Observaciones</label>
                <input type="text" name="obs" class="form-control" maxlength="500"
                       placeholder="Forma de pago, referencia, acuerdo...">
            </div>
        </div>

        <div class="alert alert-info py-2 mt-3 mb-0 small">
            <i class="bi bi-info-circle"></i>
            Ejemplo: 3 registros de $100 y un pago de $250 dejan saldados los dos mas viejos
            y el tercero con $50 pendientes.
        </div>
    </div>

    <div class="card-footer d-flex justify-content-end">
        <a href="<?= BASE_URL ?>/admin/ctacte" class="btn btn-secondary me-2">Cancelar</a>
        <button class="btn btn-success">
            <i class="bi bi-cash-coin"></i> Registrar pago
        </button>
    </div>
</form>

<script>
(function () {
    const checks = Array.from(document.querySelectorAll('.reg-check'));
    const todos = document.getElementById('checkTodos');
    const totalSel = document.getElementById('totalSeleccionado');
    const monto = document.getElementById('monto');
    const btnTotal = document.getElementById('btnTotal');
    const fmt = (n) => n.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    function totalSeleccionado() {
        return checks.reduce((acc, chk) => acc + (chk.checked ? parseFloat(chk.dataset.saldo || '0') : 0), 0);
    }

    function refrescar() {
        totalSel.textContent = '$' + fmt(totalSeleccionado());
    }

    checks.forEach((chk) => chk.addEventListener('change', refrescar));
    refrescar();

    if (todos) {
        todos.addEventListener('change', function () {
            checks.forEach((chk) => { chk.checked = todos.checked; });
            refrescar();
        });
    }

    if (btnTotal) {
        btnTotal.addEventListener('click', function () {
            monto.value = totalSeleccionado().toFixed(2);
        });
    }
})();
</script>
<?php endif; ?>
