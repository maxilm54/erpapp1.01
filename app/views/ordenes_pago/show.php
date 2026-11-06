<?php
$orden = $orden ?? [];
$cajas = $cajas ?? [];

$estadoActual = $orden['estado'] ?? 'BORRADOR';
$esBorrador = $estadoActual === 'BORRADOR';
$esAprobada = $estadoActual === 'APROBADA';
$esPagada = $estadoActual === 'PAGADA';
$esAnulada = $estadoActual === 'ANULADA';

$badgeEstado = [
    'BORRADOR' => 'bg-secondary',
    'APROBADA' => 'bg-warning text-dark',
    'PAGADA'   => 'bg-success',
    'ANULADA'  => 'bg-danger',
][$estadoActual] ?? 'bg-secondary';
?>

<div class="mb-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
    <a href="<?= BASE_URL ?>/ordenespago" class="text-decoration-none">
        <i class="bi bi-arrow-left"></i> Volver a Órdenes de Pago
    </a>
</div>

<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
    <h3 class="mb-0"><i class="bi bi-cash-coin"></i> Orden de Pago OP-<?= str_pad((string)$orden['numero'], 6, '0', STR_PAD_LEFT) ?></h3>
    <span class="badge <?= $badgeEstado ?> fs-6 align-middle"><?= htmlspecialchars(OrdenPago::ESTADOS[$estadoActual] ?? $estadoActual) ?></span>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-file-earmark-text"></i> Datos de la orden</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <small class="text-muted d-block">Proveedor</small>
                        <strong><?= htmlspecialchars($orden['proveedor_nombre'] ?? 'N/D') ?></strong>
                        <?php if (!empty($orden['proveedor_cuit'])): ?>
                            <small class="text-muted d-block">CUIT: <?= htmlspecialchars($orden['proveedor_cuit']) ?></small>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-4 mb-3">
                        <small class="text-muted d-block">Factura de compra</small>
                        <a href="<?= BASE_URL ?>/facturascompra/show/<?= (int)$orden['factura_compra_id'] ?>">
                            <?= htmlspecialchars(($orden['factura_tipo'] ?? '') !== '' ? $orden['factura_tipo'] . ' ' : '') ?><?= htmlspecialchars($orden['factura_numero'] ?? '') ?>
                        </a>
                        <small class="text-muted d-block">Fecha factura: <?= date('d/m/Y', strtotime($orden['fecha_factura'])) ?></small>
                    </div>
                    <?php if (!empty($orden['orden_compra_id'])): ?>
                    <div class="col-md-4 mb-3">
                        <small class="text-muted d-block">Orden de Compra</small>
                        <a href="<?= BASE_URL ?>/ordenescompra/show/<?= (int)$orden['orden_compra_id'] ?>">OC #<?= (int)$orden['orden_compra_id'] ?></a>
                    </div>
                    <?php endif; ?>

                    <div class="col-md-4 mb-3">
                        <small class="text-muted d-block">Monto</small>
                        <strong class="fs-5">$ <?= number_format((float)$orden['monto'], 2, ',', '.') ?></strong>
                    </div>
                    <div class="col-md-4 mb-3">
                        <small class="text-muted d-block">Medio de pago</small>
                        <strong><?= htmlspecialchars($orden['medio_pago']) ?></strong>
                    </div>
                    <div class="col-md-4 mb-3">
                        <small class="text-muted d-block">Fecha de la orden</small>
                        <strong><?= date('d/m/Y', strtotime($orden['fecha'])) ?></strong>
                    </div>

                    <?php if ($esPagada): ?>
                        <div class="col-md-4 mb-3">
                            <small class="text-muted d-block">Pagada el</small>
                            <strong><?= date('d/m/Y', strtotime($orden['fecha_pago'])) ?></strong>
                        </div>
                        <div class="col-md-4 mb-3">
                            <small class="text-muted d-block">Caja / Banco</small>
                            <strong><?= htmlspecialchars($orden['caja_nombre'] ?? '-') ?></strong>
                        </div>
                    <?php endif; ?>

                    <?php if ($esAprobada || $esPagada): ?>
                        <div class="col-md-4 mb-3">
                            <small class="text-muted d-block">Aprobada el</small>
                            <strong><?= !empty($orden['aprobada_at']) ? date('d/m/Y H:i', strtotime($orden['aprobada_at'])) : '-' ?></strong>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($orden['observaciones'])): ?>
                    <div class="col-12 mb-0">
                        <small class="text-muted d-block">Observaciones</small>
                        <p class="mb-0"><?= nl2br(htmlspecialchars($orden['observaciones'])) ?></p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card mb-3">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-info-circle"></i> Impacto financiero</h5>
            </div>
            <div class="card-body">
                <?php if ($esBorrador): ?>
                    <p class="mb-0 small">La orden todavía no afecta caja ni contabilidad. Aprobala y luego pagala desde la caja/banco que corresponda.</p>
                <?php elseif ($esAprobada): ?>
                    <p class="mb-0 small">La orden está aprobada y reserva el saldo de la factura, pero <strong>el dinero todavía no salió de caja</strong>. Se debita al marcarla como pagada.</p>
                <?php elseif ($esPagada): ?>
                    <ul class="mb-0 small">
                        <li><strong>Debe:</strong> 2100 Cuentas Corrientes Proveedores</li>
                        <li><strong>Haber:</strong> Caja/Banco elegida</li>
                        <li>Egreso en <?= htmlspecialchars($orden['caja_nombre'] ?? 'la caja') ?> por $ <?= number_format((float)$orden['monto'], 2, ',', '.') ?></li>
                    </ul>
                <?php elseif ($esAnulada): ?>
                    <p class="mb-0 small">Orden anulada. Si estaba pagada, el asiento y el movimiento de caja fueron revertidos.</p>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!$esAnulada): ?>
        <div class="card mb-3">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-arrow-repeat"></i> Acciones</h5>
            </div>
            <div class="card-body d-grid gap-3">
                <?php if ($esBorrador): ?>
                    <form method="POST" action="<?= BASE_URL ?>/ordenespago/aprobar/<?= (int)$orden['id'] ?>">
                        <input type="hidden" name="csrf_token" value="<?= Csrf::generate() ?>">
                        <button type="submit" class="btn btn-outline-warning w-100"
                                onclick="return confirm('¿Aprobar la orden de pago? Reserva el saldo de la factura.')">
                            <i class="bi bi-check2-circle"></i> Aprobar
                        </button>
                    </form>
                <?php endif; ?>

                <?php if ($esAprobada): ?>
                    <form method="POST" action="<?= BASE_URL ?>/ordenespago/pagar/<?= (int)$orden['id'] ?>" class="border rounded p-2">
                        <input type="hidden" name="csrf_token" value="<?= Csrf::generate() ?>">
                        <label class="form-label small fw-bold">Caja / Banco (origen) *</label>
                        <select name="caja_banco_id" class="form-select form-select-sm mb-2" required>
                            <option value="">Seleccione...</option>
                            <?php foreach ($cajas as $cb): ?>
                                <option value="<?= (int)$cb['id'] ?>">
                                    <?= htmlspecialchars($cb['nombre']) ?>
                                    (Saldo: $ <?= number_format((float)$cb['saldo_actual'], 2, ',', '.') ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <label class="form-label small fw-bold">Fecha de pago</label>
                        <input type="date" name="fecha_pago" class="form-control form-control-sm mb-2" value="<?= date('Y-m-d') ?>">
                        <button type="submit" class="btn btn-success w-100"
                                onclick="return confirm('¿Registrar el pago? Se generará el egreso en caja y el asiento contable.')">
                            <i class="bi bi-cash-stack"></i> Marcar como pagada
                        </button>
                    </form>
                <?php endif; ?>

                <?php if ($esBorrador || $esAprobada || $esPagada): ?>
                    <form method="POST" action="<?= BASE_URL ?>/ordenespago/anular/<?= (int)$orden['id'] ?>">
                        <input type="hidden" name="csrf_token" value="<?= Csrf::generate() ?>">
                        <button type="submit" class="btn btn-outline-danger w-100"
                                onclick="return confirm('¿Anular la orden de pago?<?= $esPagada ? ' Se revertirán el asiento y el movimiento de caja.' : '' ?>')">
                            <i class="bi bi-x-circle"></i> Anular
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
