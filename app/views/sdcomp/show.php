<?php
$esVenta = $mov['tipo'] === 'VENTA';

$estadosBadge = [
    'PENDIENTE' => 'bg-danger',
    'PARCIAL'   => 'bg-warning text-dark',
    'COBRADO'   => 'bg-success',
    'ANULADO'   => 'bg-secondary'
];
$badge = $estadosBadge[$mov['estado']] ?? 'bg-secondary';

$estadoLabel = $mov['estado'];
if ($mov['estado'] === 'COBRADO') $estadoLabel = $esVenta ? 'COBRADO' : 'PAGADO';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4>
        <i class="bi bi-file-earmark-text"></i>
        Comprobante #<?= $mov['id'] ?>
        <span class="badge <?= $badge ?> fs-6"><?= $estadoLabel ?></span>
    </h4>
    <div>
        <?php if ($mov['estado'] !== 'ANULADO' && $mov['estado'] !== 'COBRADO'): ?>
        <a href="<?= BASE_URL ?>/sdcomp/pago/<?= $mov['id'] ?>" class="btn btn-success btn-sm">
            <i class="bi bi-cash"></i> <?= $esVenta ? 'Registrar Cobro' : 'Registrar Pago' ?>
        </a>
        <a href="<?= BASE_URL ?>/sdcomp/anular/<?= $mov['id'] ?>" class="btn btn-outline-danger btn-sm"
           onclick="return confirm('Anular este comprobante? Se ajustara el stock.')">
            <i class="bi bi-x-circle"></i> Anular
        </a>
        <?php endif; ?>
        <a href="<?= BASE_URL ?>/sdcomp" class="btn btn-secondary btn-sm">Volver</a>
        <a href="<?= BASE_URL ?>/sdcomp/generar-pdf/<?= $mov['id'] ?>" class="btn btn-info btn-sm" target="_blank">
            <i class="bi bi-file-earmark-pdf"></i> Generar PDF
        </a>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card mb-3">
            <div class="card-header"><strong>Datos del Comprobante</strong></div>
            <div class="card-body">
                <table class="table table-sm table-borderless mb-0">
                    <tr><td class="text-muted" style="width:140px">Tipo</td><td><span class="badge <?= $esVenta ? 'bg-info' : 'bg-warning text-dark' ?>"><?= $esVenta ? 'Salida' : 'Entrada' ?></span></td></tr>
                    <tr><td class="text-muted">Fecha</td><td><?= date('d/m/Y H:i', strtotime($mov['created_at'])) ?></td></tr>
                    <tr><td class="text-muted">Cliente/Prov.</td><td><?= htmlspecialchars($mov['razon_social'] ?? '-') ?></td></tr>
                    <tr><td class="text-muted">CUIT</td><td><?= htmlspecialchars($mov['cuit'] ?? '-') ?></td></tr>
                    <tr><td class="text-muted">Descripcion</td><td><?= htmlspecialchars($mov['descripcion'] ?? '-') ?></td></tr>
                    <tr><td class="text-muted">Observaciones</td><td><?= htmlspecialchars($mov['observaciones'] ?? '-') ?></td></tr>
                    <tr><td class="text-muted">Creado por</td><td><?= htmlspecialchars($mov['usuario_nombre']) ?></td></tr>
                </table>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card mb-3">
            <div class="card-header"><strong>Montos</strong></div>
            <div class="card-body">
                <table class="table table-sm table-borderless mb-0">
                    <tr><td class="text-muted" style="width:160px">Monto Total</td><td class="fs-5 fw-bold">$ <?= number_format($mov['monto_total'], 2, ',', '.') ?></td></tr>
                    <tr><td class="text-muted">Saldo Pendiente</td><td class="fs-5 <?= $mov['saldo_pendiente'] > 0 ? 'text-danger' : 'text-success' ?> fw-bold">$ <?= number_format($mov['saldo_pendiente'], 2, ',', '.') ?></td></tr>
                    <tr><td class="text-muted"><?= $esVenta ? 'Cobros registrados' : 'Pagos registrados' ?></td><td><?= count($mov['pagos']) ?></td></tr>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header"><strong>Detalle de Productos</strong></div>
    <div class="card-body p-0">
        <table class="table table-sm table-striped mb-0">
            <thead class="table-dark">
                <tr>
                    <th>#</th>
                    <th>Tipo</th>
                    <th>SKU</th>
                    <th>Producto / Materia Prima / Concepto</th>
                    <th class="text-end">Cantidad</th>
                    <th class="text-end">P. Unitario</th>
                    <th class="text-end">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <?php $i = 1; foreach ($mov['detalle'] as $d):
                    $sub = (float)$d['cantidad'] * (float)$d['precio_unitario'];
                    $tipoItem = $d['tipo_item'] ?? '';
                    if ($tipoItem === 'MATERIAPRIMA') {
                        $tipoLabel = 'MP';
                        $tipoClass = 'bg-warning text-dark';
                    } elseif ($tipoItem === 'MANUAL') {
                        $tipoLabel = 'MANUAL';
                        $tipoClass = 'bg-secondary';
                    } else {
                        $tipoLabel = 'PROD';
                        $tipoClass = 'bg-info';
                    }
                    $nombreItem = $d['item_nombre'] ?? $d['descripcion'] ?? 'Sin nombre';
                ?>
                <tr>
                    <td><?= $i++ ?></td>
                    <td><span class="badge <?= $tipoClass ?>"><?= $tipoLabel ?></span></td>
                    <td><?= htmlspecialchars($d['sku'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($nombreItem) ?></td>
                    <td class="text-end"><?= number_format((float)$d['cantidad'], 2, ',', '.') ?></td>
                    <td class="text-end">$ <?= number_format((float)$d['precio_unitario'], 2, ',', '.') ?></td>
                    <td class="text-end">$ <?= number_format($sub, 2, ',', '.') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if (!empty($mov['pagos'])): ?>
<div class="card mb-3">
    <div class="card-header"><strong><?= $esVenta ? 'Historial de Cobros' : 'Historial de Pagos' ?></strong></div>
    <div class="card-body p-0">
        <table class="table table-sm table-striped mb-0">
            <thead class="table-success">
                <tr>
                    <th>Fecha</th>
                    <th class="text-end">Monto</th>
                    <th>Descripcion</th>
                    <th>Usuario</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($mov['pagos'] as $pg): ?>
                <tr>
                    <td><?= date('d/m/Y H:i', strtotime($pg['fecha'])) ?></td>
                    <td class="text-end">$ <?= number_format($pg['monto'], 2, ',', '.') ?></td>
                    <td><?= htmlspecialchars($pg['descripcion'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($pg['usuario_nombre']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
