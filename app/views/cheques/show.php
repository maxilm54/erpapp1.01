<?php
$cheque = $cheque ?? [];
$estados = $estados ?? [];
$tipos = $tipos ?? [];
$cajas = $cajas ?? [];
$proveedores = $proveedores ?? [];

$estadoActual = $cheque['estado'] ?? 'EN_CARTERA';
$dias = (int)($cheque['dias_restantes'] ?? 0);
$pendienteAcreditar = in_array($estadoActual, ['EN_CARTERA', 'DEPOSITADO'], true);
$pagoAnulado = !empty($cheque['pago_anulado']);

$badgeEstado = [
    'EN_CARTERA' => 'bg-primary',
    'DEPOSITADO' => 'bg-info text-dark',
    'COBRADO'    => 'bg-success',
    'ENDOSADO'   => 'bg-dark',
    'RECHAZADO'  => 'bg-danger',
    'ANULADO'    => 'bg-secondary',
][$estadoActual] ?? 'bg-secondary';
?>

<div class="mb-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
    <a href="<?= BASE_URL ?>/cheques" class="text-decoration-none">
        <i class="bi bi-arrow-left"></i> Volver a Cartera de Cheques
    </a>
    <div class="d-flex gap-2">
        <?php if (!$pagoAnulado && $estadoActual !== 'ANULADO'): ?>
        <a href="<?= BASE_URL ?>/cheques/edit/<?= (int)$cheque['id'] ?>" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-pencil"></i> Editar datos
        </a>
        <?php endif; ?>
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

<?php if ($pendienteAcreditar && $dias < 0): ?>
<div class="alert alert-danger d-flex align-items-center gap-2">
    <i class="bi bi-exclamation-triangle-fill fs-4"></i>
    <div>
        <strong>Cheque vencido hace <?= abs($dias) ?> día(s).</strong>
        Verificá si fue depositado o si hay que rechazarlo.
    </div>
</div>
<?php elseif ($pendienteAcreditar && $dias === 0): ?>
<div class="alert alert-warning d-flex align-items-center gap-2">
    <i class="bi bi-exclamation-triangle-fill fs-4"></i>
    <div><strong>El cheque vence hoy.</strong> Recordá depositarlo o actualizar su estado.</div>
</div>
<?php endif; ?>

<div class="alert alert-info d-flex align-items-start gap-2">
    <i class="bi bi-info-circle-fill fs-5 mt-1"></i>
    <div>
        <strong>Impacto financiero:</strong>
        <?php if ($pendienteAcreditar): ?>
            el cheque está pendiente de acreditación (en cartera o depositado), así que
            <strong>no suma a la disponibilidad de caja/banco</strong>.
            Se acredita recién cuando lo marcás como <em>Cobrado / Acreditado</em>.
        <?php elseif ($estadoActual === 'COBRADO'): ?>
            acreditado en <strong><?= htmlspecialchars($cheque['caja_nombre'] ?? 'Caja/Banco') ?></strong>
            <?= !empty($cheque['fecha_acreditacion']) ? 'el ' . date('d/m/Y', strtotime($cheque['fecha_acreditacion'])) : '' ?>.
            Este importe <strong>sí suma a la disponibilidad real</strong>.
        <?php elseif ($estadoActual === 'ENDOSADO'): ?>
            endosado a <strong><?= htmlspecialchars($cheque['proveedor_nombre'] ?? 'un proveedor') ?></strong>
            <?= !empty($cheque['endosado_at']) ? 'el ' . date('d/m/Y', strtotime($cheque['endosado_at'])) : '' ?>.
            Salió de la cartera y <strong>canceló deuda con el proveedor</strong> sin pasar por banco.
        <?php elseif ($estadoActual === 'RECHAZADO'): ?>
            rechazado. Se revirtió la acreditación y el importe <strong>volvió a la cuenta corriente del cliente</strong>.
        <?php else: ?>
            el cheque está anulado y sus efectos financieros fueron revertidos.
        <?php endif; ?>
    </div>
