<?php $cajas = $cajas ?? []; ?>

<div class="mb-3">
    <a href="<?= BASE_URL ?>/cobros" class="text-decoration-none">
        <i class="bi bi-arrow-left"></i> Volver a Cobros
    </a>
</div>

<h3><i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($title) ?></h3>

<?php
$totalPendiente = array_sum(array_column($ventas, 'saldo_pendiente'));
?>

<!-- Resumen -->
<div class="card mb-4">
    <div class="card-body">
        <div class="row text-center">
            <div class="col-md-3">
                <small class="text-muted d-block">Ventas Pendientes</small>
                <h4 class="mb-0"><?= count($ventas) ?></h4>
            </div>
            <div class="col-md-3">
                <small class="text-muted d-block">Total Facturado</small>
                <h4 class="mb-0">$ <?= number_format(array_sum(array_column($ventas, 'monto_total')), 2, ',', '.') ?></h4>
            </div>
            <div class="col-md-3">
                <small class="text-muted d-block">Total Cobrado</small>
                <h4 class="mb-0 text-success">$ <?= number_format(array_sum(array_column($ventas, 'pagado')), 2, ',', '.') ?></h4>
            </div>
            <div class="col-md-3">
                <small class="text-muted d-block">Total Pendiente</small>
                <h4 class="mb-0 text-danger">$ <?= number_format($totalPendiente, 2, ',', '.') ?></h4>
            </div>
        </div>
    </div>
</div>

<!-- Tabla de ventas pendientes -->
<div class="card">
    <div class="card-header bg-warning text-dark d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Remitos con Saldo Pendiente</h5>
        <span class="badge bg-dark"><?= count($ventas) ?></span>
    </div>
    <div class="card-body p-0">
        <?php if (!empty($ventas)): ?>
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Remito #</th>
                        <th>Cliente</th>
                        <th>Fecha</th>
                        <th class="text-end">Total</th>
                        <th class="text-end">Cobrado</th>
                        <th class="text-end">Pendiente</th>
                        <th class="text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ventas as $v): ?>
                    <tr>
                        <td><strong>#<?= $v['remito_id'] ?></strong></td>
                        <td><?= htmlspecialchars($v['cliente']) ?></td>
                        <td><?= date('d/m/Y', strtotime($v['fecha'])) ?></td>
                        <td class="text-end">$ <?= number_format($v['monto_total'], 2, ',', '.') ?></td>
                        <td class="text-end text-success">$ <?= number_format($v['pagado'], 2, ',', '.') ?></td>
                        <td class="text-end fw-bold text-danger">$ <?= number_format($v['saldo_pendiente'], 2, ',', '.') ?></td>
                        <td class="text-center">
                            <button type="button" class="btn btn-sm btn-success btn-cobrar"
                                    data-remito-id="<?= $v['remito_id'] ?>"
                                    data-cliente-id="<?= $v['cliente_id'] ?>"
                                    data-cliente-nombre="<?= htmlspecialchars($v['cliente']) ?>"
                                    data-monto-total="<?= $v['monto_total'] ?>"
                                    data-pagado="<?= $v['pagado'] ?>"
                                    data-saldo="<?= $v['saldo_pendiente'] ?>"
                                    data-fecha="<?= $v['fecha'] ?>">
                                <i class="bi bi-cash"></i> Cobrar
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="table-light">
                    <tr>
                        <td colspan="3" class="text-end fw-bold">Totales:</td>
                        <td class="text-end fw-bold">$ <?= number_format(array_sum(array_column($ventas, 'monto_total')), 2, ',', '.') ?></td>
                        <td class="text-end fw-bold text-success">$ <?= number_format(array_sum(array_column($ventas, 'pagado')), 2, ',', '.') ?></td>
                        <td class="text-end fw-bold text-danger">$ <?= number_format($totalPendiente, 2, ',', '.') ?></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <?php else: ?>
        <div class="card-body text-center text-muted py-5">
            <i class="bi bi-check-circle fs-1"></i>
            <p class="mt-2 mb-0">No hay ventas pendientes de cobro.</p>
            <small>Todas las ventas están saldadas.</small>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal: Registrar Cobro por Remito -->
