<div class="d-flex justify-content-between align-items-center mb-3">
    <h3><i class="bi bi-bank"></i> <?= htmlspecialchars($title) ?></h3>
    <button class="btn btn-primary" onclick="abrirModalCaja()">
        <i class="bi bi-plus-lg"></i> Nueva Caja/Banco
    </button>
</div>

<!-- Resumen de saldos -->
<div class="row mb-4">
    <?php foreach ($resumen as $tipo => $total): ?>
    <div class="col-md-3">
        <div class="card <?= $tipo === 'CAJA' ? 'bg-success' : ($tipo === 'BANCO' ? 'bg-primary' : 'bg-info') ?> text-white">
            <div class="card-body text-center">
                <h6><?= $tipo ?></h6>
                <h4>$ <?= number_format($total, 2, ',', '.') ?></h4>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    <div class="col-md-3">
        <div class="card bg-dark text-white">
            <div class="card-body text-center">
                <h6>TOTAL</h6>
                <h4>$ <?= number_format(array_sum($resumen), 2, ',', '.') ?></h4>
            </div>
        </div>
    </div>
</div>

<!-- Tabla de cajas/bancos -->
<div class="table-responsive">
<table class="table table-striped table-hover">
    <thead class="table-dark">
        <tr>
            <th>ID</th>
            <th>Nombre</th>
            <th>Tipo</th>
            <th>Banco</th>
            <th>N° Cuenta</th>
            <th>Moneda</th>
            <th class="text-end">Saldo Actual</th>
            <th>Cuenta Contable</th>
            <th>Acciones</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($cajas as $c): ?>
        <tr>
            <td><?= $c['id'] ?></td>
            <td><strong><?= htmlspecialchars($c['nombre']) ?></strong></td>
            <td>
                <span class="badge <?= $c['tipo'] === 'CAJA' ? 'bg-success' : ($c['tipo'] === 'BANCO' ? 'bg-primary' : 'bg-info') ?>">
                    <?= $c['tipo'] ?>
                </span>
            </td>
            <td><?= htmlspecialchars($c['banco'] ?? '-') ?></td>
            <td><code><?= htmlspecialchars($c['numero_cuenta'] ?? '-') ?></code></td>
            <td><?= $c['moneda'] ?></td>
            <td class="text-end fw-bold <?= $c['saldo_actual'] >= 0 ? 'text-success' : 'text-danger' ?>">
                $ <?= number_format($c['saldo_actual'], 2, ',', '.') ?>
            </td>
            <td>
                <?php if ($c['cuenta_codigo']): ?>
                    <code><?= $c['cuenta_codigo'] ?></code> <?= htmlspecialchars($c['cuenta_nombre'] ?? '') ?>
                <?php else: ?>
                    <span class="text-muted">-</span>
                <?php endif; ?>
            </td>
            <td>
                <a href="<?= BASE_URL ?>/contabilidad/caja-detalle/<?= $c['id'] ?>" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-list-ul"></i> Movimientos
                </a>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($cajas)): ?>
        <tr>
            <td colspan="9" class="text-center text-muted py-4">
                No hay cajas, bancos o fondos registrados.
            </td>
        </tr>
        <?php endif; ?>
    </tbody>
</table>
</div>

<!-- Modal Nueva Caja/Banco -->
<div class="modal fade" id="modalCaja" tabindex="-1">
<div class="modal-dialog">
<div class="modal-content">
    <form method="POST" action="<?= BASE_URL ?>/contabilidad/caja-save">
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

        <div class="modal-header">
            <h5 class="modal-title">Nueva Caja / Banco / Fondo</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
            <div class="mb-3">
                <label for="nombre" class="form-label">Nombre *</label>
                <input type="text" id="nombre" name="nombre" class="form-control" required
                       placeholder="Ej: Banco Nación, Caja Principal, Fondo Maniobra">
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="tipo" class="form-label">Tipo *</label>
                    <select id="tipo" name="tipo" class="form-select" required>
                        <option value="CAJA">Caja</option>
                        <option value="BANCO">Banco</option>
                        <option value="FONDO">Fondo</option>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="moneda" class="form-label">Moneda</label>
                    <select id="moneda" name="moneda" class="form-select">
                        <option value="ARS">ARS - Peso</option>
                        <option value="USD">USD - Dólar</option>
                    </select>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="banco" class="form-label">Banco</label>
                    <input type="text" id="banco" name="banco" class="form-control"
                           placeholder="Solo si tipo=Banco">
                </div>
                <div class="col-md-6 mb-3">
                    <label for="numero_cuenta" class="form-label">N° Cuenta</label>
                    <input type="text" id="numero_cuenta" name="numero_cuenta" class="form-control">
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="saldo_inicial" class="form-label">Saldo Inicial</label>
                    <input type="number" id="saldo_inicial" name="saldo_inicial" class="form-control"
                           step="0.01" value="0">
                </div>
                <div class="col-md-6 mb-3">
                    <label for="cuenta_contable_id" class="form-label">Cuenta Contable</label>
                    <select id="cuenta_contable_id" name="cuenta_contable_id" class="form-select">
                        <option value="">Sin vincular</option>
                        <?php
                        require_once BASE_PATH . '/app/models/CuentaContable.php';
                        $ccModel = new CuentaContable();
                        $cuentasActivas = $ccModel->getHojas();
                        foreach ($cuentasActivas as $c):
                            if (in_array($c['tipo'], ['ACTIVO'])):
                        ?>
                            <option value="<?= $c['id'] ?>">
                                <?= $c['codigo'] ?> - <?= htmlspecialchars($c['nombre']) ?>
                            </option>
                        <?php
                            endif;
                        endforeach;
                        ?>
                    </select>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-check-lg"></i> Crear
            </button>
        </div>
    </form>
</div>
</div>
</div>

<script>
function abrirModalCaja() {
    new bootstrap.Modal(document.getElementById('modalCaja')).show();
}
</script>