</div>

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
                    <?php if (!empty($cheque['fecha_acreditacion'])): ?>
                    <div class="col-md-4 mb-3">
                        <small class="text-muted d-block">Fecha de acreditación</small>
                        <strong><?= date('d/m/Y', strtotime($cheque['fecha_acreditacion'])) ?></strong>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($cheque['proveedor_nombre'])): ?>
                    <div class="col-md-4 mb-3">
                        <small class="text-muted d-block">Endosado a</small>
                        <strong><?= htmlspecialchars($cheque['proveedor_nombre']) ?></strong>
                        <?php if (!empty($cheque['endosado_at'])): ?>
                            <small class="text-muted d-block"><?= date('d/m/Y', strtotime($cheque['endosado_at'])) ?></small>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <div class="col-md-4 mb-3">
                        <small class="text-muted d-block">Fecha de emisión</small>
                        <strong><?= !empty($cheque['fecha_emision']) ? date('d/m/Y', strtotime($cheque['fecha_emision'])) : '-' ?></strong>
                    </div>
                    <div class="col-md-4 mb-3">
                        <small class="text-muted d-block">Fecha de vencimiento</small>
                        <strong><?= date('d/m/Y', strtotime($cheque['fecha_vencimiento'])) ?></strong>
                        <?php if ($pendienteAcreditar): ?>
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
            <div class="card-body d-grid gap-3">
                <?php if ($pendienteAcreditar): ?>
                    <?php if ($estadoActual === 'EN_CARTERA'): ?>
                    <form method="POST" action="<?= BASE_URL ?>/cheques/cambiar-estado/<?= (int)$cheque['id'] ?>">
                        <input type="hidden" name="csrf_token" value="<?= Csrf::generate() ?>">
                        <input type="hidden" name="estado" value="DEPOSITADO">
                        <button type="submit" class="btn btn-outline-info w-100"
                                onclick="return confirm('¿Marcar el cheque como depositado en tránsito?')">
                            <i class="bi bi-bank"></i> Depositar (en tránsito)
                        </button>
                        <div class="form-text">Depositado pero todavía no acreditado: sigue sin sumar a la disponibilidad.</div>
                    </form>
                    <?php else: ?>
                    <form method="POST" action="<?= BASE_URL ?>/cheques/cambiar-estado/<?= (int)$cheque['id'] ?>">
                        <input type="hidden" name="csrf_token" value="<?= Csrf::generate() ?>">
                        <input type="hidden" name="estado" value="EN_CARTERA">
                        <button type="submit" class="btn btn-outline-primary w-100"
                                onclick="return confirm('¿Volver a poner el cheque en cartera?')">
                            <i class="bi bi-arrow-counterclockwise"></i> Volver a cartera
                        </button>
                    </form>
                    <?php endif; ?>

                    <form method="POST" action="<?= BASE_URL ?>/cheques/cambiar-estado/<?= (int)$cheque['id'] ?>" class="border rounded p-2">
                        <input type="hidden" name="csrf_token" value="<?= Csrf::generate() ?>">
                        <input type="hidden" name="estado" value="COBRADO">
                        <label class="form-label small fw-bold mb-1">Cobrado / Acreditado</label>
                        <?php if (empty($cajas)): ?>
                            <div class="alert alert-warning py-1 small mb-2">
                                No hay cajas/bancos activos.
                                <a href="<?= BASE_URL ?>/contabilidad/cajas">Crear uno</a>.
                            </div>
                        <?php else: ?>
                            <select name="caja_banco_id" class="form-select form-select-sm mb-2" required>
                                <option value="">Caja / Banco...</option>
                                <?php foreach ($cajas as $cb): ?>
                                    <option value="<?= (int)$cb['id'] ?>" <?= (int)($cheque['caja_banco_id'] ?? 0) === (int)$cb['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($cb['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>
                        <input type="date" name="fecha_acreditacion" class="form-control form-control-sm mb-2"
                               value="<?= date('Y-m-d') ?>" required>
                        <button type="submit" class="btn btn-success w-100" <?= empty($cajas) ? 'disabled' : '' ?>
                                onclick="return confirm('¿Acreditar el cheque en la caja/banco seleccionada? Recién ahora suma a la disponibilidad.')">
                            <i class="bi bi-check-circle"></i> Acreditar en banco/caja
                        </button>
                        <div class="form-text">Recién acá el cheque sale de cartera y suma a la disponibilidad real.</div>
                    </form>

                    <form method="POST" action="<?= BASE_URL ?>/cheques/cambiar-estado/<?= (int)$cheque['id'] ?>" class="border rounded p-2">
                        <input type="hidden" name="csrf_token" value="<?= Csrf::generate() ?>">
                        <input type="hidden" name="estado" value="ENDOSADO">
                        <label class="form-label small fw-bold mb-1">Endosar a proveedor</label>
                        <?php if (empty($proveedores)): ?>
                            <div class="alert alert-warning py-1 small mb-2">
                                No hay proveedores activos.
                                <a href="<?= BASE_URL ?>/proveedores">Crear uno</a>.
                            </div>
                        <?php else: ?>
                            <select name="proveedor_id" class="form-select form-select-sm mb-2" required>
                                <option value="">Proveedor...</option>
                                <?php foreach ($proveedores as $pv): ?>
                                    <option value="<?= (int)$pv['id'] ?>"><?= htmlspecialchars($pv['razon_social']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>
                        <input type="date" name="fecha_endoso" class="form-control form-control-sm mb-2"
                               value="<?= date('Y-m-d') ?>" required>
                        <button type="submit" class="btn btn-dark w-100" <?= empty($proveedores) ? 'disabled' : '' ?>
                                onclick="return confirm('¿Endosar el cheque al proveedor seleccionado y cancelar deuda?')">
                            <i class="bi bi-arrow-left-right"></i> Endosar y cancelar deuda
                        </button>
                        <div class="form-text">No pasa por banco: descuenta la deuda del proveedor en Cta. Cte. Empresa.</div>
                    </form>

                    <form method="POST" action="<?= BASE_URL ?>/cheques/cambiar-estado/<?= (int)$cheque['id'] ?>">
                        <input type="hidden" name="csrf_token" value="<?= Csrf::generate() ?>">
                        <input type="hidden" name="estado" value="RECHAZADO">
                        <button type="submit" class="btn btn-outline-danger w-100"
                                onclick="return confirm('¿Marcar el cheque como rechazado? El importe vuelve a la cuenta corriente del cliente.')">
                            <i class="bi bi-x-octagon"></i> Rechazar
                        </button>
                    </form>
                <?php elseif ($estadoActual === 'COBRADO'): ?>
                    <div class="alert alert-success py-2 small mb-0">
                        Acreditado en <strong><?= htmlspecialchars($cheque['caja_nombre'] ?? 'Caja/Banco') ?></strong>.
                    </div>
                    <form method="POST" action="<?= BASE_URL ?>/cheques/cambiar-estado/<?= (int)$cheque['id'] ?>">
                        <input type="hidden" name="csrf_token" value="<?= Csrf::generate() ?>">
                        <input type="hidden" name="estado" value="EN_CARTERA">
                        <button type="submit" class="btn btn-outline-primary w-100"
                                onclick="return confirm('¿Volver a cartera? Se revierte el ingreso en caja/banco.')">
                            <i class="bi bi-arrow-counterclockwise"></i> Volver a cartera
                        </button>
                    </form>
                    <form method="POST" action="<?= BASE_URL ?>/cheques/cambiar-estado/<?= (int)$cheque['id'] ?>">
                        <input type="hidden" name="csrf_token" value="<?= Csrf::generate() ?>">
                        <input type="hidden" name="estado" value="RECHAZADO">
                        <button type="submit" class="btn btn-outline-danger w-100"
                                onclick="return confirm('¿Marcar el cheque como rechazado? Se revierte la acreditación y el cliente vuelve a deber.')">
                            <i class="bi bi-x-octagon"></i> Rechazar
                        </button>
                    </form>
                <?php elseif ($estadoActual === 'ENDOSADO'): ?>
                    <div class="alert alert-dark py-2 small mb-0">
                        Endosado a <strong><?= htmlspecialchars($cheque['proveedor_nombre'] ?? 'proveedor') ?></strong>.
                    </div>
                    <form method="POST" action="<?= BASE_URL ?>/cheques/cambiar-estado/<?= (int)$cheque['id'] ?>">
                        <input type="hidden" name="csrf_token" value="<?= Csrf::generate() ?>">
                        <input type="hidden" name="estado" value="EN_CARTERA">
                        <button type="submit" class="btn btn-outline-primary w-100"
                                onclick="return confirm('¿Volver a cartera? Se revierte el endoso y vuelve la deuda con el proveedor.')">
                            <i class="bi bi-arrow-counterclockwise"></i> Volver a cartera
                        </button>
                    </form>
                    <form method="POST" action="<?= BASE_URL ?>/cheques/cambiar-estado/<?= (int)$cheque['id'] ?>">
                        <input type="hidden" name="csrf_token" value="<?= Csrf::generate() ?>">
                        <input type="hidden" name="estado" value="RECHAZADO">
                        <button type="submit" class="btn btn-outline-danger w-100"
                                onclick="return confirm('¿Marcar el cheque como rechazado? Se revierte el endoso y el cliente vuelve a deber.')">
                            <i class="bi bi-x-octagon"></i> Rechazar
                        </button>
                    </form>
                <?php elseif ($estadoActual === 'RECHAZADO'): ?>
                    <div class="alert alert-danger py-2 small mb-0">
                        Rechazado. El importe volvió a la cuenta corriente del cliente.
                    </div>
                    <form method="POST" action="<?= BASE_URL ?>/cheques/cambiar-estado/<?= (int)$cheque['id'] ?>">
                        <input type="hidden" name="csrf_token" value="<?= Csrf::generate() ?>">
                        <input type="hidden" name="estado" value="EN_CARTERA">
                        <button type="submit" class="btn btn-outline-primary w-100"
                                onclick="return confirm('¿Volver a cartera? Se rehace el crédito en la cuenta corriente y el asiento de cartera.')">
                            <i class="bi bi-arrow-counterclockwise"></i> Volver a cartera
                        </button>
                    </form>
                <?php endif; ?>

                <div class="form-text mt-1">
                    Para anular el cheque hay que anular el cobro asociado.
                    La cartera es un activo: no es caja ni banco hasta que se acredita.
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
