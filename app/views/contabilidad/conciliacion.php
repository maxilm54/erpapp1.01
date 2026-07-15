<div class="mb-3">
    <a href="<?= BASE_URL ?>/contabilidad/cajas" class="text-decoration-none">
        <i class="bi bi-arrow-left"></i> Volver a Cajas
    </a>
</div>

<h3><i class="bi bi-check2-all"></i> <?= htmlspecialchars($title) ?></h3>

<!-- Selector de caja/banco -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="<?= BASE_URL ?>/contabilidad/conciliacion" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label">Caja / Banco</label>
                <select name="caja_id" class="form-select" required>
                    <option value="">Seleccionar...</option>
                    <?php foreach ($cajas as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $cajaId == $c['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['nombre']) ?> (<?= $c['tipo'] ?>) - Saldo: $ <?= number_format($c['saldo_actual'], 2, ',', '.') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i> Verificar</button>
            </div>
        </form>
    </div>
</div>

<?php if ($cajaId): ?>
<!-- Movimientos no conciliados -->
<div class="card mb-4">
    <div class="card-header bg-warning text-dark">
        <h5 class="mb-0">Movimientos No Conciliados (<?= count($movimientos) ?>)</h5>
    </div>
    <div class="card-body p-0">
        <?php if (!empty($movimientos)): ?>
        <table class="table table-sm table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th><input type="checkbox" id="checkAll"></th>
                    <th>#</th>
                    <th>Fecha</th>
                    <th>Tipo</th>
                    <th>Descripción</th>
                    <th class="text-end">Monto</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($movimientos as $m): ?>
                <tr>
                    <td><input type="checkbox" class="check-mov" data-id="<?= $m['id'] ?>" data-monto="<?= $m['monto'] ?>"></td>
                    <td><?= $m['id'] ?></td>
                    <td><?= date('d/m/Y', strtotime($m['fecha'])) ?></td>
                    <td>
                        <span class="badge <?= $m['tipo'] === 'INGRESO' ? 'bg-success' : 'bg-danger' ?>">
                            <?= $m['tipo'] ?>
                        </span>
                    </td>
                    <td><?= htmlspecialchars($m['descripcion'] ?? '-') ?></td>
                    <td class="text-end">$ <?= number_format($m['monto'], 2, ',', '.') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="card-body text-center text-muted">
            No hay movimientos pendientes de conciliación.
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Conciliaciones anteriores -->
<?php if (!empty($conciliaciones)): ?>
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">Conciliaciones Anteriores</h5>
    </div>
    <div class="card-body p-0">
        <table class="table table-sm mb-0">
            <thead class="table-light">
                <tr>
                    <th>#</th>
                    <th>Fecha</th>
                    <th class="text-end">Saldo Banco</th>
                    <th class="text-end">Saldo Sistema</th>
                    <th class="text-end">Diferencia</th>
                    <th>Estado</th>
                    <th>Registrado por</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($conciliaciones as $conc): ?>
                <tr>
                    <td><?= $conc['id'] ?></td>
                    <td><?= date('d/m/Y', strtotime($conc['fecha_conciliacion'])) ?></td>
                    <td class="text-end">$ <?= number_format($conc['saldo_segun_banco'], 2, ',', '.') ?></td>
                    <td class="text-end">$ <?= number_format($conc['saldo_segun_sistema'], 2, ',', '.') ?></td>
                    <td class="text-end <?= abs($conc['diferencia']) < 0.01 ? 'text-success' : 'text-danger' ?>">
                        $ <?= number_format($conc['diferencia'], 2, ',', '.') ?>
                    </td>
                    <td>
                        <span class="badge <?= $conc['estado'] === 'CONCILIADA' ? 'bg-success' : 'bg-warning' ?>">
                            <?= $conc['estado'] ?>
                        </span>
                    </td>
                    <td><?= htmlspecialchars($conc['usuario_nombre']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
<?php endif; ?>

<script>
document.getElementById('checkAll')?.addEventListener('change', function() {
    document.querySelectorAll('.check-mov').forEach(cb => cb.checked = this.checked);
});
</script>
