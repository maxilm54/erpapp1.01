<h1 class="mb-4">Dashboard General</h1>

<!-- STOCKS -->
<div class="row mb-4">
    <!-- Productos -->
    <div class="col-md-6">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-primary text-white">
                <h6 class="mb-0">📦 Stock Productos Terminados</h6>
            </div>
            <div class="card-body p-0" style="max-height: 300px; overflow-y: auto;">
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-light sticky-top">
                        <tr>
                            <th>Producto</th>
                            <th class="text-end">Stock</th>
                            <th class="text-end">Mín</th>
                            <th class="text-end">Crítico</th>
                            <th class="text-end">Máx</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($productosStock)): ?>
                        <tr><td colspan="6" class="text-center text-muted">Sin productos</td></tr>
                    <?php else: ?>
                        <?php foreach ($productosStock as $p): ?>
                        <tr>
                            <td><?= htmlspecialchars($p['nombre']) ?></td>
                            <td class="text-end"><?= number_format($p['stock'], 0) ?></td>
                            <td class="text-end text-muted"><?= $p['stock_minimo'] > 0 ? number_format($p['stock_minimo'], 0) : '-' ?></td>
                            <td class="text-end text-muted"><?= $p['stock_critico'] > 0 ? number_format($p['stock_critico'], 0) : '-' ?></td>
                            <td class="text-end text-muted"><?= $p['stock_maximo'] > 0 ? number_format($p['stock_maximo'], 0) : '-' ?></td>
                            <td>
                                <?php 
                                $colors = ['critico' => 'danger', 'bajo' => 'warning', 'normal' => 'success', 'alto' => 'info'];
                                $labels = ['critico' => 'Crítico', 'bajo' => 'Bajo', 'normal' => 'Normal', 'alto' => 'Alto'];
                                ?>
                                <span class="badge bg-<?= $colors[$p['estado']] ?? 'secondary' ?>">
                                    <?= $labels[$p['estado']] ?? $p['estado'] ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Materias Primas -->
    <div class="col-md-6">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-warning text-dark">
                <h6 class="mb-0">🧱 Stock Materias Primas</h6>
            </div>
            <div class="card-body p-0" style="max-height: 300px; overflow-y: auto;">
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-light sticky-top">
                        <tr>
                            <th>Materia Prima</th>
                            <th class="text-end">Stock</th>
                            <th class="text-end">Mín</th>
                            <th class="text-end">Crítico</th>
                            <th class="text-end">Máx</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($materiasPrimasStock)): ?>
                        <tr><td colspan="6" class="text-center text-muted">Sin materias primas</td></tr>
                    <?php else: ?>
                        <?php foreach ($materiasPrimasStock as $mp): ?>
                        <tr>
                            <td><?= htmlspecialchars($mp['nombre']) ?></td>
                            <td class="text-end"><?= number_format($mp['stock_actual'], 0) ?></td>
                            <td class="text-end text-muted"><?= $mp['stock_minimo'] > 0 ? number_format($mp['stock_minimo'], 0) : '-' ?></td>
                            <td class="text-end text-muted"><?= $mp['stock_critico'] > 0 ? number_format($mp['stock_critico'], 0) : '-' ?></td>
                            <td class="text-end text-muted"><?= $mp['stock_maximo'] > 0 ? number_format($mp['stock_maximo'], 0) : '-' ?></td>
                            <td>
                                <?php 
                                $colors = ['critico' => 'danger', 'bajo' => 'warning', 'normal' => 'success', 'alto' => 'info'];
                                $labels = ['critico' => 'Crítico', 'bajo' => 'Bajo', 'normal' => 'Normal', 'alto' => 'Alto'];
                                ?>
                                <span class="badge bg-<?= $colors[$mp['estado']] ?? 'secondary' ?>">
                                    <?= $labels[$mp['estado']] ?? $mp['estado'] ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($alertasStock)): ?>
<div class="card mt-4 border-danger mb-2">
    <div class="card-body">
        <h6 class="text-danger">⚠ Materias Primas en Riesgo</h6>

        <?php foreach ($alertasStock as $mp): ?>
            <?php
            $estado = StockHelper::estado(
                $mp['stock_actual'],
                $mp['stock_minimo'],
                $mp['stock_critico'],
                0
            );
            ?>

            <div class="d-flex justify-content-between mb-1">
                <span><?= htmlspecialchars($mp['nombre']) ?></span>
                <span class="badge bg-<?= $estado['color'] ?>">
                    <?= $mp['stock_actual'] ?>
                </span>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>
<div class="row">

    <!-- Ingresos -->
    <div class="col-md-4">
        <div class="card shadow-sm border-success">
            <div class="card-body">
                <h6 class="text-muted">Ingresos Último Mes</h6>
                <h3 class="text-success">
                    $<?= number_format($ingresos,2) ?>
                </h3>
            </div>
        </div>
    </div>

    <!-- Egresos -->
    <div class="col-md-4">
        <div class="card shadow-sm border-danger">
            <div class="card-body">
                <h6 class="text-muted">Egresos Último Mes</h6>
                <h3 class="text-danger">
                    $<?= number_format($egresos,2) ?>
                </h3>
            </div>
        </div>
    </div>

    <!-- Ganancia -->
    <div class="col-md-4">
        <div class="card shadow-sm border-primary">
            <div class="card-body">
                <h6 class="text-muted">Resultado</h6>
                <h3 class="<?= $ganancia >= 0 ? 'text-success' : 'text-danger' ?>">
                    $<?= number_format($ganancia,2) ?>
                </h3>
            </div>
        </div>
    </div>

</div>
<div class="row">
    <div class="col-lg-6">
    <canvas id="ventasChart"></canvas>

    <script>
        const ctx = document.getElementById('ventasChart');

        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['Ago','Sep','Oct','Nov','Dic','Ene'],
                datasets: [{
                    label: 'Ventas',
                    data: [12000, 15000, 9000, 18000, 22000, 17000]
                }]
            }
        });
    </script>
    </div>
    <div class="col-lg-6">
        <canvas id="ventasChart2"></canvas>

        <script>
        const ctx2 = document.getElementById('ventasChart2');

        new Chart(ctx2, {
            type: 'pie',
            data: {
                labels: ['Ago','Sep','Oct','Nov','Dic','Ene'],
                datasets: [{
                    label: 'Ventas',
                    data: [800, 900, 900, 1200, 1800, 2200, 1700]
                }]
            },
            options: {
                responsive: true,
                plugins: {
                legend: {
                    position: 'top',
                },
                title: {
                    display: true,
                    text: 'Ventas Mensuales'
                }
                }
            }
        });
        </script>
    </div>
</div>
