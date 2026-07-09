<?php
$meses = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
          'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
$nombreMes = $meses[$mes] ?? '';

// Colores para categorías
$colores = [
    'PROVEEDORES' => '#0d6efd',
    'SUELDOS'     => '#198754',
    'SERVICIOS'   => '#ffc107',
    'ALQUILER'    => '#dc3545',
    'IMPUESTOS'   => '#6f42c1',
    'OTROS'       => '#6c757d',
];
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h3><i class="bi bi-graph-up"></i> <?= htmlspecialchars($title) ?></h3>
    <a href="<?= BASE_URL ?>/gastos/create" class="btn btn-primary">
        <i class="bi bi-plus-lg"></i> Nuevo Gasto
    </a>
</div>

<!-- Selector de mes/año -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="<?= BASE_URL ?>/gastos/dashboard" class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label form-label-sm">Mes</label>
                <select name="mes" class="form-select form-select-sm">
                    <?php for ($i = 1; $i <= 12; $i++): ?>
                        <option value="<?= $i ?>" <?= $mes === $i ? 'selected' : '' ?>>
                            <?= $meses[$i] ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label form-label-sm">Año</label>
                <select name="año" class="form-select form-select-sm">
                    <?php for ($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
                        <option value="<?= $y ?>" <?= $año === $y ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-sm btn-primary">
                    <i class="bi bi-search"></i> Ver
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Resumen del mes -->
<div class="row mb-4">
    <div class="col-md-4">
        <div class="card bg-primary text-white">
            <div class="card-body text-center">
                <h6 class="card-title">Total <?= $nombreMes ?> <?= $año ?></h6>
                <h2 class="mb-0">$ <?= number_format($resumen['total_general'], 2, ',', '.') ?></h2>
            </div>
        </div>
    </div>
    <?php foreach ($resumen['por_categoria'] as $cat): ?>
    <div class="col-md-2">
        <div class="card">
            <div class="card-body text-center">
                <small class="text-muted"><?= $cat['categoria'] ?></small>
                <h5 class="mb-0 mt-1" style="color: <?= $colores[$cat['categoria']] ?? '#333' ?>">
                    $ <?= number_format($cat['total'], 0, ',', '.') ?>
                </h5>
                <small class="text-muted"><?= $cat['cantidad'] ?> gasto(s)</small>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="row">
    <!-- Gráfico de barras por categoría -->
    <div class="col-md-8">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Gastos por Categoría - <?= $nombreMes ?> <?= $año ?></h5>
            </div>
            <div class="card-body">
                <?php if (!empty($resumen['por_categoria'])): ?>
                <canvas id="chartCategorias" height="300"></canvas>
                <?php else: ?>
                <p class="text-muted text-center py-4">No hay gastos registrados en este período.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Panel lateral -->
    <div class="col-md-4">
        <!-- Estados -->
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0">Por Estado</h6>
            </div>
            <div class="card-body">
                <?php
                $badgeClasses = [
                    'BORRADOR' => 'bg-secondary',
                    'APROBADO' => 'bg-primary',
                    'PAGADO'   => 'bg-success',
                    'ANULADO'  => 'bg-danger',
                ];
                foreach ($resumen['por_estado'] as $est):
                ?>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="badge <?= $badgeClasses[$est['estado']] ?? 'bg-secondary' ?>">
                            <?= $est['estado'] ?>
                        </span>
                        <span><?= $est['cantidad'] ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Últimos gastos -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0">Últimos Gastos</h6>
                <a href="<?= BASE_URL ?>/gastos" class="btn btn-sm btn-outline-primary">Ver todos</a>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    <?php foreach ($recientes as $r): ?>
                    <a href="<?= BASE_URL ?>/gastos/show/<?= $r['id'] ?>"
                       class="list-group-item list-group-item-action">
                        <div class="d-flex justify-content-between">
                            <small class="text-muted"><?= date('d/m', strtotime($r['fecha'])) ?></small>
                            <strong>$ <?= number_format($r['monto_total'], 0, ',', '.') ?></strong>
                        </div>
                        <small><?= htmlspecialchars(mb_substr($r['descripcion'], 0, 40)) ?></small>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Proveedores (si hay) -->
<?php if (!empty($porProveedor)): ?>
<div class="card mt-4">
    <div class="card-header">
        <h5 class="mb-0">Gastos a Proveedores - <?= $nombreMes ?> <?= $año ?></h5>
    </div>
    <div class="card-body">
        <table class="table table-sm">
            <thead>
                <tr>
                    <th>Proveedor</th>
                    <th class="text-end">Monto</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($porProveedor as $pv): ?>
                <tr>
                    <td><?= htmlspecialchars($pv['proveedor'] ?? 'Sin proveedor') ?></td>
                    <td class="text-end">$ <?= number_format($pv['total'], 2, ',', '.') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($resumen['por_categoria'])): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('chartCategorias').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?= json_encode(array_column($resumen['por_categoria'], 'categoria')) ?>,
            datasets: [{
                label: 'Monto ($)',
                data: <?= json_encode(array_map('floatval', array_column($resumen['por_categoria'], 'total'))) ?>,
                backgroundColor: <?= json_encode(array_map(fn($c) => $colores[$c] ?? '#999', array_column($resumen['por_categoria'], 'categoria'))) ?>,
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function(ctx) {
                            return '$ ' + ctx.parsed.y.toLocaleString('es-AR', {minimumFractionDigits: 2});
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(val) { return '$ ' + val.toLocaleString('es-AR'); }
                    }
                }
            }
        }
    });
});
</script>
<?php endif; ?>
