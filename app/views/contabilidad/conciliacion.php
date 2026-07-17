<div class="mb-3">
    <a href="<?= BASE_URL ?>/contabilidad/cajas" class="text-decoration-none">
        <i class="bi bi-arrow-left"></i> Volver a Cajas
    </a>
</div>

<h3><i class="bi bi-check2-all"></i> <?= htmlspecialchars($title) ?></h3>

<!-- Selector de caja/banco -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="<?= BASE_URL ?>/contabilidad/conciliacion" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label">Caja / Banco</label>
                <select name="caja_id" class="form-select" required id="selectCaja">
                    <option value="">Seleccionar...</option>
                    <?php foreach ($cajas as $c): ?>
                        <option value="<?= $c['id'] ?>" 
                                data-tipo="<?= $c['tipo'] ?>"
                                <?= $cajaId == $c['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['nombre']) ?> (<?= $c['tipo'] ?>) - Saldo: $ <?= number_format($c['saldo_actual'], 2, ',', '.') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($cajaInfo && $cajaInfo['tipo'] === 'CAJA'): ?>
            <div class="col-md-3">
                <label class="form-label">Fecha de Control</label>
                <input type="date" name="fecha" class="form-control" value="<?= $fechaControl ?>">
            </div>
            <?php endif; ?>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i> Verificar</button>
            </div>
        </form>
    </div>
</div>

<?php if ($cajaId && $cajaInfo): ?>

<?php if ($cajaInfo['tipo'] === 'BANCO'): ?>
<!-- ===================================================== -->
<!-- CONCILIACIÓN BANCARIA -->
<!-- ===================================================== -->
<form method="POST" action="<?= BASE_URL ?>/contabilidad/conciliacion-store" id="formConciliacion">
    <input type="hidden" name="caja_id" value="<?= $cajaId ?>">
    <input type="hidden" name="tipo_conciliacion" value="BANCO">

    <!-- Panel de resumen -->
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="bi bi-bank"></i> Conciliación Bancaria - <?= htmlspecialchars($cajaInfo['nombre']) ?></h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-3">
                    <label class="form-label fw-bold">Fecha de Conciliación *</label>
                    <input type="date" name="fecha_conciliacion" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="col-md-3">
                    <div class="p-3 bg-light rounded text-center">
                        <small class="text-muted d-block">Saldo Sistema</small>
                        <h4 class="mb-0 text-primary" id="saldoSistema">$ <?= number_format($saldoSistema, 2, ',', '.') ?></h4>
                        <small class="text-muted">(movimientos no conciliados)</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">Saldo Según Extracto (Banco) *</label>
                    <input type="number" step="0.01" name="saldo_banco" class="form-control form-control-lg" 
                           id="saldoBanco" placeholder="0.00" required>
                </div>
                <div class="col-md-3">
                    <div class="p-3 rounded text-center" id="panelDiferencia">
                        <small class="text-muted d-block">Diferencia</small>
                        <h4 class="mb-0" id="diferencia">$ 0,00</h4>
                        <small id="diferenciaMsg"></small>
                    </div>
                </div>
            </div>
            <div class="row mt-3">
                <div class="col-md-6">
                    <label class="form-label">Observaciones</label>
                    <input type="text" name="observaciones" class="form-control" placeholder="Nota de conciliación...">
                </div>
            </div>
        </div>
    </div>

    <!-- Movimientos no conciliados -->
    <div class="card mb-4">
        <div class="card-header bg-warning text-dark d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Movimientos No Conciliados (<?= count($movimientos) ?>)</h5>
            <div>
                <span class="fw-bold">Seleccionados: <span id="cantSeleccionados">0</span></span>
                <span class="ms-3 fw-bold">Total: $ <span id="totalSeleccionados">0,00</span></span>
            </div>
        </div>
        <div class="card-body p-0">
            <?php if (!empty($movimientos)): ?>
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th width="40"><input type="checkbox" id="checkAll"></th>
                            <th>#</th>
                            <th>Fecha</th>
                            <th>Tipo</th>
                            <th>Descripción</th>
                            <th>Referencia</th>
                            <th class="text-end">Monto</th>
                            <th width="180">Nro. Transacción Banco</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($movimientos as $m): ?>
                        <tr>
                            <td><input type="checkbox" name="movimientos[]" value="<?= $m['id'] ?>" class="check-mov" data-monto="<?= $m['monto'] ?>"></td>
                            <td><?= $m['id'] ?></td>
                            <td><?= date('d/m/Y', strtotime($m['fecha'])) ?></td>
                            <td>
                                <span class="badge <?= $m['tipo'] === 'INGRESO' ? 'bg-success' : 'bg-danger' ?>">
                                    <?= $m['tipo'] ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($m['descripcion'] ?? '-') ?></td>
                            <td>
                                <?php if ($m['referencia_modulo']): ?>
                                    <small class="text-muted"><?= htmlspecialchars($m['referencia_modulo']) ?> #<?= $m['referencia_id'] ?></small>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td class="text-end">$ <?= number_format($m['monto'], 2, ',', '.') ?></td>
                            <td>
                                <input type="text" name="num_transaccion[<?= $m['id'] ?>]" 
                                       class="form-control form-control-sm num-transaccion" 
                                       placeholder="TX-00123..." disabled>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="card-body text-center text-muted">
                <i class="bi bi-check-circle fs-1"></i>
                <p class="mt-2">No hay movimientos pendientes de conciliación.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Botón confirmar -->
    <?php if (!empty($movimientos)): ?>
    <div class="d-flex gap-2 mb-4">
        <button type="submit" class="btn btn-success" id="btnConciliar" disabled>
            <i class="bi bi-check2-all"></i> Confirmar Conciliación
        </button>
    </div>
    <?php endif; ?>
</form>

<?php elseif ($cajaInfo['tipo'] === 'CAJA'): ?>
<!-- ===================================================== -->
<!-- CONTROL DE CAJA DIARIO (ARQUEO) -->
<!-- ===================================================== -->
<form method="POST" action="<?= BASE_URL ?>/contabilidad/conciliacion-store" id="formConciliacion">
    <input type="hidden" name="caja_id" value="<?= $cajaId ?>">
    <input type="hidden" name="tipo_conciliacion" value="CAJA">

    <!-- Panel de resumen -->
    <div class="card mb-4">
        <div class="card-header bg-success text-white">
            <h5 class="mb-0"><i class="bi bi-wallet2"></i> Control de Caja Diario - <?= htmlspecialchars($cajaInfo['nombre']) ?></h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-3">
                    <label class="form-label fw-bold">Fecha de Control *</label>
                    <input type="date" name="fecha_conciliacion" class="form-control" value="<?= $fechaControl ?>" required>
                </div>
                <?php if ($resumenDia): ?>
                <div class="col-md-2">
                    <div class="p-2 bg-light rounded text-center">
                        <small class="text-muted d-block">Saldo Apertura</small>
                        <h5 class="mb-0">$ <?= number_format($resumenDia['saldo_apertura'], 2, ',', '.') ?></h5>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="p-2 bg-success bg-opacity-10 rounded text-center">
                        <small class="text-muted d-block">+ Ingresos</small>
                        <h5 class="mb-0 text-success">$ <?= number_format($resumenDia['ingresos'], 2, ',', '.') ?></h5>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="p-2 bg-danger bg-opacity-10 rounded text-center">
                        <small class="text-muted d-block">- Egresos</small>
                        <h5 class="mb-0 text-danger">$ <?= number_format($resumenDia['egresos'], 2, ',', '.') ?></h5>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="p-2 bg-primary bg-opacity-10 rounded text-center">
                        <small class="text-muted d-block">Saldo Sistema</small>
                        <h4 class="mb-0 text-primary">$ <?= number_format($resumenDia['saldo_calculado'], 2, ',', '.') ?></h4>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <hr>
            <div class="row">
                <div class="col-md-4">
                    <label class="form-label fw-bold">Saldo según Arqueo de Caja (Físico) *</label>
                    <input type="number" step="0.01" name="saldo_banco" class="form-control form-control-lg" 
                           id="saldoBanco" placeholder="0.00" required>
                </div>
                <div class="col-md-4">
                    <div class="p-3 rounded text-center" id="panelDiferencia">
                        <small class="text-muted d-block">Diferencia</small>
                        <h4 class="mb-0" id="diferencia">$ 0,00</h4>
                        <small id="diferenciaMsg"></small>
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Observaciones</label>
                    <input type="text" name="observaciones" class="form-control" placeholder="Observaciones del arqueo...">
                </div>
            </div>
        </div>
    </div>

    <!-- Movimientos del día -->
    <div class="card mb-4">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Movimientos del Día <?= date('d/m/Y', strtotime($fechaControl)) ?> (<?= count($movimientos) ?>)</h5>
            <div>
                <span class="fw-bold">Seleccionados: <span id="cantSeleccionados">0</span></span>
                <span class="ms-3 fw-bold">Total: $ <span id="totalSeleccionados">0,00</span></span>
            </div>
        </div>
        <div class="card-body p-0">
            <?php if (!empty($movimientos)): ?>
            <table class="table table-sm table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th width="40"><input type="checkbox" id="checkAll"></th>
                        <th>#</th>
                        <th>Tipo</th>
                        <th>Descripción</th>
                        <th>Referencia</th>
                        <th class="text-end">Monto</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($movimientos as $m): ?>
                    <tr>
                        <td><input type="checkbox" name="movimientos[]" value="<?= $m['id'] ?>" class="check-mov" data-monto="<?= $m['monto'] ?>"></td>
                        <td><?= $m['id'] ?></td>
                        <td>
                            <span class="badge <?= $m['tipo'] === 'INGRESO' ? 'bg-success' : 'bg-danger' ?>">
                                <?= $m['tipo'] ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars($m['descripcion'] ?? '-') ?></td>
                        <td>
                            <?php if ($m['referencia_modulo']): ?>
                                <small class="text-muted"><?= htmlspecialchars($m['referencia_modulo']) ?> #<?= $m['referencia_id'] ?></small>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td class="text-end">$ <?= number_format($m['monto'], 2, ',', '.') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="table-light">
                    <tr>
                        <td colspan="5" class="text-end fw-bold">Total:</td>
                        <td class="text-end fw-bold">
                            $ <?= number_format(array_sum(array_column($movimientos, 'monto')), 2, ',', '.') ?>
                        </td>
                    </tr>
                </tfoot>
            </table>
            <?php else: ?>
            <div class="card-body text-center text-muted">
                <i class="bi bi-inbox fs-1"></i>
                <p class="mt-2">No hay movimientos registrados en esta fecha.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Botón confirmar -->
    <?php if (!empty($movimientos)): ?>
    <div class="d-flex gap-2 mb-4">
        <button type="submit" class="btn btn-success" id="btnConciliar" disabled>
            <i class="bi bi-check2-all"></i> Confirmar Control de Caja
        </button>
    </div>
    <?php endif; ?>
</form>
<?php endif; ?>

<!-- Conciliaciones anteriores -->
<?php if (!empty($conciliaciones)): ?>
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">Conciliaciones Anteriores</h5>
    </div>
    <div class="card-body p-0">
        <table class="table table-sm mb-0">
            <thead class="table-light">
                <tr>
                    <th>#</th>
                    <th>Fecha</th>
                    <th class="text-end">Saldo Banco/Arqueo</th>
                    <th class="text-end">Saldo Sistema</th>
                    <th class="text-end">Diferencia</th>
                    <th>Estado</th>
                    <th>Registrado por</th>
                    <th class="text-center">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($conciliaciones as $conc): ?>
                <tr>
                    <td><?= $conc['id'] ?></td>
                    <td><?= date('d/m/Y', strtotime($conc['fecha_conciliacion'])) ?></td>
                    <td class="text-end">$ <?= number_format($conc['saldo_segun_banco'], 2, ',', '.') ?></td>
                    <td class="text-end">$ <?= number_format($conc['saldo_segun_sistema'], 2, ',', '.') ?></td>
                    <td class="text-end <?= abs($conc['diferencia']) < 0.01 ? 'text-success' : 'text-danger' ?>">
                        $ <?= number_format($conc['diferencia'], 2, ',', '.') ?>
                    </td>
                    <td>
                        <span class="badge <?= $conc['estado'] === 'CONCILIADA' ? 'bg-success' : 'bg-warning' ?>">
                            <?= $conc['estado'] ?>
                        </span>
                    </td>
                    <td><?= htmlspecialchars($conc['usuario_nombre']) ?></td>
                    <td class="text-center">
                        <button type="button" class="btn btn-sm btn-outline-primary btn-ver-detalle" 
                                data-id="<?= $conc['id'] ?>" title="Ver detalle">
                            <i class="bi bi-eye"></i>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Modal: Detalle de Conciliación -->
<div class="modal fade" id="modalDetalle" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-list-check"></i> Detalle de Conciliación #<span id="mdId"></span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Resumen -->
                <div class="row mb-3">
                    <div class="col-md-3">
                        <small class="text-muted d-block">Fecha</small>
                        <strong id="mdFecha"></strong>
                    </div>
                    <div class="col-md-3">
                        <small class="text-muted d-block">Saldo Banco</small>
                        <strong id="mdSaldoBanco"></strong>
                    </div>
                    <div class="col-md-3">
                        <small class="text-muted d-block">Saldo Sistema</small>
                        <strong id="mdSaldoSistema"></strong>
                    </div>
                    <div class="col-md-3">
                        <small class="text-muted d-block">Diferencia</small>
                        <strong id="mdDiferencia"></strong>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <small class="text-muted d-block">Estado</small>
                        <span id="mdEstado" class="badge"></span>
                    </div>
                    <div class="col-md-6">
                        <small class="text-muted d-block">Observaciones</small>
                        <span id="mdObs"></span>
                    </div>
                </div>
                <hr>
                <!-- Detalle de movimientos -->
                <h6 class="fw-bold">Movimientos Conciliados</h6>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Fecha</th>
                                <th>Tipo</th>
                                <th>Descripción</th>
                                <th class="text-end">Monto</th>
                                <th>Nro. Transacción</th>
                                <th class="text-center">Conciliado</th>
                            </tr>
                        </thead>
                        <tbody id="mdDetalleBody">
                            <tr><td colspan="6" class="text-center text-muted">Cargando...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const checkAll = document.getElementById('checkAll');
    const btnConciliar = document.getElementById('btnConciliar');
    const cantEl = document.getElementById('cantSeleccionados');
    const totalEl = document.getElementById('totalSeleccionados');
    const saldoBancoInput = document.getElementById('saldoBanco');
    const saldoSistemaEl = document.getElementById('saldoSistema');
    const diferenciaEl = document.getElementById('diferencia');
    const diferenciaMsgEl = document.getElementById('diferenciaMsg');
    const panelDiferencia = document.getElementById('panelDiferencia');
    const form = document.getElementById('formConciliacion');
    const numTransInputs = document.querySelectorAll('.num-transaccion');

    // Detectar tipo de conciliación
    const tipoInput = document.querySelector('input[name="tipo_conciliacion"]');
    const esBanco = tipoInput && tipoInput.value === 'BANCO';

    // Obtener saldo sistema del panel o del resumen del día
    let saldoSistema = 0;
    if (saldoSistemaEl) {
        saldoSistema = parseFloat(saldoSistemaEl.dataset.valor || '0');
    } else if (typeof resumenDia !== 'undefined' && resumenDia) {
        saldoSistema = resumenDia.saldo_calculado;
    }

    // Si el saldo sistema viene del PHP, obtenerlo del texto
    const saldoSistemaText = saldoSistemaEl ? saldoSistemaEl.textContent.trim() : '';
    if (saldoSistemaText) {
        saldoSistema = parseMoney(saldoSistemaText);
    }

    function parseMoney(str) {
        return parseFloat(str.replace(/[^0-9,-]/g, '').replace(',', '.')) || 0;
    }

    function formatMoney(val) {
        return val.toLocaleString('es-AR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    }

    function actualizarResumen() {
        const checks = document.querySelectorAll('.check-mov');
        let cant = 0;
        let total = 0;
        checks.forEach(function(cb) {
            if (cb.checked) {
                cant++;
                total += parseFloat(cb.dataset.monto) || 0;
                // Habilitar input de transacción si es banco
                const row = cb.closest('tr');
                const input = row.querySelector('.num-transaccion');
                if (input) input.disabled = false;
            } else {
                const row = cb.closest('tr');
                const input = row.querySelector('.num-transaccion');
                if (input) {
                    input.disabled = true;
                    input.value = '';
                }
            }
        });
        cantEl.textContent = cant;
        totalEl.textContent = formatMoney(total);
        actualizarDiferencia();
    }

    function actualizarDiferencia() {
        const saldoBanco = parseFloat(saldoBancoInput.value) || 0;
        const diferencia = saldoBanco - saldoSistema;
        
        diferenciaEl.textContent = '$ ' + formatMoney(diferencia);
        
        if (Math.abs(diferencia) < 0.01) {
            panelDiferencia.className = 'p-3 rounded text-center bg-success bg-opacity-10';
            diferenciaEl.className = 'mb-0 text-success';
            diferenciaMsgEl.textContent = '✓ Conciliado';
            diferenciaMsgEl.className = 'text-success';
            if (btnConciliar) btnConciliar.disabled = false;
        } else {
            panelDiferencia.className = 'p-3 rounded text-center bg-danger bg-opacity-10';
            diferenciaEl.className = 'mb-0 text-danger';
            diferenciaMsgEl.textContent = 'Diferencia pendiente';
            diferenciaMsgEl.className = 'text-danger';
            // Permitir conciliar aunque haya diferencia (se registra como PENDIENTE)
            const cantChecks = document.querySelectorAll('.check-mov:checked').length;
            if (btnConciliar) btnConciliar.disabled = cantChecks === 0;
        }
    }

    if (checkAll) {
        checkAll.addEventListener('change', function() {
            document.querySelectorAll('.check-mov').forEach(cb => cb.checked = this.checked);
            actualizarResumen();
        });
    }

    document.querySelectorAll('.check-mov').forEach(function(cb) {
        cb.addEventListener('change', actualizarResumen);
    });

    if (saldoBancoInput) {
        saldoBancoInput.addEventListener('input', actualizarDiferencia);
    }

    // Validar antes de enviar
    if (form) {
        form.addEventListener('submit', function(e) {
            const checks = document.querySelectorAll('.check-mov:checked');
            if (checks.length === 0) {
                e.preventDefault();
                Swal.fire({icon: 'error', title: 'Sin selección', text: 'Seleccione al menos un movimiento.'});
                return false;
            }

            // Validar números de transacción si es banco
            if (esBanco) {
                let sinNumero = 0;
                checks.forEach(function(cb) {
                    const row = cb.closest('tr');
                    const input = row.querySelector('.num-transaccion');
                    if (input && !input.value.trim()) {
                        sinNumero++;
                    }
                });
                if (sinNumero > 0) {
                    e.preventDefault();
                    Swal.fire({
                        icon: 'warning', 
                        title: 'Faltan números de transacción', 
                        text: sinNumero + ' movimiento(s) sin número de transacción del banco.',
                        confirmButtonText: 'Entendido'
                    });
                    return false;
                }
            }

            e.preventDefault();
            Swal.fire({
                title: '¿Confirmar conciliación?',
                text: 'Se marcarán ' + checks.length + ' movimiento(s) como conciliados.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#28a745',
                confirmButtonText: 'Sí, confirmar'
            }).then((result) => {
                if (result.isConfirmed) form.submit();
            });
        });
    }

    // Ver detalle de conciliación (modal)
    const modalDetalle = document.getElementById('modalDetalle');
    if (modalDetalle) {
        const bsModal = new bootstrap.Modal(modalDetalle);

        document.querySelectorAll('.btn-ver-detalle').forEach(function(btn) {
            btn.addEventListener('click', function() {
                const id = this.dataset.id;
                document.getElementById('mdDetalleBody').innerHTML = '<tr><td colspan="6" class="text-center text-muted">Cargando...</td></tr>';
                bsModal.show();

                fetch('<?= BASE_URL ?>/contabilidad/conciliacion-detalle/' + id)
                    .then(r => r.json())
                    .then(data => {
                        if (data.error) {
                            document.getElementById('mdDetalleBody').innerHTML = '<tr><td colspan="6" class="text-center text-danger">' + data.error + '</td></tr>';
                            return;
                        }

                        // Llenar resumen
                        document.getElementById('mdId').textContent = data.id;
                        document.getElementById('mdFecha').textContent = new Date(data.fecha_conciliacion + 'T00:00:00').toLocaleDateString('es-AR');
                        document.getElementById('mdSaldoBanco').textContent = '$ ' + parseFloat(data.saldo_segun_banco).toLocaleString('es-AR', {minimumFractionDigits: 2});
                        document.getElementById('mdSaldoSistema').textContent = '$ ' + parseFloat(data.saldo_segun_sistema).toLocaleString('es-AR', {minimumFractionDigits: 2});
                        
                        const dif = parseFloat(data.diferencia);
                        const mdDif = document.getElementById('mdDiferencia');
                        mdDif.textContent = '$ ' + dif.toLocaleString('es-AR', {minimumFractionDigits: 2});
                        mdDif.className = Math.abs(dif) < 0.01 ? 'text-success' : 'text-danger';

                        const mdEstado = document.getElementById('mdEstado');
                        mdEstado.textContent = data.estado;
                        mdEstado.className = 'badge ' + (data.estado === 'CONCILIADA' ? 'bg-success' : 'bg-warning');

                        document.getElementById('mdObs').textContent = data.observaciones || '-';

                        // Llenar tabla de detalle
                        const tbody = document.getElementById('mdDetalleBody');
                        if (data.detalle && data.detalle.length > 0) {
                            let html = '';
                            data.detalle.forEach(function(d) {
                                const tipo = d.mov_tipo || '-';
                                const badgeClass = tipo === 'INGRESO' ? 'bg-success' : (tipo === 'EGRESO' ? 'bg-danger' : 'bg-secondary');
                                html += '<tr>';
                                html += '<td>' + new Date(d.fecha_movimiento + 'T00:00:00').toLocaleDateString('es-AR') + '</td>';
                                html += '<td><span class="badge ' + badgeClass + '">' + tipo + '</span></td>';
                                html += '<td>' + (d.descripcion || d.mov_descripcion || '-') + '</td>';
                                html += '<td class="text-end">$ ' + parseFloat(d.monto).toLocaleString('es-AR', {minimumFractionDigits: 2}) + '</td>';
                                html += '<td>' + (d.numero_transaccion || '<em class="text-muted">-</em>') + '</td>';
                                html += '<td class="text-center">' + (d.conciliado ? '<i class="bi bi-check-circle-fill text-success"></i>' : '<i class="bi bi-x-circle text-danger"></i>') + '</td>';
                                html += '</tr>';
                            });
                            tbody.innerHTML = html;
                        } else {
                            tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">Sin detalle</td></tr>';
                        }
                    })
                    .catch(() => {
                        document.getElementById('mdDetalleBody').innerHTML = '<tr><td colspan="6" class="text-center text-danger">Error al cargar</td></tr>';
                    });
            });
        });
    }
});
</script>
