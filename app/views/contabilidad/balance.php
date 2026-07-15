<div class="mb-3">
    <a href="<?= BASE_URL ?>/contabilidad/cajas" class="text-decoration-none">
        <i class="bi bi-arrow-left"></i> Volver
    </a>
</div>

<h3><i class="bi bi-balance-scale"></i> <?= htmlspecialchars($title) ?></h3>

<!-- Selector de período -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="<?= BASE_URL ?>/contabilidad/balance" class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label form-label-sm">Desde</label>
                <input type="date" name="fecha_desde" class="form-control form-control-sm"
                       value="<?= htmlspecialchars($fechaDesde) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label form-label-sm">Hasta</label>
                <input type="date" name="fecha_hasta" class="form-control form-control-sm"
                       value="<?= htmlspecialchars($fechaHasta) ?>">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-search"></i> Calcular</button>
            </div>
        </form>
    </div>
</div>

<div class="row">
    <!-- ACTIVO -->
    <div class="col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">ACTIVO</h5>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <tbody>
                        <?php foreach ($activo as $item): ?>
                        <tr>
                            <td><code><?= htmlspecialchars($item['cuenta']['codigo']) ?></code></td>
                            <td><?= htmlspecialchars($item['cuenta']['nombre']) ?></td>
                            <td class="text-end">$ <?= number_format($item['saldo'], 2, ',', '.') ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($activo)): ?>
                        <tr><td colspan="3" class="text-muted text-center">Sin datos</td></tr>
                        <?php endif; ?>
                    </tbody>
                    <tfoot class="table-primary">
                        <tr>
                            <th colspan="2">TOTAL ACTIVO</th>
                            <th class="text-end">$ <?= number_format($totalActivo, 2, ',', '.') ?></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <!-- PASIVO -->
    <div class="col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-header bg-danger text-white">
                <h5 class="mb-0">PASIVO</h5>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <tbody>
                        <?php foreach ($pasivo as $item): ?>
                        <tr>
                            <td><code><?= htmlspecialchars($item['cuenta']['codigo']) ?></code></td>
                            <td><?= htmlspecialchars($item['cuenta']['nombre']) ?></td>
                            <td class="text-end">$ <?= number_format($item['saldo'], 2, ',', '.') ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($pasivo)): ?>
                        <tr><td colspan="3" class="text-muted text-center">Sin datos</td></tr>
                        <?php endif; ?>
                    </tbody>
                    <tfoot class="table-danger">
                        <tr>
                            <th colspan="2">TOTAL PASIVO</th>
                            <th class="text-end">$ <?= number_format($totalPasivo, 2, ',', '.') ?></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- PATRIMONIO -->
<div class="card mb-4">
    <div class="card-header bg-dark text-white">
        <h5 class="mb-0">PATRIMONIO NETO</h5>
    </div>
    <div class="card-body p-0">
        <table class="table table-sm mb-0">
            <tbody>
                <?php foreach ($patrimonio as $item): ?>
                <tr>
                    <td><code><?= htmlspecialchars($item['cuenta']['codigo']) ?></code></td>
                    <td><?= htmlspecialchars($item['cuenta']['nombre']) ?></td>
                    <td class="text-end">$ <?= number_format($item['saldo'], 2, ',', '.') ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($patrimonio)): ?>
                <tr><td colspan="3" class="text-muted text-center">Sin datos</td></tr>
                <?php endif; ?>
            </tbody>
            <tfoot class="table-dark">
                <tr>
                    <th colspan="2">TOTAL PATRIMONIO</th>
                    <th class="text-end">$ <?= number_format($totalPatrimonio, 2, ',', '.') ?></th>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<!-- Ecuación contable -->
<div class="row">
    <div class="col-md-4">
        <div class="card bg-primary text-white text-center">
            <div class="card-body">
                <h6>ACTIVO</h6>
                <h4>$ <?= number_format($totalActivo, 2, ',', '.') ?></h4>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card bg-danger text-white text-center">
            <div class="card-body">
                <h6>PASIVO</h6>
                <h4>$ <?= number_format($totalPasivo, 2, ',', '.') ?></h4>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card bg-dark text-white text-center">
            <div class="card-body">
                <h6>PATRIMONIO</h6>
                <h4>$ <?= number_format($totalPatrimonio, 2, ',', '.') ?></h4>
            </div>
        </div>
    </div>
</div>
<div class="text-center mt-2 mb-4">
    <strong>ACTIVO ($ <?= number_format($totalActivo, 2, ',', '.') ?>) =
    PASIVO ($ <?= number_format($totalPasivo, 2, ',', '.') ?>) +
    PATRIMONIO ($ <?= number_format($totalPatrimonio, 2, ',', '.') ?>)</strong>
</div>
