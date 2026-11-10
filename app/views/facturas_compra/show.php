<?php
$factura = $factura ?? [];
$ordenesPago = $ordenesPago ?? [];

$estadoActual = $factura['estado'] ?? 'REGISTRADA';
$esAnulada = $estadoActual === 'ANULADA';
$esRegistrada = $estadoActual === 'REGISTRADA';
$saldo = (float)($factura['saldo'] ?? 0);
$tipoFiscal = ($factura['tipo_fiscal'] ?? '') !== '' ? $factura['tipo_fiscal'] . ' ' : '';
?>

<div class="mb-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
    <a href="<?= BASE_URL ?>/facturascompra" class="text-decoration-none">
        <i class="bi bi-arrow-left"></i> Volver a Facturas de Compra
    </a>
</div>

<div class="d-flex align-items-center gap-2 mb-3">
    <h3 class="mb-0"><i class="bi bi-receipt"></i> Factura <?= htmlspecialchars($tipoFiscal) ?>Nº <?= htmlspecialchars($factura['numero']) ?></h3>
    <span class="badge <?= $esAnulada ? 'bg-secondary' : 'bg-success' ?> fs-6 align-middle">
        <?= htmlspecialchars(FacturaCompra::ESTADOS[$estadoActual] ?? $estadoActual) ?>
    </span>
</div>

<?php if ($esAnulada): ?>
<div class="alert alert-secondary d-flex align-items-center gap-2">
    <i class="bi bi-info-circle-fill fs-5"></i>
    <div>Esta factura está anulada: su asiento de compra fue revertido y no genera deuda.</div>
</div>
<?php endif; ?>

