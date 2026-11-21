<?php
$esEdicion = !empty($registro);
$accion = $esEdicion
    ? BASE_URL . '/admin/ctacte-edit/' . (int)$registro['id']
    : BASE_URL . '/admin/ctacte-create';

$valorFecha = function (string $clave, string $defecto) use ($registro): string {
    if ($registro && !empty($registro[$clave])) {
        return substr((string)$registro[$clave], 0, 10);
    }
    return $defecto;
};

$valorMonto = fn(string $clave) => number_format((float)($registro[$clave] ?? 0), 2, '.', '');

$periodoForm = $esEdicion ? (string)$registro['periodo'] : (string)$periodo;
$fechaAltaForm = $valorFecha('fecha_alta', $periodoForm . '-01');
$vencimientoForm = $valorFecha('fecha_vencimiento', $periodoForm . '-10');
$totalActual = $esEdicion
    ? (float)$registro['sistema'] + (float)$registro['soporte'] + (float)$registro['desarrollo']
    : 0.0;
$pagoActual = $esEdicion ? (float)($registro['pago'] ?? 0) : 0.0;
$saldoActual = $esEdicion ? (float)$registro['saldo_pendiente'] : 0.0;
$fmt = fn($v) => number_format((float)$v, 2, ',', '.');
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0"><i class="bi bi-cash-coin"></i> <?= htmlspecialchars($title) ?></h3>
    <a href="<?= BASE_URL ?>/admin/ctacte" class="btn btn-secondary btn-sm">
        <i class="bi bi-arrow-left"></i> Volver
    </a>
</div>

<?php if ($esEdicion): ?>
<div class="alert alert-info py-2">
    <strong><?= htmlspecialchars($registro['tenant_nombre']) ?></strong>
    &mdash; periodo <strong><?= htmlspecialchars($registro['periodo']) ?></strong>
    <span class="ms-3">Pagado: <strong>$<?= $fmt($pagoActual) ?></strong></span>
    <span class="ms-3">Saldo: <strong>$<?= $fmt($saldoActual) ?></strong></span>
    <div class="small mt-1">El pago acumulado no se edita desde aca: se registra desde <em>Registrar pago</em>.</div>
</div>
<?php endif; ?>

<form method="POST" action="<?= $accion ?>" class="card p-4 col-md-8 mx-auto shadow-sm">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">

    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label">Empresa *</label>
            <?php if ($esEdicion): ?>
                <input type="text" class="form-control" value="<?= htmlspecialchars($registro['tenant_nombre']) ?>" disabled>
            <?php else: ?>
                <select name="tenant_id" class="form-select" required>
                    <option value="">Seleccionar...</option>
                    <?php foreach ($tenants as $t): ?>
                    <option value="<?= (int)$t['id'] ?>" <?= (int)$preselect === (int)$t['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($t['nombre']) ?> (<?= htmlspecialchars($t['dbname']) ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>
        </div>

        <div class="col-md-3">
            <label class="form-label">Periodo *</label>
            <?php if ($esEdicion): ?>
                <input type="text" class="form-control" value="<?= htmlspecialchars($periodoForm) ?>" disabled>
            <?php else: ?>
                <input type="month" name="periodo" id="periodo" class="form-control" value="<?= htmlspecialchars($periodoForm) ?>" required>
            <?php endif; ?>
        </div>

        <div class="col-md-3">
            <label class="form-label">Fecha de alta</label>
            <input type="date" name="fecha_alta" id="fecha_alta" class="form-control" value="<?= htmlspecialchars($fechaAltaForm) ?>">
        </div>

        <div class="col-md-3">
            <label class="form-label">Fecha de vencimiento</label>
            <input type="date" name="fecha_vencimiento" id="fecha_vencimiento" class="form-control" value="<?= htmlspecialchars($vencimientoForm) ?>">
            <div class="form-text">Por defecto el dia 10 del periodo.</div>
        </div>

        <div class="col-md-3">
            <label class="form-label">Sistema</label>
            <input type="number" name="sistema" step="0.01" min="0" class="form-control monto" value="<?= $valorMonto('sistema') ?>">
        </div>

        <div class="col-md-3">
            <label class="form-label">Soporte</label>
            <input type="number" name="soporte" step="0.01" min="0" class="form-control monto" value="<?= $valorMonto('soporte') ?>">
        </div>

        <div class="col-md-3">
            <label class="form-label">Desarrollo</label>
            <input type="number" name="desarrollo" step="0.01" min="0" class="form-control monto" value="<?= $valorMonto('desarrollo') ?>">
        </div>

        <div class="col-md-3">
            <label class="form-label">Total</label>
            <div class="form-control bg-light fw-bold" id="totalPreview">$<?= $fmt($totalActual) ?></div>
            <div class="form-text">Sistema + Soporte + Desarrollo.</div>
        </div>

        <div class="col-12">
            <label class="form-label">Observaciones</label>
            <textarea name="obs" class="form-control" rows="3" maxlength="500"
                      placeholder="Detalle del periodo, ajustes, acuerdos..."><?= htmlspecialchars($registro['obs'] ?? '') ?></textarea>
        </div>
    </div>

    <div class="d-flex justify-content-end mt-4">
        <a href="<?= BASE_URL ?>/admin/ctacte" class="btn btn-secondary me-2">Cancelar</a>
        <button class="btn btn-success"><i class="bi bi-save"></i> Guardar</button>
    </div>
</form>

<script>
(function () {
    const montos = document.querySelectorAll('.monto');
    const preview = document.getElementById('totalPreview');
    const periodo = document.getElementById('periodo');
    const fechaAlta = document.getElementById('fecha_alta');
    const vencimiento = document.getElementById('fecha_vencimiento');
    const fmt = (n) => n.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    function actualizarTotal() {
        let total = 0;
        montos.forEach((input) => {
            const v = parseFloat(input.value);
            if (!isNaN(v)) total += v;
        });
        preview.textContent = '$' + fmt(total);
    }

    montos.forEach((input) => input.addEventListener('input', actualizarTotal));
    actualizarTotal();

    // Al elegir el mes, propone alta dia 1 y vencimiento dia 10 (solo si estan vacios).
    if (periodo) {
        periodo.addEventListener('change', function () {
            if (!periodo.value) return;
            if (fechaAlta && !fechaAlta.value) fechaAlta.value = periodo.value + '-01';
            if (vencimiento && !vencimiento.value) vencimiento.value = periodo.value + '-10';
        });
    }
})();
</script>
