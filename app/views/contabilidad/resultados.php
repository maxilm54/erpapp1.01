<div class="mb-3">
    <a href="<?= BASE_URL ?>/contabilidad/cajas" class="text-decoration-none">
        <i class="bi bi-arrow-left"></i> Volver
    </a>
</div>

<h3><i class="bi bi-graph-up"></i> <?= htmlspecialchars($title) ?></h3>

<!-- Selector de período -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="<?= BASE_URL ?>/contabilidad/resultados" class="row g-2 align-items-end">
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
    <!-- INGRESOS -->
    <div class="col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0">INGRESOS</h5>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <tbody>
                        <?php foreach ($ingresos as $item): ?>
                        <tr>
                            <td><code><?= htmlspecialchars($item['cuenta']['codigo']) ?></code></td>
                            <td><?= htmlspecialchars($item['cuenta']['nombre']) ?></td>
                            <td class="text-end text-success">$ <?= number_format($item['saldo'], 2, ',', '.') ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($ingresos)): ?>
                        <tr><td colspan="3" class="text-muted text-center">Sin ingresos</td></tr>
                        <?php endif; ?>
                    </tbody>
                    <tfoot class="table-success">
                        <tr>
                            <th colspan="2">TOTAL INGRESOS</th>
                            <th class="text-end">$ <?= number_format($totalIngresos, 2, ',', '.') ?></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <!-- EGRESOS -->
    <div class="col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-header bg-danger text-white">
                <h5 class="mb-0">EGRESOS / COSTOS</h5>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <tbody>
                        <?php foreach ($egresos as $item): ?>
                        <tr>
                            <td><code><?= htmlspecialchars($item['cuenta']['codigo']) ?></code></td>
                            <td><?= htmlspecialchars($item['cuenta']['nombre']) ?></td>
                            <td class="text-end text-danger">$ <?= number_format(abs($item['saldo']), 2, ',', '.') ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($egresos)): ?>
                        <tr><td colspan="3" class="text-muted text-center">Sin egresos</td></tr>
                        <?php endif; ?>
                    </tbody>
                    <tfoot class="table-danger">
                        <tr>
                            <th colspan="2">TOTAL EGRESOS</th>
                            <th class="text-end">$ <?= number_format($totalEgresos, 2, ',', '.') ?></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Resultado -->
<div class="card mb-4 <?= $resultado >= 0 ? 'border-success' : 'border-danger' ?>">
    <div class="card-body text-center">
        <h5>Resultado del Período</h5>
        <h2 class="<?= $resultado >= 0 ? 'text-success' : 'text-danger' ?>">
            <?= $resultado >= 0 ? 'GANANCIA' : 'PÉRDIDA' ?>: $ <?= number_format(abs($resultado), 2, ',', '.') ?>
        </h2>
        <p class="text-muted">
            Ingresos ($ <?= number_format($totalIngresos, 2, ',', '.') ?>) -
            Egresos ($ <?= number_format($totalEgresos, 2, ',', '.') ?>) =
            $ <?= number_format($resultado, 2, ',', '.') ?>
        </p>
    </div>
</div>
