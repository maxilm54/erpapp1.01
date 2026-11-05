<?php
$cheque = $cheque ?? [];
$estados = $estados ?? [];
$tipos = $tipos ?? [];

$estadoActual = $cheque['estado'] ?? 'EN_CARTERA';
$dias = (int)($cheque['dias_restantes'] ?? 0);
$enCartera = $estadoActual === 'EN_CARTERA';
$pagoAnulado = !empty($cheque['pago_anulado']);

$badgeEstado = [
    'EN_CARTERA' => 'bg-primary',
    'DEPOSITADO' => 'bg-info text-dark',
    'COBRADO'    => 'bg-success',
    'RECHAZADO'  => 'bg-danger',
    'ANULADO'    => 'bg-secondary',
][$estadoActual] ?? 'bg-secondary';
?>

<div class="mb-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
    <a href="<?= BASE_URL ?>/cheques" class="text-decoration-none">
        <i class="bi bi-arrow-left"></i> Volver a Cartera de Cheques
    </a>
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>/cheques/edit/<?= (int)$cheque['id'] ?>" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-pencil"></i> Editar datos
        </a>
        <?php if (!empty($cheque['pago_id'])): ?>
            <a href="<?= BASE_URL ?>/cobros/show/<?= (int)$cheque['pago_id'] ?>" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-cash-coin"></i> Ver cobro #<?= (int)$cheque['pago_id'] ?>
            </a>
        <?php endif; ?>
    </div>
</div>

<h3>
    <i class="bi bi-wallet2"></i>
    Cheque <?= htmlspecialchars($cheque['banco'] ?? '') ?> Nº <?= htmlspecialchars($cheque['numero'] ?? '') ?>
    <span class="badge <?= $badgeEstado ?> fs-6 align-middle"><?= htmlspecialchars($estados[$estadoActual] ?? $estadoActual) ?></span>
</h3>

<?php if ($enCartera && $dias < 0): ?>
<div class="alert alert-danger d-flex align-items-center gap-2">
    <i class="bi bi-exclamation-triangle-fill fs-4"></i>
    <div>
        <strong>Cheque vencido hace <?= abs($dias) ?> día(s).</strong>
        Verificá si fue depositado o si hay que rechazarlo.
    </div>
</div>
<?php elseif ($enCartera && $dias === 0): ?>
<div class="alert alert-warning d-flex align-items-center gap-2">
    <i class="bi bi-exclamation-triangle-fill fs-4"></i>
    <div><strong>El cheque vence hoy.</strong> Recordá depositarlo o actualizar su estado.</div>
</div>
<?php endif; ?>

<?php if ($pagoAnulado): ?>
<div class="alert alert-secondary">
    <i class="bi bi-info-circle"></i>
    El cobro asociado a este cheque está <strong>anulado</strong>.