<div class="row">
    <div class="col-md-8">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-file-earmark-text"></i> Datos de la factura</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <small class="text-muted d-block">Proveedor</small>
                        <strong><?= htmlspecialchars($factura['proveedor_nombre'] ?? 'N/D') ?></strong>
                        <?php if (!empty($factura['proveedor_cuit'])): ?>
                            <small class="text-muted d-block">CUIT: <?= htmlspecialchars($factura['proveedor_cuit']) ?></small>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-4 mb-3">
                        <small class="text-muted d-block">Orden(es) de Compra</small>
                        <?php $ocsFactura = $ocs ?? []; ?>
                        <?php if (!empty($ocsFactura)): ?>
                            <?php foreach ($ocsFactura as $ocF): ?>
                                <div>
                                    <a href="<?= BASE_URL ?>/ordenescompra/show/<?= (int)$ocF['oc_id'] ?>">OC #<?= (int)$ocF['oc_id'] ?></a>
                                    <small class="text-muted">(cubre $ <?= number_format((float)$ocF['monto_asignado'], 2, ',', '.') ?>)</small>
                                </div>
                            <?php endforeach; ?>
                        <?php elseif (!empty($factura['oc_id'])): ?>
                            <a href="<?= BASE_URL ?>/ordenescompra/show/<?= (int)$factura['oc_id'] ?>">OC #<?= (int)$factura['oc_id'] ?></a>
                        <?php else: ?>
                            <strong>Sin OC</strong>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-4 mb-3">
                        <small class="text-muted d-block">Fecha de factura</small>
                        <strong><?= date('d/m/Y', strtotime($factura['fecha_factura'])) ?></strong>
                    </div>

                    <div class="col-md-4 mb-3">
                        <small class="text-muted d-block">Vencimiento</small>
                        <strong><?= !empty($factura['fecha_vencimiento']) ? date('d/m/Y', strtotime($factura['fecha_vencimiento'])) : '-' ?></strong>
                    </div>
                    <div class="col-md-4 mb-3">
                        <small class="text-muted d-block">Moneda</small>
                        <strong><?= htmlspecialchars(($factura['moneda_simbolo'] ?? '') . ' ' . ($factura['moneda_nombre'] ?? '')) ?></strong>
                    </div>
                    <div class="col-md-4 mb-3">
                        <small class="text-muted d-block">Registrada por</small>
                        <strong><?= htmlspecialchars($factura['usuario_nombre'] ?? 'N/D') ?></strong>
                    </div>

                    <div class="col-md-4 mb-3">
                        <small class="text-muted d-block">Neto gravado</small>
                        <strong>$ <?= number_format((float)$factura['monto_base'], 2, ',', '.') ?></strong>
                    </div>
                    <div class="col-md-4 mb-3">
                        <small class="text-muted d-block">IVA</small>
                        <strong>$ <?= number_format((float)$factura['monto_iva'], 2, ',', '.') ?></strong>
                    </div>
                    <div class="col-md-4 mb-3">
                        <small class="text-muted d-block">Total</small>
                        <strong class="fs-5">$ <?= number_format((float)$factura['monto_total'], 2, ',', '.') ?></strong>
                    </div>

                    <div class="col-md-6 mb-3">
                        <small class="text-muted d-block">Pagado (comprometido en órdenes de pago)</small>
                        <strong>$ <?= number_format((float)$factura['pagado_comprometido'], 2, ',', '.') ?></strong>
                    </div>
                    <div class="col-md-6 mb-3">
                        <small class="text-muted d-block">Saldo pendiente de pago</small>
                        <strong class="fs-5 <?= $saldo > 0 && $esRegistrada ? 'text-danger' : 'text-success' ?>">
                            $ <?= number_format($saldo, 2, ',', '.') ?>
                        </strong>
                    </div>

                    <?php if (!empty($factura['observaciones'])): ?>
                    <div class="col-12 mb-0">
                        <small class="text-muted d-block">Observaciones</small>
                        <p class="mb-0"><?= nl2br(htmlspecialchars($factura['observaciones'])) ?></p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-cash-coin"></i> Órdenes de pago</h5>
                <?php if ($esRegistrada && $saldo > 0): ?>
                    <a href="<?= BASE_URL ?>/ordenespago/create?factura=<?= (int)$factura['id'] ?>" class="btn btn-sm btn-success">
                        <i class="bi bi-plus-lg"></i> Nueva orden de pago
                    </a>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if (empty($ordenesPago)): ?>
                    <p class="text-muted mb-0">Todavía no hay órdenes de pago para esta factura.</p>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm table-striped align-middle">
                        <thead class="table-dark">
                            <tr>
                                <th>Número</th>
                                <th>Fecha</th>
                                <th>Monto</th>
                                <th>Medio de pago</th>
                                <th>Estado</th>
                                <th class="text-center">Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($ordenesPago as $op): ?>
                            <?php
                                $badges = ['BORRADOR' => 'bg-secondary', 'APROBADA' => 'bg-warning text-dark', 'PAGADA' => 'bg-success', 'ANULADA' => 'bg-danger'];
                                $badgeOp = $badges[$op['estado']] ?? 'bg-secondary';
                            ?>
                            <tr>
                                <td>OP-<?= str_pad((string)$op['numero'], 6, '0', STR_PAD_LEFT) ?></td>
                                <td><?= date('d/m/Y', strtotime($op['fecha'])) ?></td>
                                <td>$ <?= number_format((float)$op['monto'], 2, ',', '.') ?></td>
                                <td><?= htmlspecialchars($op['medio_pago']) ?></td>
                                <td><span class="badge <?= $badgeOp ?>"><?= htmlspecialchars(OrdenPago::ESTADOS[$op['estado']] ?? $op['estado']) ?></span></td>
                                <td class="text-center">
                                    <a href="<?= BASE_URL ?>/ordenespago/show/<?= (int)$op['id'] ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card mb-3">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-info-circle"></i> Impacto financiero</h5>
            </div>
            <div class="card-body">
                <p class="mb-2">Al registrar la factura se generó el asiento de compra:</p>
                <ul class="mb-2 small">
                    <li><strong>Debe:</strong> Proveedores (gasto de compra)<?= (float)$factura['monto_iva'] > 0 ? ' + IVA' : '' ?></li>
                    <li><strong>Haber:</strong> 2100 Cuentas Corrientes Proveedores</li>
                </ul>
                <p class="mb-0 small text-muted">La caja no se movió: el dinero sale cuando la orden de pago se marca como pagada.</p>
            </div>
        </div>

        <?php if ($esRegistrada): ?>
        <div class="card mb-3">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-arrow-repeat"></i> Acciones</h5>
            </div>
            <div class="card-body d-grid gap-2">
                <?php if ($saldo > 0): ?>
                    <a href="<?= BASE_URL ?>/ordenespago/create?factura=<?= (int)$factura['id'] ?>" class="btn btn-success">
                        <i class="bi bi-cash-coin"></i> Generar orden de pago
                    </a>
                <?php endif; ?>
                <form method="POST" action="<?= BASE_URL ?>/facturascompra/anular/<?= (int)$factura['id'] ?>">
                    <input type="hidden" name="csrf_token" value="<?= Csrf::generate() ?>">
                    <button type="submit" class="btn btn-outline-danger w-100"
                            onclick="return confirm('¿Anular la factura? Se revertirá su asiento de compra. No debe tener órdenes de pago activas.')">
                        <i class="bi bi-x-circle"></i> Anular factura
                    </button>
                </form>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