<div class="modal fade" id="modalCobro" tabindex="-1" aria-labelledby="modalCobroLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="<?= BASE_URL ?>/cobros/store" id="formCobroModal">
                <input type="hidden" name="cliente_id" id="modalClienteId">
                <input type="hidden" name="cliente_nombre" id="modalClienteNombre">
                <input type="hidden" name="remito_id" id="modalRemitoId">

                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="modalCobroLabel">
                        <i class="bi bi-cash-coin"></i> Registrar Cobro
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <!-- Info del remito -->
                    <div class="alert alert-light border mb-3">
                        <div class="row">
                            <div class="col-6">
                                <small class="text-muted d-block">Remito #</small>
                                <strong id="modalRemitoNum"></strong>
                            </div>
                            <div class="col-6">
                                <small class="text-muted d-block">Cliente</small>
                                <strong id="modalClienteDisplay"></strong>
                            </div>
                        </div>
                        <hr class="my-2">
                        <div class="row">
                            <div class="col-4">
                                <small class="text-muted d-block">Total Remito</small>
                                <span id="modalTotalRemito" class="fw-bold"></span>
                            </div>
                            <div class="col-4">
                                <small class="text-muted d-block">Ya Cobrado</small>
                                <span id="modalYaCobrado" class="text-success fw-bold"></span>
                            </div>
                            <div class="col-4">
                                <small class="text-muted d-block">Saldo Pendiente</small>
                                <span id="modalSaldoPendiente" class="text-danger fw-bold fs-5"></span>
                            </div>
                        </div>
                    </div>

                    <!-- Datos del cobro -->
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="modalMonto" class="form-label fw-bold">Monto a Cobrar *</label>
                            <input type="number" step="0.01" min="0.01" id="modalMonto" name="monto" 
                                   class="form-control form-control-lg" required>
                        </div>
                        <div class="col-md-6">
                            <label for="modalMedioPago" class="form-label fw-bold">Medio de Pago *</label>
                            <select id="modalMedioPago" name="medio_pago" class="form-select form-select-lg" required>
                                <option value="">Seleccionar...</option>
                                <option value="Efectivo">Efectivo</option>
                                <option value="Transferencia">Transferencia</option>
                                <option value="Cheque">Cheque</option>
                                <option value="Tarjeta">Tarjeta</option>
                                <option value="Otro">Otro</option>
                            </select>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="modalCajaBanco" class="form-label fw-bold">Caja / Banco</label>
                            <select id="modalCajaBanco" name="caja_banco_id" class="form-select">
                                <option value="">Sin asignar</option>
                                <?php foreach ($cajas as $cb): ?>
                                    <option value="<?= $cb['id'] ?>"><?= htmlspecialchars($cb['nombre']) ?> (<?= number_format((float)$cb['saldo_actual'], 2, ',', '.') ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="modalObservaciones" class="form-label">Observaciones</label>
                            <input type="text" id="modalObservaciones" name="observaciones" class="form-control" 
                                   placeholder="Nota del cobro...">
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success btn-lg" id="btnModalCobrar">
                        <i class="bi bi-check-lg"></i> Confirmar Cobro
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const modal = new bootstrap.Modal(document.getElementById('modalCobro'));
    const form = document.getElementById('formCobroModal');

    function formatMoney(val) {
        return '$ ' + val.toLocaleString('es-AR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    }

    function parseMoney(str) {
        // Remove $ and spaces, then handle Spanish format: 58.000,00 → 58000.00
        return parseFloat(str.replace(/[$\s]/g, '').replace(/\./g, '').replace(',', '.')) || 0;
    }

    // Abrir modal al hacer clic en "Cobrar"
    document.querySelectorAll('.btn-cobrar').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const remitoId = this.dataset.remitoId;
            const clienteId = this.dataset.clienteId;
            const clienteNombre = this.dataset.clienteNombre;
            const montoTotal = parseFloat(this.dataset.montoTotal) || 0;
            const pagado = parseFloat(this.dataset.pagado) || 0;
            const saldo = parseFloat(this.dataset.saldo) || 0;
            const fecha = this.dataset.fecha;

            // Llenar campos ocultos
            document.getElementById('modalRemitoId').value = remitoId;
            document.getElementById('modalClienteId').value = clienteId;
            document.getElementById('modalClienteNombre').value = clienteNombre;

            // Llenar info visible
            document.getElementById('modalRemitoNum').textContent = '#' + remitoId;
            document.getElementById('modalClienteDisplay').textContent = clienteNombre;
            document.getElementById('modalTotalRemito').textContent = formatMoney(montoTotal);
            document.getElementById('modalYaCobrado').textContent = formatMoney(pagado);
            document.getElementById('modalSaldoPendiente').textContent = formatMoney(saldo);

            // Prellenar monto con el saldo pendiente
            document.getElementById('modalMonto').value = saldo.toFixed(2);
            document.getElementById('modalMonto').max = saldo.toFixed(2);

            // Limpiar formulario
            document.getElementById('modalMedioPago').value = '';
            document.getElementById('modalCajaBanco').value = '';
            document.getElementById('modalObservaciones').value = '';

            modal.show();
        });
    });

    // Validar antes de enviar
    form.addEventListener('submit', function(e) {
        const monto = parseFloat(document.getElementById('modalMonto').value) || 0;
        const saldo = parseMoney(document.getElementById('modalSaldoPendiente').textContent);

        if (monto <= 0) {
            e.preventDefault();
            Swal.fire({icon: 'error', title: 'Monto inválido', text: 'El monto debe ser mayor a cero.'});
            return false;
        }

        if (monto > saldo + 0.01) {
            e.preventDefault();
            Swal.fire({icon: 'error', title: 'Monto excede el saldo', text: 'No puede cobrar más de lo pendiente.'});
            return false;
        }

        e.preventDefault();
        Swal.fire({
            title: '¿Registrar cobro?',
            text: 'Cobro de ' + formatMoney(monto) + ' para Remito #' + document.getElementById('modalRemitoId').value,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#28a745',
            confirmButtonText: 'Sí, registrar'
        }).then((result) => {
            if (result.isConfirmed) form.submit();
        });
    });
});
</script>