</div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-md-8">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-info-circle"></i> Datos del Cheque</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <small class="text-muted d-block">Cliente</small>
                        <strong><?= htmlspecialchars($cheque['nombre_cliente'] ?? 'N/D') ?></strong>
                    </div>
                    <div class="col-md-4 mb-3">
                        <small class="text-muted d-block">Monto</small>
                        <strong class="fs-5">$ <?= number_format((float)$cheque['monto'], 2, ',', '.') ?></strong>
                    </div>
                    <div class="col-md-4 mb-3">
                        <small class="text-muted d-block">Tipo</small>
                        <strong><?= htmlspecialchars($tipos[$cheque['tipo'] ?? 'FISICO'] ?? $cheque['tipo'] ?? '') ?></strong>
                    </div>

                    <div class="col-md-4 mb-3">
                        <small class="text-muted d-block">Banco</small>
                        <strong><?= htmlspecialchars($cheque['banco'] ?? '-') ?></strong>
                    </div>
                    <div class="col-md-4 mb-3">
                        <small class="text-muted d-block">Número</small>
                        <strong><?= htmlspecialchars($cheque['numero'] ?? '-') ?></strong>
                    </div>
                    <div class="col-md-4 mb-3">
                        <small class="text-muted d-block">Sucursal</small>
                        <strong><?= htmlspecialchars($cheque['sucursal'] ?? '-') ?></strong>
                    </div>

                    <div class="col-md-4 mb-3">
                        <small class="text-muted d-block">Titular</small>
                        <strong><?= htmlspecialchars($cheque['titular'] ?? '-') ?></strong>
                    </div>
                    <div class="col-md-4 mb-3">
                        <small class="text-muted d-block">CUIT</small>
                        <strong><?= htmlspecialchars($cheque['cuit'] ?? '-') ?></strong>
                    </div>
                    <div class="col-md-4 mb-3">
                        <small class="text-muted d-block">Caja / Banco destino</small>
                        <strong><?= htmlspecialchars($cheque['caja_nombre'] ?? 'Sin asignar') ?></strong>
                    </div>

                    <div class="col-md-4 mb-3">
                        <small class="text-muted d-block">Fecha de emisión</small>
                        <strong><?= !empty($cheque['fecha_emision']) ? date('d/m/Y', strtotime($cheque['fecha_emision'])) : '-' ?></strong>
                    </div>
                    <div class="col-md-4 mb-3">
                        <small class="text-muted d-block">Fecha de vencimiento</small>
                        <strong><?= date('d/m/Y', strtotime($cheque['fecha_vencimiento'])) ?></strong>
                        <?php if ($enCartera): ?>
                            <?php if ($dias < 0): ?>
                                <span class="badge bg-danger ms-1"><?= abs($dias) ?> días vencido</span>
                            <?php elseif ($dias === 0): ?>
                                <span class="badge bg-danger ms-1">Vence hoy</span>
                            <?php else: ?>
                                <span class="badge bg-light text-dark border ms-1">En <?= $dias ?> días</span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-4 mb-3">
                        <small class="text-muted d-block">Registrado</small>
                        <strong><?= !empty($cheque['created_at']) ? date('d/m/Y H:i', strtotime($cheque['created_at'])) : '-' ?></strong>
                    </div>

                    <div class="col-md-12">
                        <small class="text-muted d-block">Observaciones</small>
                        <div><?= nl2br(htmlspecialchars($cheque['observaciones'] ?? '-')) ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <?php if ($pagoAnulado): ?>
        <div class="card mb-3">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-slash-circle"></i> Cheque anulado</h5>
            </div>
            <div class="card-body text-muted">
                El cobro asociado está anulado, por lo que el cheque quedó anulado.
                Para modificarlo hay que registrar un cobro nuevo.
            </div>
        </div>
        <?php else: ?>
        <div class="card mb-3">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-arrow-repeat"></i> Cambiar estado</h5>
            </div>
            <div class="card-body d-grid gap-2">
                <?php if ($estadoActual !== 'DEPOSITADO'): ?>
                <form method="POST" action="<?= BASE_URL ?>/cheques/cambiar-estado/<?= (int)$cheque['id'] ?>">
                    <input type="hidden" name="csrf_token" value="<?= Csrf::generate() ?>">
                    <input type="hidden" name="estado" value="DEPOSITADO">
                    <button type="submit" class="btn btn-outline-info w-100"
                            onclick="return confirm('¿Marcar el cheque como depositado?')">
                        <i class="bi bi-bank"></i> Depositar
                    </button>
                </form>
                <?php endif; ?>

                <?php if ($estadoActual !== 'COBRADO'): ?>
                <form method="POST" action="<?= BASE_URL ?>/cheques/cambiar-estado/<?= (int)$cheque['id'] ?>">
                    <input type="hidden" name="csrf_token" value="<?= Csrf::generate() ?>">
                    <input type="hidden" name="estado" value="COBRADO">
                    <button type="submit" class="btn btn-outline-success w-100"
                            onclick="return confirm('¿Marcar el cheque como cobrado?')">
                        <i class="bi bi-check-circle"></i> Cobrado
                    </button>
                </form>
                <?php endif; ?>

                <?php if ($estadoActual !== 'RECHAZADO'): ?>
                <form method="POST" action="<?= BASE_URL ?>/cheques/cambiar-estado/<?= (int)$cheque['id'] ?>">
                    <input type="hidden" name="csrf_token" value="<?= Csrf::generate() ?>">
                    <input type="hidden" name="estado" value="RECHAZADO">
                    <button type="submit" class="btn btn-outline-danger w-100"
                            onclick="return confirm('¿Marcar el cheque como rechazado?')">
                        <i class="bi bi-x-octagon"></i> Rechazado
                    </button>
                </form>
                <?php endif; ?>

                <?php if ($estadoActual !== 'EN_CARTERA'): ?>
                <form method="POST" action="<?= BASE_URL ?>/cheques/cambiar-estado/<?= (int)$cheque['id'] ?>">
                    <input type="hidden" name="csrf_token" value="<?= Csrf::generate() ?>">
                    <input type="hidden" name="estado" value="EN_CARTERA">
                    <button type="submit" class="btn btn-outline-primary w-100"
                            onclick="return confirm('¿Volver a poner el cheque en cartera?')">
                        <i class="bi bi-arrow-counterclockwise"></i> Volver a cartera
                    </button>
                </form>
                <?php endif; ?>

                <?php if ($estadoActual !== 'ANULADO'): ?>
                <hr class="my-2">
                <form method="POST" action="<?= BASE_URL ?>/cheques/cambiar-estado/<?= (int)$cheque['id'] ?>">
                    <input type="hidden" name="csrf_token" value="<?= Csrf::generate() ?>">
                    <input type="hidden" name="estado" value="ANULADO">
                    <button type="submit" class="btn btn-outline-secondary w-100"
                            onclick="return confirm('¿Anular este cheque? No se borra, queda registrado como ANULADO.')">
                        <i class="bi bi-slash-circle"></i> Anular
                    </button>
                </form>
                <?php endif; ?>

                <div class="form-text mt-1">
                    El estado es de seguimiento del cheque: no genera movimientos de caja adicionales.
                    El cobro ya impactó en la cuenta corriente y, si se eligió caja/banco, en la caja.
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-link-45deg"></i> Cobro asociado</h6>
            </div>
            <div class="card-body">
                <?php if (!empty($cheque['pago_id'])): ?>
                    <div class="mb-1">
                        <a href="<?= BASE_URL ?>/cobros/show/<?= (int)$cheque['pago_id'] ?>" class="text-decoration-none fw-bold">
                            Cobro #<?= (int)$cheque['pago_id'] ?>
                        </a>
                    </div>
                    <small class="text-muted d-block">
                        Fecha: <?= !empty($cheque['pago_fecha']) ? date('d/m/Y H:i', strtotime($cheque['pago_fecha'])) : '-' ?>
                    </small>
                    <?php if (isset($cheque['pago_monto'])): ?>
                        <small class="text-muted d-block">
                            Monto del cobro: $ <?= number_format((float)$cheque['pago_monto'], 2, ',', '.') ?>
                        </small>
                    <?php endif; ?>
                    <?php if ($pagoAnulado): ?>
                        <span class="badge bg-danger mt-2">Cobro anulado</span>
                    <?php else: ?>
                        <span class="badge bg-success mt-2">Cobro activo</span>
                    <?php endif; ?>
                <?php else: ?>
                    <span class="text-muted">Sin cobro asociado.</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
