<h3><i class="bi bi-arrow-return-left"></i> Nueva Devolución</h3>

<?php if (!empty($_SESSION['error'])): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error']) ?></div>
    <?php unset($_SESSION['error']); ?>
<?php endif; ?>

<form method="POST" action="<?= BASE_URL ?>/devoluciones/store" id="formDevolucion">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::generate()) ?>">
    <input type="hidden" name="remito_id" id="remito_id" value="">
    <input type="hidden" name="cliente_id" id="cliente_id" value="">
    <input type="hidden" name="cliente_nombre" id="cliente_nombre" value="">

    <!-- Paso 1: Buscar Remito -->
    <div class="card mb-3">
        <div class="card-header bg-primary text-white">
            <h6 class="mb-0"><i class="bi bi-search"></i> 1. Seleccionar Remito</h6>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <label class="form-label">Buscar remito por número o cliente</label>
                    <input type="text" id="buscarRemito" class="form-control"
                           placeholder="Ingrese número de remito o nombre del cliente..." autocomplete="off">
                    <div id="resultadosRemito" class="list-group mt-1" style="position:absolute; z-index:1000;"></div>
                </div>
                <div class="col-md-6">
                    <div id="infoRemito" style="display:none;">
                        <p class="mb-1"><strong>Remito:</strong> #<span id="remNumero"></span></p>
                        <p class="mb-1"><strong>Cliente:</strong> <span id="remCliente"></span></p>
                        <p class="mb-0"><strong>Fecha:</strong> <span id="remFecha"></span></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Paso 2: Productos a Devolver -->
    <div class="card mb-3" id="cardProductos" style="display:none;">
        <div class="card-header bg-info text-white">
            <h6 class="mb-0"><i class="bi bi-box"></i> 2. Evaluar Productos Devueltos</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" id="tablaProductos">
                    <thead class="table-dark">
                        <tr>
                            <th>Producto</th>
                            <th class="text-center" width="90">Cant. Remitida</th>
                            <th class="text-center" width="90">Cant. Devolver</th>
                            <th width="160">Condición</th>
                            <th class="text-center" width="90">Reingresa</th>
                            <th class="text-center" width="90">Descarta</th>
                            <th class="text-end" width="100">Precio U.</th>
                            <th class="text-end" width="100">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody id="bodyProductos"></tbody>
                    <tfoot>
                        <tr class="table-success fw-bold">
                            <td colspan="7" class="text-end">TOTAL:</td>
                            <td class="text-end" id="totalGeneral">$ 0,00</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <!-- Paso 3: Motivo -->
    <div class="card mb-3" id="cardMotivo" style="display:none;">
        <div class="card-header bg-secondary text-white">
            <h6 class="mb-0"><i class="bi bi-chat-left-text"></i> 3. Motivo de Devolución</h6>
        </div>
        <div class="card-body">
            <textarea name="motivo" id="motivo" class="form-control" rows="3"
                      placeholder="Describa el motivo de la devolución..." required></textarea>
        </div>
    </div>

    <!-- Paso 4: Reembolso -->
    <div class="card mb-3" id="cardReembolso" style="display:none;">
        <div class="card-header bg-warning text-dark">
            <h6 class="mb-0"><i class="bi bi-cash-stack"></i> 4. Reembolso</h6>
        </div>
        <div class="card-body">
            <div class="alert alert-info mb-3" id="deudaInfo" style="display:none;">
                <div class="row">
                    <div class="col-md-4"><strong>Deuda del cliente:</strong> <span id="deudaMonto">$ 0,00</span></div>
                    <div class="col-md-4"><strong>Total devolución:</strong> <span id="devolucionTotal">$ 0,00</span></div>
                    <div class="col-md-4"><strong>Saldo:</strong> <span id="saldoReembolsar" class="fw-bold">$ 0,00</span></div>
                </div>
            </div>

            <div id="bloqueNC" class="mb-3" style="display:none;">
                <h6 class="text-primary"><i class="bi bi-file-earmark-text"></i> Nota de Crédito (CtaCte)</h6>
                <p class="text-muted small" id="ncLabel">Se acreditará el monto total de la devolución al saldo del cliente.</p>
                <div class="row">
                    <div class="col-md-6">
                        <label class="form-label">Monto NC</label>
                        <input type="text" id="montoNCDisplay" class="form-control" readonly>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Observaciones</label>
                        <input type="text" id="obsNC" class="form-control" value="Compensación de deuda pendiente">
                    </div>
                </div>
            </div>

            <div id="bloqueExceso" class="mb-3" style="display:none;">
                <h6 class="text-success"><i class="bi bi-cash-stack"></i> Reembolso en Efectivo / Transferencia</h6>
                <p class="text-muted small">El exceso sobre la deuda se devuelve por este medio.</p>
                <div class="row">
                    <div class="col-md-3">
                        <label class="form-label">Método</label>
                        <select id="metodoExceso" class="form-select">
                            <option value="EFECTIVO">Efectivo</option>
                            <option value="TRANSFERENCIA">Transferencia Bancaria</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Caja / Banco</label>
                        <select id="cajaBancoExceso" class="form-select">
                            <option value="">Seleccionar...</option>
                            <?php foreach ($cajas as $c): ?>
                                <option value="<?= $c['id'] ?>" data-saldo="<?= (float)$c['saldo_actual'] ?>">
                                    <?= htmlspecialchars($c['nombre']) ?> (<?= number_format((float)$c['saldo_actual'], 2, ',', '.') ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Monto</label>
                        <input type="text" id="montoExcesoDisplay" class="form-control" readonly>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Observaciones</label>
                        <input type="text" id="obsExceso" class="form-control">
                    </div>
                </div>
            </div>

            <div id="bloqueSinDeuda" style="display:none;">
                <h6 class="text-success"><i class="bi bi-cash-stack"></i> Método de Reembolso</h6>
                <div class="row">
                    <div class="col-md-4">
                        <label class="form-label">Método</label>
                        <select id="metodoSinDeuda" class="form-select">
                            <option value="NOTA_CREDITO">Nota de Crédito (CtaCte)</option>
                            <option value="EFECTIVO">Efectivo</option>
                            <option value="TRANSFERENCIA">Transferencia Bancaria</option>
                        </select>
                    </div>
                    <div class="col-md-4" id="divCajaSinDeuda" style="display:none;">
                        <label class="form-label">Caja / Banco</label>
                        <select id="cajaBancoSinDeuda" class="form-select">
                            <option value="">Seleccionar...</option>
                            <?php foreach ($cajas as $c): ?>
                                <option value="<?= $c['id'] ?>" data-saldo="<?= (float)$c['saldo_actual'] ?>">
                                    <?= htmlspecialchars($c['nombre']) ?> (<?= number_format((float)$c['saldo_actual'], 2, ',', '.') ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Monto del Reembolso</label>
                        <input type="text" id="montoSinDeudaDisplay" class="form-control" readonly>
                    </div>
                </div>
                <div class="row mt-2">
                    <div class="col-md-12">
                        <label class="form-label">Observaciones</label>
                        <input type="text" id="obsSinDeuda" class="form-control">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2 mt-3" id="botonesAccion" style="display:none;">
        <button type="submit" class="btn btn-success" id="btnProcesar">
            <i class="bi bi-check-lg"></i> Procesar Devolución
        </button>
        <a href="<?= BASE_URL ?>/devoluciones" class="btn btn-secondary">Cancelar</a>
    </div>
</form>

<script>
let remitoData = null;
let deudaCliente = 0;

document.getElementById('buscarRemito').addEventListener('input', function() {
    const q = this.value.trim();
    const resultados = document.getElementById('resultadosRemito');
    if (q.length < 1) { resultados.innerHTML = ''; return; }
    fetch('<?= BASE_URL ?>/devoluciones/buscar-remito?q=' + encodeURIComponent(q))
        .then(r => r.json())
        .then(data => {
            resultados.innerHTML = '';
            data.forEach(r => {
                const item = document.createElement('button');
                item.type = 'button';
                item.className = 'list-group-item list-group-item-action';
                item.textContent = `Remito #${r.numero} - ${r.cliente_nombre} (${r.created_at.substring(0,10)})`;
                item.addEventListener('click', () => cargarRemito(r.id));
                resultados.appendChild(item);
            });
        });
});

function cargarRemito(id) {
    document.getElementById('resultadosRemito').innerHTML = '';
    document.getElementById('buscarRemito').value = '';
    fetch('<?= BASE_URL ?>/devoluciones/items-remito/' + id)
        .then(r => r.json())
        .then(data => {
            if (data.error) { Swal.fire('Error', data.error, 'error'); return; }
            remitoData = data;
            document.getElementById('remito_id').value = data.id;
            document.getElementById('cliente_id').value = data.cliente_id;
            document.getElementById('cliente_nombre').value = data.cliente_nombre || '';
            document.getElementById('remNumero').textContent = data.numero;
            document.getElementById('remCliente').textContent = data.cliente_nombre;
            document.getElementById('remFecha').textContent = data.created_at.substring(0,10);
            document.getElementById('infoRemito').style.display = 'block';
            document.getElementById('cardProductos').style.display = 'block';
            document.getElementById('cardMotivo').style.display = 'block';
            document.getElementById('cardReembolso').style.display = 'block';
            document.getElementById('botonesAccion').style.display = 'flex';
            renderProductos(data.detalle);
            cargarDeudaCliente(data.cliente_id, data.cliente_nombre || '');
        });
}

function cargarDeudaCliente(clienteId, clienteNombre) {
    const params = new URLSearchParams({ cliente_id: clienteId, cliente_nombre: clienteNombre });
    fetch('<?= BASE_URL ?>/devoluciones/deudas-cliente?' + params.toString())
        .then(r => r.json())
        .then(data => {
            deudaCliente = parseFloat(data.deuda) || 0;
            actualizarReembolso();
        })
        .catch(() => { deudaCliente = 0; actualizarReembolso(); });
}

function calcularTotalDevolucion() {
    let total = 0;
    document.querySelectorAll('.cantidad-input').forEach(input => {
        const prod = input.dataset.producto;
        const cant = parseFloat(input.value) || 0;
        const precio = parseFloat(input.dataset.precio) || 0;
        total += cant * precio;
        document.querySelector(`.subtotal-cell[data-producto="${prod}"]`).textContent = '$ ' + (cant * precio).toFixed(2).replace('.', ',');
    });
    return total;
}

function filtrarCajas(selectEl, monto) {
    let seleccionValida = false;
    selectEl.querySelectorAll('option[data-saldo]').forEach(opt => {
        const saldo = parseFloat(opt.dataset.saldo) || 0;
        const insuficiente = saldo < monto - 0.01;
        opt.disabled = insuficiente;
        if (insuficiente && opt.selected) {
            selectEl.value = '';
        } else if (opt.selected && !insuficiente) {
            seleccionValida = true;
        }
    });
    return seleccionValida;
}

function actualizarReembolso() {
    const total = calcularTotalDevolucion();
    const deuda = deudaCliente;

    document.getElementById('totalGeneral').textContent = '$ ' + total.toFixed(2).replace('.', ',');
    document.getElementById('deudaMonto').textContent = '$ ' + deuda.toFixed(2).replace('.', ',');
    document.getElementById('devolucionTotal').textContent = '$ ' + total.toFixed(2).replace('.', ',');
    document.getElementById('deudaInfo').style.display = 'block';

    document.getElementById('bloqueNC').style.display = 'none';
    document.getElementById('bloqueExceso').style.display = 'none';
    document.getElementById('bloqueSinDeuda').style.display = 'none';

    if (total <= 0) {
        document.getElementById('saldoReembolsar').textContent = '$ 0,00';
        filtrarCajas(document.getElementById('cajaBancoExceso'), 0);
        filtrarCajas(document.getElementById('cajaBancoSinDeuda'), 0);
        return;
    }

    if (deuda > 0 && total <= deuda) {
        document.getElementById('saldoReembolsar').textContent = 'Deuda restante: $ ' + (deuda - total).toFixed(2).replace('.', ',');
        document.getElementById('saldoReembolsar').className = 'fw-bold text-primary';
        document.getElementById('bloqueNC').style.display = 'block';
        document.getElementById('montoNCDisplay').value = '$ ' + total.toFixed(2).replace('.', ',');
        document.getElementById('ncLabel').textContent = 'La Nota de Crédito compensa parte de la deuda pendiente.';
    } else if (deuda > 0 && total > deuda) {
        const exceso = total - deuda;
        document.getElementById('saldoReembolsar').textContent = 'A devolver en efectivo: $ ' + exceso.toFixed(2).replace('.', ',');
        document.getElementById('saldoReembolsar').className = 'fw-bold text-danger';
        document.getElementById('bloqueNC').style.display = 'block';
        document.getElementById('montoNCDisplay').value = '$ ' + total.toFixed(2).replace('.', ',');
        document.getElementById('ncLabel').textContent = 'La Nota de Crédito cubre la deuda y el exceso se reembolsa en efectivo/transferencia.';
        document.getElementById('bloqueExceso').style.display = 'block';
        document.getElementById('montoExcesoDisplay').value = '$ ' + exceso.toFixed(2).replace('.', ',');
        filtrarCajas(document.getElementById('cajaBancoExceso'), exceso);
        filtrarCajas(document.getElementById('cajaBancoSinDeuda'), 0);
    } else {
        document.getElementById('saldoReembolsar').textContent = '$ ' + total.toFixed(2).replace('.', ',');
        document.getElementById('saldoReembolsar').className = 'fw-bold text-success';
        document.getElementById('bloqueSinDeuda').style.display = 'block';
        document.getElementById('montoSinDeudaDisplay').value = '$ ' + total.toFixed(2).replace('.', ',');
        filtrarCajas(document.getElementById('cajaBancoSinDeuda'), total);
        filtrarCajas(document.getElementById('cajaBancoExceso'), 0);
    }
}

function renderProductos(detalle) {
    const tbody = document.getElementById('bodyProductos');
    tbody.innerHTML = '';
    detalle.forEach(item => {
        const precio = parseFloat(item.precio_unitario || 0);
        const stock = parseFloat(item.stock_actual || 0);
        const cantRemitida = parseFloat(item.CantRem || 0);
        const yaDevuelto = parseFloat(item.ya_devuelto || 0);
        const pendiente = parseFloat(item.pendiente_devolver || cantRemitida);
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>
                <input type="hidden" name="items[${item.producto_id}][precio]" value="${precio}">
                <input type="hidden" name="items[${item.producto_id}][producto_id]" value="${item.producto_id}">
                ${item.producto_nombre}
                <small class="text-muted">(Stock: ${stock})</small>
            </td>
            <td class="text-center">
                ${cantRemitida.toFixed(2)}
                ${yaDevuelto > 0 ? '<br><small class="text-danger">Ya devuelto: ' + yaDevuelto.toFixed(2) + '</small>' : ''}
            </td>
            <td>
                <input type="number" name="items[${item.producto_id}][cantidad]"
                       class="form-control form-control-sm cantidad-input"
                       min="0" max="${pendiente}" step="0.01" value="0"
                       data-precio="${precio}" data-producto="${item.producto_id}"
                       ${pendiente <= 0 ? 'disabled' : ''}>
            </td>
            <td>
                <select name="items[${item.producto_id}][condicion]" class="form-select form-select-sm condicion-input">
                    <option value="BUEN_ESTADO">Buen Estado</option>
                    <option value="NUEVO">Nuevo</option>
                    <option value="ESTADO_REGULAR">Estado Regular</option>
                    <option value="DANADO">Dañado</option>
                    <option value="INSERVIBLE">Inservible</option>
                </select>
            </td>
            <td>
                <input type="number" name="items[${item.producto_id}][reingresa]"
                       class="form-control form-control-sm reingresa-input"
                       min="0" step="0.01" value="0" data-producto="${item.producto_id}" readonly>
            </td>
            <td>
                <input type="number" name="items[${item.producto_id}][descarta]"
                       class="form-control form-control-sm descarta-input"
                       min="0" step="0.01" value="0" data-producto="${item.producto_id}">
            </td>
            <td class="text-end">$ ${precio.toFixed(2)}</td>
            <td class="text-end subtotal-cell" data-producto="${item.producto_id}">$ 0,00</td>
        `;
        tbody.appendChild(tr);
    });

    document.querySelectorAll('.cantidad-input').forEach(input => {
        input.addEventListener('input', function() {
            const prod = this.dataset.producto;
            const cant = parseFloat(this.value) || 0;
            document.querySelector(`.reingresa-input[data-producto="${prod}"]`).value = cant;
            const descartaEl = document.querySelector(`.descarta-input[data-producto="${prod}"]`);
            descartaEl.value = 0;
            descartaEl.setAttribute('max', cant);
            descartaEl.classList.remove('is-invalid');
            actualizarReembolso();
        });
    });

    document.querySelectorAll('.descarta-input').forEach(input => {
        input.addEventListener('input', function() {
            const prod = this.dataset.producto;
            const reingresa = parseFloat(document.querySelector(`.reingresa-input[data-producto="${prod}"]`).value) || 0;
            this.classList.toggle('is-invalid', parseFloat(this.value) > reingresa);
        });
    });

    document.querySelectorAll('.condicion-input').forEach(select => {
        select.addEventListener('change', function() {
            const tr = this.closest('tr');
            const prod = tr.querySelector('.cantidad-input').dataset.producto;
            const cant = parseFloat(tr.querySelector('.cantidad-input').value) || 0;
            tr.querySelector('.reingresa-input').value = cant;
            const descartaEl = tr.querySelector('.descarta-input');
            descartaEl.value = ['DANADO', 'INSERVIBLE'].includes(this.value) ? cant : 0;
            descartaEl.setAttribute('max', cant);
            descartaEl.classList.remove('is-invalid');
        });
    });
}

document.getElementById('metodoSinDeuda').addEventListener('change', function() {
    document.getElementById('divCajaSinDeuda').style.display =
        ['EFECTIVO', 'TRANSFERENCIA'].includes(this.value) ? 'block' : 'none';
});

document.getElementById('formDevolucion').addEventListener('submit', function(e) {
    if (!document.getElementById('motivo').value.trim()) {
        e.preventDefault();
        Swal.fire('Error', 'Debe indicar el motivo de la devolución', 'error');
        return;
    }
    if (document.querySelector('.descarta-input.is-invalid')) {
        e.preventDefault();
        Swal.fire('Error', 'La cantidad descarta no puede superar la cantidad reingresa', 'error');
        return;
    }
    let hasItems = false;
    document.querySelectorAll('.cantidad-input').forEach(i => {
        if (parseFloat(i.value) > 0) hasItems = true;
    });
    if (!hasItems) {
        e.preventDefault();
        Swal.fire('Error', 'Debe devolver al menos un producto', 'error');
        return;
    }

    var form = this;
    var total = calcularTotalDevolucion();
    var deuda = deudaCliente;

    var reembolsosData = [];

    if (deuda > 0 && total <= deuda) {
        reembolsosData.push({
            metodo: 'NOTA_CREDITO',
            monto: total.toFixed(2),
            caja_banco_id: null,
            observaciones: document.getElementById('obsNC').value || 'Compensación de deuda pendiente'
        });
    } else if (deuda > 0 && total > deuda) {
        reembolsosData.push({
            metodo: 'NOTA_CREDITO',
            monto: total.toFixed(2),
            caja_banco_id: null,
            observaciones: 'Compensación total de devolución'
        });
        var exceso = total - deuda;
        var metodo = document.getElementById('metodoExceso').value;
        var caja = document.getElementById('cajaBancoExceso').value;
        if (!caja) {
            e.preventDefault();
            Swal.fire('Error', 'Debe seleccionar una caja o banco para el reembolso del exceso', 'error');
            return;
        }
        reembolsosData.push({
            metodo: metodo,
            monto: exceso.toFixed(2),
            caja_banco_id: caja,
            observaciones: document.getElementById('obsExceso').value || 'Reembolso de exceso pagado'
        });
    } else {
        var metodo = document.getElementById('metodoSinDeuda').value;
        if (['EFECTIVO', 'TRANSFERENCIA'].includes(metodo)) {
            var caja = document.getElementById('cajaBancoSinDeuda').value;
            if (!caja) {
                e.preventDefault();
                Swal.fire('Error', 'Debe seleccionar una caja o banco para el reembolso', 'error');
                return;
            }
        }
        reembolsosData.push({
            metodo: metodo,
            monto: total.toFixed(2),
            caja_banco_id: document.getElementById('cajaBancoSinDeuda').value || null,
            observaciones: document.getElementById('obsSinDeuda').value || ''
        });
    }

    var existingInputs = form.querySelectorAll('input[name^="reembolsos"]');
    existingInputs.forEach(function(el) { el.remove(); });

    reembolsosData.forEach(function(r, idx) {
        var fields = ['metodo', 'monto', 'observaciones'];
        fields.forEach(function(f) {
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'reembolsos[' + idx + '][' + f + ']';
            input.value = r[f] || '';
            form.appendChild(input);
        });
        if (r.caja_banco_id) {
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'reembolsos[' + idx + '][caja_banco_id]';
            input.value = r.caja_banco_id;
            form.appendChild(input);
        }
    });
});
</script>
