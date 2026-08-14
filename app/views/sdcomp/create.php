<h4><i class="bi bi-plus-lg"></i> Nuevo Comprobante</h4>
<hr>

<?php if (!empty($_SESSION['error'])): ?>
<div class="alert alert-danger"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
<?php endif; ?>

<form method="post" id="form-mov-sdcomp" action="<?= BASE_URL ?>/sdcomp/store">
    <!-- Tipo + Datos del cliente/proveedor -->
    <div class="row mb-3">
        <div class="col-md-3">
            <label class="form-label">Tipo de Movimiento</label>
            <select name="tipo" id="tipo-mov" class="form-select" required>
                <option value="VENTA">Salida</option>
                <option value="COMPRA">Entrada</option>
            </select>
        </div>

        <!-- VENTAS: campos cliente -->
        <div id="campos-ventas" class="col-md-9 row">
            <div class="col-md-4">
                <label class="form-label">Cliente (opcional)</label>
                <select name="cliente_id" id="cliente-select" class="form-select">
                    <option value="">Sin cliente registrado</option>
                    <?php foreach ($clientes as $c): ?>
                    <option value="<?= $c['id'] ?>" data-cuit="<?= htmlspecialchars($c['cuit'] ?? '') ?>">
                        <?= htmlspecialchars($c['razon_social']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Razon Social / Nombre</label>
                <input type="text" name="razon_social_ventas" id="razon-social-ventas" class="form-control" placeholder="Nombre del cliente">
            </div>
            <div class="col-md-4">
                <label class="form-label">CUIT</label>
                <input type="text" name="cuit_ventas" id="cuit-ventas" class="form-control" placeholder="XX-XXXXXXXX-X">
            </div>
        </div>

        <!-- COMPRAS: campos proveedor -->
        <div id="campos-compras" class="col-md-9 row" style="display:none;">
            <div class="col-md-4">
                <label class="form-label">Proveedor (opcional)</label>
                <select name="proveedor_id" id="proveedor-select" class="form-select">
                    <option value="">Sin proveedor registrado</option>
                    <?php foreach ($proveedores as $p): ?>
                    <option value="<?= $p['id'] ?>" data-cuit="<?= htmlspecialchars($p['cuit'] ?? '') ?>">
                        <?= htmlspecialchars($p['razon_social']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Razon Social / Nombre</label>
                <input type="text" name="razon_social_compras" id="razon-social-compras" class="form-control" placeholder="Nombre del proveedor">
            </div>
            <div class="col-md-4">
                <label class="form-label">CUIT</label>
                <input type="text" name="cuit_compras" id="cuit-compras" class="form-control" placeholder="XX-XXXXXXXX-X">
            </div>
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-md-6">
            <label class="form-label">Descripcion</label>
            <input type="text" name="descripcion" class="form-control" placeholder="Breve descripcion del movimiento">
        </div>
        <div class="col-md-6">
            <label class="form-label">Observaciones</label>
            <textarea name="observaciones" class="form-control" rows="1"></textarea>
        </div>
    </div>

    <!-- Productos -->
    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <strong>Productos / Materias Primas</strong>
            <div>
                <button type="button" class="btn btn-sm btn-success me-1" onclick="agregarItemManualSdcomp()">
                    <i class="bi bi-plus-circle"></i> Concepto
                </button>
                <button type="button" class="btn btn-sm btn-primary" onclick="agregarItem()">
                    <i class="bi bi-plus"></i> Agregar
                </button>
            </div>
        </div>
        <div class="card-body p-0">
            <table class="table table-sm mb-0" id="tabla-productos">
                <thead class="table-light">
                    <tr>
                        <th style="width:35%">Buscar producto / materia prima</th>
                        <th style="width:8%">Tipo</th>
                        <th style="width:12%">Stock Disp.</th>
                        <th style="width:12%">Cantidad</th>
                        <th style="width:13%">P. Unitario</th>
                        <th style="width:12%">Subtotal</th>
                        <th style="width:3%"></th>
                    </tr>
                </thead>
                <tbody id="items-body">
                </tbody>
                <tfoot>
                    <tr class="table-dark">
                        <td colspan="5" class="text-end"><strong>TOTAL:</strong></td>
                        <td class="text-end"><strong>$ <span id="total-general">0.00</span></strong></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <button type="submit" class="btn btn-success" id="btn-guardar">
        <i class="bi bi-check-lg"></i> Guardar Comprobante
    </button>
    <a href="<?= BASE_URL ?>/sdcomp" class="btn btn-secondary">Cancelar</a>
</form>

<script>
let contadorItems = 0;

function agregarItem() {
    const tbody = document.getElementById('items-body');
    const idx = contadorItems;
    const tr = document.createElement('tr');
    tr.id = 'item-' + idx;

    tr.innerHTML = `
        <td style="position:relative;">
            <input type="hidden" name="items[${idx}][tipo_item]" class="input-tipo-item" value="">
            <input type="hidden" name="items[${idx}][producto_id]" class="input-producto-id" value="">
            <input type="hidden" name="items[${idx}][materia_prima_id]" class="input-mp-id" value="">
            <input type="text" class="form-control form-control-sm input-buscar" placeholder="Escribi para buscar..." autocomplete="off" data-idx="${idx}">
            <div class="resultados-busqueda" id="resultados-${idx}" style="display:none; position:absolute; z-index:1000; background:#fff; border:1px solid #ccc; width:100%; max-height:200px; overflow-y:auto;"></div>
        </td>
        <td class="text-center"><span class="badge bg-secondary" id="tipo-badge-${idx}">-</span></td>
        <td class="text-center stock-disp" id="stock-${idx}">-</td>
        <td><input type="number" name="items[${idx}][cantidad]" class="form-control form-control-sm input-cantidad" step="0.01" min="0.01" oninput="calcularSubtotal(${idx})" required></td>
        <td><input type="number" name="items[${idx}][precio_unitario]" class="form-control form-control-sm input-precio" step="0.01" min="0" oninput="calcularSubtotal(${idx})" value="0.00"></td>
        <td class="text-end subtotal" id="subtotal-${idx}">$ 0.00</td>
        <td><button type="button" class="btn btn-outline-danger btn-sm" onclick="eliminarItem(${idx})"><i class="bi bi-x"></i></button></td>
    `;
    tbody.appendChild(tr);
    contadorItems++;

    initBuscadorItem(tr.querySelector('.input-buscar'), idx);
}

function initBuscadorItem(input, idx) {
    const resultados = document.getElementById('resultados-' + idx);
    let timeout = null;

    input.addEventListener('input', function() {
        clearTimeout(timeout);
        const q = this.value.trim();
        if (q.length < 2) {
            resultados.style.display = 'none';
            return;
        }
        timeout = setTimeout(() => {
            fetch('<?= BASE_URL ?>/sdcomp/search?q=' + encodeURIComponent(q))
                .then(r => r.json())
                .then(data => {
                    resultados.innerHTML = '';
                    if (!data.length) {
                        resultados.style.display = 'none';
                        return;
                    }
                    data.forEach(item => {
                        const btn = document.createElement('button');
                        btn.type = 'button';
                        btn.className = 'list-group-item list-group-item-action py-1 px-2';
                        const label = item.tipo_item === 'MATERIAPRIMA' ? '<span class="badge bg-warning text-dark me-1">MP</span>' : '<span class="badge bg-info me-1">PROD</span>';
                        btn.innerHTML = `${label} <small>${item.sku} - ${item.nombre} (Stock: ${parseFloat(item.stock_actual).toFixed(2)})</small>`;
                        btn.onclick = () => seleccionarItem(idx, item);
                        resultados.appendChild(btn);
                    });
                    resultados.style.display = 'block';
                });
        }, 300);
    });

    input.addEventListener('blur', () => {
        setTimeout(() => { resultados.style.display = 'none'; }, 200);
    });
}

function seleccionarItem(idx, item) {
    const row = document.getElementById('item-' + idx);
    const inputBuscar = row.querySelector('.input-buscar');

    inputBuscar.value = item.sku + ' - ' + item.nombre;
    document.getElementById('resultados-' + idx).style.display = 'none';

    row.querySelector('.input-tipo-item').value = item.tipo_item;
    row.querySelector('.input-precio').value = parseFloat(item.precio).toFixed(2);
    document.getElementById('stock-' + idx).textContent = parseFloat(item.stock_actual).toFixed(2);

    const tipoBadge = document.getElementById('tipo-badge-' + idx);
    if (item.tipo_item === 'MATERIAPRIMA') {
        tipoBadge.textContent = 'MP';
        tipoBadge.className = 'badge bg-warning text-dark';
        row.querySelector('.input-producto-id').value = '';
        row.querySelector('.input-mp-id').value = item.id;
    } else {
        tipoBadge.textContent = 'PROD';
        tipoBadge.className = 'badge bg-info';
        row.querySelector('.input-producto-id').value = item.id;
        row.querySelector('.input-mp-id').value = '';
    }

    calcularSubtotal(idx);
}

function calcularSubtotal(idx) {
    const row = document.getElementById('item-' + idx);
    if (!row) return;
    const cant = parseFloat(row.querySelector('.input-cantidad').value) || 0;
    const prec = parseFloat(row.querySelector('.input-precio').value) || 0;
    const sub = cant * prec;
    document.getElementById('subtotal-' + idx).textContent = '$ ' + sub.toFixed(2).replace('.', ',');
    calcularTotal();
}

function calcularTotal() {
    let total = 0;
    document.querySelectorAll('.subtotal').forEach(el => {
        total += parseFloat(el.textContent.replace('$ ', '').replace(',', '.')) || 0;
    });
    document.getElementById('total-general').textContent = total.toFixed(2).replace('.', ',');
}

function eliminarItem(idx) {
    const row = document.getElementById('item-' + idx);
    if (row) row.remove();
    calcularTotal();
}

document.getElementById('tipo-mov').addEventListener('change', function() {
    const esVenta = this.value === 'VENTA';
    document.getElementById('campos-ventas').style.display = esVenta ? '' : 'none';
    document.getElementById('campos-compras').style.display = esVenta ? 'none' : '';
});

document.getElementById('cliente-select').addEventListener('change', function() {
    const opt = this.options[this.selectedIndex];
    document.getElementById('cuit-ventas').value = opt.dataset.cuit || '';
    document.getElementById('razon-social-ventas').value = opt.text.trim();
});

document.getElementById('proveedor-select').addEventListener('change', function() {
    const opt = this.options[this.selectedIndex];
    document.getElementById('cuit-compras').value = opt.dataset.cuit || '';
    document.getElementById('razon-social-compras').value = opt.text.trim();
});

document.getElementById('form-mov-sdcomp').addEventListener('submit', function(e) {
    const items = document.querySelectorAll('#items-body tr');
    const manuales = document.querySelectorAll('.item-manual');
    if (items.length === 0 && manuales.length === 0) {
        e.preventDefault();
        alert('Debe agregar al menos un producto o concepto');
        return;
    }
    let valido = true;
    items.forEach(row => {
        const tipo = row.querySelector('.input-tipo-item').value;
        if (!tipo) {
            valido = false;
        }
    });
    manuales.forEach(row => {
        const descripcion = row.querySelector('input[name$="[descripcion]"]').value.trim();
        if (!descripcion) {
            valido = false;
        }
    });
    if (!valido) {
        e.preventDefault();
        alert('Debe seleccionar un producto/materia prima o completar la descripción en cada fila');
        return;
    }
});

agregarItem();

// Función para agregar item manual (concepto sin producto)
function agregarItemManualSdcomp() {
    const tbody = document.getElementById('items-body');
    const idx = 'manual_' + Date.now();
    const tr = document.createElement('tr');
    tr.id = 'item-' + idx;
    tr.className = 'item-manual';

    tr.innerHTML = `
        <td style="position:relative;">
            <input type="hidden" name="items[${idx}][tipo_item]" value="MANUAL">
            <input type="hidden" name="items[${idx}][producto_id]" value="">
            <input type="hidden" name="items[${idx}][materia_prima_id]" value="">
            <input type="text" name="items[${idx}][descripcion]" class="form-control form-control-sm descripcion-manual" 
                   placeholder="Descripción del concepto" required>
        </td>
        <td class="text-center"><span class="badge bg-secondary">MANUAL</span></td>
        <td class="text-center">-</td>
        <td><input type="number" name="items[${idx}][cantidad]" class="form-control form-control-sm input-cantidad" step="0.01" min="0.01" value="1" required></td>
        <td><input type="number" name="items[${idx}][precio_unitario]" class="form-control form-control-sm input-precio" step="0.01" min="0" oninput="calcularSubtotalManual('${idx}')" value="0.00"></td>
        <td class="text-end subtotal" id="subtotal-${idx}">$ 0.00</td>
        <td><button type="button" class="btn btn-sm btn-outline-danger" onclick="eliminarItem('${idx}')"><i class="bi bi-x-lg"></i></button></td>
    `;
    tbody.appendChild(tr);
    calcularTotal();
}

function calcularSubtotalManual(idx) {
    const tr = document.getElementById('item-' + idx);
    if (!tr) return;
    const cant = parseFloat(tr.querySelector('.input-cantidad').value) || 0;
    const prec = parseFloat(tr.querySelector('.input-precio').value) || 0;
    const sub = cant * prec;
    document.getElementById('subtotal-' + idx).textContent = '$ ' + sub.toFixed(2).replace('.', ',');
    calcularTotal();
}

function actualizarDescripcion(input, idx) {
    // Actualizar el valor del input oculto de búsqueda con la descripción
    const buscarInput = tr.querySelector('.input-buscar');
    if (buscarInput) {
        buscarInput.value = input.value;
    }
}

function calcularSubtotalManual(idx) {
    const tr = document.getElementById('item-' + idx);
    if (!tr) return;
    const cant = parseFloat(tr.querySelector('.input-cantidad').value) || 0;
    const prec = parseFloat(tr.querySelector('.input-precio').value) || 0;
    const sub = cant * prec;
    document.getElementById('subtotal-' + idx).textContent = '$ ' + sub.toFixed(2).replace('.', ',');
    calcularTotal();
}
</script>
