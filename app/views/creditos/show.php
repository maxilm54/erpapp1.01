<div class="d-flex justify-content-between mb-3">
    <h3><i class="bi bi-bank"></i> Credito #<?= $credito['id'] ?> - <?= htmlspecialchars($credito['entidad']) ?></h3>
    <div>
        <?php if ($credito['estado'] === 'ACTIVO'): ?>
        <a href="<?= BASE_URL ?>/creditos/dashboard" class="btn btn-info">
            <i class="bi bi-speedometer2"></i> Dashboard
        </a>
        <?php endif; ?>
        <a href="<?= BASE_URL ?>/creditos" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Volver
        </a>
    </div>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Datos del Credito</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-borderless mb-0">
                            <tr><th style="width:160px">Entidad:</th><td><?= htmlspecialchars($credito['entidad']) ?></td></tr>
                            <tr><th>Monto Original:</th><td><strong>$ <?= number_format($credito['monto_original'], 2, ',', '.') ?></strong></td></tr>
                            <tr><th>Saldo Actual:</th><td>
                                <span class="<?= $credito['saldo_actual'] > 0 ? 'text-danger' : 'text-success' ?>">
                                    $ <?= number_format($credito['saldo_actual'], 2, ',', '.') ?>
                                </span>
                            </td></tr>
                            <tr><th>Cuota Mensual:</th><td>$ <?= number_format($credito['monto_cuota'], 2, ',', '.') ?></td></tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-borderless mb-0">
                            <tr><th style="width:160px">Tasa Interes:</th><td><?= $credito['tasa_interes'] ?>% anual</td></tr>
                            <tr><th>Cuotas:</th><td><?= $credito['cantidad_cuotas'] ?></td></tr>
                            <tr><th>Desembolso:</th><td><?= date('d/m/Y', strtotime($credito['fecha_desembolso'])) ?></td></tr>
                            <tr><th>Estado:</th><td>
                                <?php if ($credito['estado'] === 'ACTIVO'): ?>
                                    <span class="badge bg-success">Activo</span>
                                <?php elseif ($credito['estado'] === 'PAGADO'): ?>
                                    <span class="badge bg-primary">Pagado</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Cancelado</span>
                                <?php endif; ?>
                            </td></tr>
                        </table>
                    </div>
                </div>
                <?php if ($credito['observaciones']): ?>
                <hr>
                <p><strong>Observaciones:</strong> <?= nl2br(htmlspecialchars($credito['observaciones'])) ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card mb-4">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0">Resumen</h5>
            </div>
            <div class="card-body">
                <?php
                $pagadas = 0;
                $pendientes = 0;
                foreach ($cuotas as $cu) {
                    if ($cu['estado'] === 'PAGADO') $pagadas++;
                    else $pendientes++;
                }
                ?>
                <p><strong>Pagadas:</strong> <span class="text-success"><?= $pagadas ?></span></p>
                <p><strong>Pendientes:</strong> <span class="text-warning"><?= $pendientes ?></span></p>
                <p><strong>Total Cuotas:</strong> <?= $credito['cantidad_cuotas'] ?></p>
                <div class="progress mb-3">
                    <div class="progress-bar bg-success" style="width: <?= $credito['cantidad_cuotas'] > 0 ? ($pagadas / $credito['cantidad_cuotas'] * 100) : 0 ?>%"></div>
                </div>
                <?php if ($credito['estado'] === 'ACTIVO' && $pendientes > 0): ?>
                <form method="POST" action="<?= BASE_URL ?>/creditos/cancelar/<?= $credito['id'] ?>" class="mt-2">
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                    <button class="btn btn-sm btn-outline-danger w-100"
                            onclick="return confirm('Cancelar este credito? Las cuotas pendientes quedaran como vencidas.')">
                        <i class="bi bi-x-circle"></i> Cancelar Credito
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Cuotas -->
<div class="card">
    <div class="card-header bg-dark text-white">
        <h5 class="mb-0"><i class="bi bi-calendar-check"></i> Cronograma de Cuotas</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
        <table class="table table-sm table-hover">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Capital</th>
                    <th>Interes</th>
                    <th>Cuota</th>
                    <th>Vencimiento</th>
                    <th>Estado</th>
                    <th>Pago</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($cuotas as $cu): ?>
                <tr class="<?= $cu['estado'] === 'PAGADO' ? 'table-light' : ($cu['estado'] === 'PENDIENTE' && strtotime($cu['fecha_vencimiento']) < time() ? 'table-danger' : '') ?>">
                    <td><?= $cu['numero_cuota'] ?></td>
                    <td>$ <?= number_format($cu['capital'], 2, ',', '.') ?></td>
                    <td>$ <?= number_format($cu['interes'], 2, ',', '.') ?></td>
                    <td><strong>$ <?= number_format($cu['monto'], 2, ',', '.') ?></strong></td>
                    <td><?= date('d/m/Y', strtotime($cu['fecha_vencimiento'])) ?></td>
                    <td>
                        <?php if ($cu['estado'] === 'PAGADO'): ?>
                            <span class="badge bg-success">Pagado</span>
                        <?php elseif ($cu['estado'] === 'VENCIDO'): ?>
                            <span class="badge bg-danger">Vencido</span>
                        <?php else: ?>
                            <span class="badge bg-warning text-dark">Pendiente</span>
                        <?php endif; ?>
                    </td>
                    <td><?= $cu['fecha_pago'] ? date('d/m/Y', strtotime($cu['fecha_pago'])) : '-' ?></td>
                    <td>
                        <?php if ($cu['estado'] === 'PENDIENTE' && $credito['estado'] === 'ACTIVO'): ?>
                        <button class="btn btn-sm btn-success" data-bs-toggle="modal"
                                data-bs-target="#pagarModal"
                                data-cuota-id="<?= $cu['id'] ?>"
                                data-cuota-num="<?= $cu['numero_cuota'] ?>"
                                data-cuota-monto="<?= number_format($cu['monto'], 2, ',', '.') ?>">
                            <i class="bi bi-cash"></i> Pagar
                        </button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>

<!-- Modal Pagar Cuota -->
<div class="modal fade" id="pagarModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="<?= BASE_URL ?>/creditos/pagar-cuota">
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                <input type="hidden" name="credito_id" value="<?= $credito['id'] ?>">
                <input type="hidden" name="cuota_id" id="modalCuotaId">

                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">Pagar Cuota</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Cuota <strong>#<span id="modalCuotaNum"></span></strong> - $ <strong><span id="modalCuotaMonto"></span></strong></p>

                    <div class="mb-3">
                        <label class="form-label">Pagar con cuenta: *</label>
                        <select class="form-select" name="caja_banco_id" required>
                            <option value="">Seleccionar...</option>
                            <?php foreach ($cajas as $caja): ?>
                            <option value="<?= $caja['id'] ?>">
                                <?= htmlspecialchars($caja['nombre']) ?>
                                ($ <?= number_format($caja['saldo_actual'], 2, ',', '.') ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-check-lg"></i> Confirmar Pago
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('pagarModal').addEventListener('show.bs.modal', function(event) {
    var button = event.relatedTarget;
    document.getElementById('modalCuotaId').value = button.getAttribute('data-cuota-id');
    document.getElementById('modalCuotaNum').textContent = button.getAttribute('data-cuota-num');
    document.getElementById('modalCuotaMonto').textContent = button.getAttribute('data-cuota-monto');
});
</script>
