<h4><i class="bi bi-speedometer2"></i> Dashboard - Comprobantes</h4>
<hr>

<?php
$t = $datos['totales'];
?>

<!-- KPIs -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card text-bg-primary">
            <div class="card-body text-center">
                <div class="fs-4 fw-bold">$ <?= number_format($t['total_ventas'] ?? 0, 0, ',', '.') ?></div>
                <div class="small">Total Salidas</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-bg-danger">
            <div class="card-body text-center">
                <div class="fs-4 fw-bold">$ <?= number_format($t['ventas_pendientes'] ?? 0, 0, ',', '.') ?></div>
                <div class="small">Pendientes de Cobro</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-bg-warning">
            <div class="card-body text-center">
                <div class="fs-4 fw-bold">$ <?= number_format($t['total_compras'] ?? 0, 0, ',', '.') ?></div>
                <div class="small">Total Entradas</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-bg-success">
            <div class="card-body text-center">
                <div class="fs-4 fw-bold">$ <?= number_format($t['compras_pendientes'] ?? 0, 0, ',', '.') ?></div>
                <div class="small">Pendientes de Pago</div>
            </div>
        </div>
    </div>
</div>

<!-- Segunda fila: totales cobrados/pagados -->
<div class="row mb-4">
    <div class="col-md-6">
        <div class="card text-bg-info">
            <div class="card-body text-center">
                <div class="fs-4 fw-bold">$ <?= number_format($t['ventas_cobradas'] ?? 0, 0, ',', '.') ?></div>
                <div class="small">Total Cobrado (Salidas)</div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card text-bg-success">
            <div class="card-body text-center">
                <div class="fs-4 fw-bold">$ <?= number_format($t['compras_cobradas'] ?? 0, 0, ',', '.') ?></div>
                <div class="small">Total Pagado (Entradas)</div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-person-exclamation"></i> <strong>Top Deudores (Cobros Pendientes)</strong></div>
            <div class="card-body p-0">
                <table class="table table-sm table-striped mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>Cliente</th>
                            <th>Movimientos</th>
                            <th class="text-end">Pendiente</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($datos['deudores'])): ?>
                        <tr><td colspan="3" class="text-center text-muted">No hay deudores pendientes</td></tr>
                        <?php else: ?>
                        <?php foreach ($datos['deudores'] as $d): ?>
                        <tr>
                            <td><?= htmlspecialchars($d['razon_social']) ?></td>
                            <td><?= $d['cantidad'] ?></td>
                            <td class="text-end text-danger fw-bold">$ <?= number_format($d['pendiente'], 2, ',', '.') ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-truck"></i> <strong>Proveedores con Pagos Pendientes</strong></div>
            <div class="card-body p-0">
                <table class="table table-sm table-striped mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>Proveedor</th>
                            <th>Movimientos</th>
                            <th class="text-end">Pendiente</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($datos['proveedores'])): ?>
                        <tr><td colspan="3" class="text-center text-muted">No hay pagos pendientes</td></tr>
                        <?php else: ?>
                        <?php foreach ($datos['proveedores'] as $p): ?>
                        <tr>
                            <td><?= htmlspecialchars($p['razon_social']) ?></td>
                            <td><?= $p['cantidad'] ?></td>
                            <td class="text-end text-warning fw-bold">$ <?= number_format($p['total'], 2, ',', '.') ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header"><strong>Resumen por Tipo y Estado</strong></div>
    <div class="card-body p-0">
        <table class="table table-sm table-striped mb-0">
            <thead class="table-dark">
                <tr>
                    <th>Tipo</th>
                    <th>Estado</th>
                    <th class="text-end">Cantidad</th>
                    <th class="text-end">Monto Total</th>
                    <th class="text-end">Saldo Pendiente</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($datos['resumen'])): ?>
                <tr><td colspan="5" class="text-center text-muted">No hay comprobantes</td></tr>
                <?php else: ?>
                <?php foreach ($datos['resumen'] as $r):
                    $esVenta = $r['tipo'] === 'VENTA';
                    $estadoLabel = $r['estado'];
                    if ($r['estado'] === 'COBRADO') $estadoLabel = $esVenta ? 'COBRADO' : 'PAGADO';
                ?>
                <tr>
                    <td><span class="badge <?= $esVenta ? 'bg-info' : 'bg-warning text-dark' ?>"><?= $esVenta ? 'Salida' : 'Entrada' ?></span></td>
                    <td><?= $estadoLabel ?></td>
                    <td class="text-end"><?= $r['cantidad'] ?></td>
                    <td class="text-end">$ <?= number_format($r['total_monto'], 2, ',', '.') ?></td>
                    <td class="text-end">$ <?= number_format($r['total_pendiente'], 2, ',', '.') ?></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="text-end">
    <a href="<?= BASE_URL ?>/sdcomp" class="btn btn-primary btn-sm">
        <i class="bi bi-list-ul"></i> Ver Todos los Comprobantes
    </a>
</div>
