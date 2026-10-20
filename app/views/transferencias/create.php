<h3><?= $title ?></h3>

<form method="POST" action="<?= BASE_URL ?>/transferencias/store" id="formTransferencia" class="card p-4">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::generate()) ?>">

    <div class="row mb-3">
        <div class="col-md-5">
            <label class="form-label">Depósito Origen *</label>
            <select name="deposito_origen_id" id="depositoOrigen" class="form-select" required>
                <option value="">Seleccionar...</option>
                <?php foreach ($depositos as $d): ?>
                <option value="<?= $d['id'] ?>" <?= ($origen_preseleccionado === (int)$d['id']) ? 'selected' : '' ?>><?= htmlspecialchars($d['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2 d-flex align-items-end justify-content-center">
            <i class="bi bi-arrow-right-circle" style="font-size: 1.8rem; color: #6c757d;"></i>
        </div>
        <div class="col-md-5">
            <label class="form-label">Depósito Destino *</label>
            <select name="deposito_destino_id" id="depositoDestino" class="form-select" required>
                <option value="">Seleccionar...</option>
                <?php foreach ($depositos as $d): ?>
                <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="mb-3">
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-sm btn-primary" onclick="abrirBuscador('producto')">
                <i class="bi bi-box"></i> Agregar Producto
            </button>
            <button type="button" class="btn btn-sm btn-warning" onclick="abrirBuscador('materia_prima')">
                <i class="bi bi-box-seam"></i> Agregar Materia Prima
            </button>
        </div>
    </div>

    <div id="sinItems" class="text-center text-muted py-4">
        <i class="bi bi-inbox" style="font-size: 2rem;"></i>
        <p class="mt-2">No hay ítems. Seleccione un producto o materia prima para transferir.</p>
    </div>

    <div class="table-responsive" id="contenedorTabla" style="display:none;">
        <table class="table table-bordered" id="tablaItems">
            <thead class="table-dark">
                <tr>
                    <th>Ítem</th>
                    <th class="text-end">Disponible (Origen)</th>
                    <th>Lote</th>
                    <th width="120">Cantidad</th>
                    <th width="50"></th>
                </tr>
            </thead>
            <tbody id="itemsBody"></tbody>
        </table>
    </div>

    <div class="mb-3">
        <label class="form-label">Observaciones</label>
        <textarea name="observaciones" class="form-control" rows="2"></textarea>
    </div>

    <div id="itemsHidden"></div>

    <div class="alert alert-info">
        <i class="bi bi-truck"></i> Al confirmar, el stock saldrá del depósito origen y quedará <strong>En Tránsito</strong>.
        Se acreditará en el depósito destino cuando confirme la recepción.
    </div>

    <button class="btn btn-success w-100">Confirmar Salida de Mercadería</button>
    <a href="<?= BASE_URL ?>/transferencias" class="btn btn-secondary w-100 mt-2">Cancelar</a>
</form>

<!-- Modal buscador -->
<div class="modal fade" id="modalBuscador" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-search"></i> Buscar <span id="buscadorTitulo"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="text" id="buscadorInput" class="form-control form-control-lg"
                       placeholder="Buscar por nombre, SKU o código de barra..." autofocus>
                <div id="resultadosBusqueda" class="list-group mt-3" style="max-height: 400px; overflow-y: auto;"></div>
            </div>
        </div>
    </div>
</div>

<div id="itemsHidden"></div>

<script>
let itemsSeleccionados = {};
let buscadorTipo = 'producto';
let busquedaTimeout;

function abrirBuscador(tipo) {
    buscadorTipo = tipo;
    document.getElementById('buscadorTitulo').textContent = tipo === 'producto' ? 'Producto' : 'Materia Prima';
    document.getElementById('buscadorInput').value = '';
    document.getElementById('resultadosBusqueda').innerHTML = '';
    new bootstrap.Modal(document.getElementById('modalBuscador')).show();
    setTimeout(() => document.getElementById('buscadorInput').focus(), 300);
}

document.getElementById('buscadorInput').addEventListener('input', function() {
    clearTimeout(busquedaTimeout);
    const q = this.value.trim();
    if (q.length < 2) {
        document.getElementById('resultadosBusqueda').innerHTML = '';
        return;
    }
    busquedaTimeout = setTimeout(() => {
        const endpoint = buscadorTipo === 'producto'
            ? '<?= BASE_URL ?>/productos/search?q='
            : '<?= BASE_URL ?>/materiasprimas/search?q=';
        fetch(endpoint + encodeURIComponent(q))
            .then(r => r.json())
            .then(data => {
                const cont = document.getElementById('resultadosBusqueda');
                cont.innerHTML = '';
                if (!data.length) {
                    cont.innerHTML = '<div class="text-center text-muted py-3">Sin resultados</div>';
                    return;
                }
                data.forEach(item => {
                    const div = document.createElement('a');
                    div.href = '#';
                    div.className = 'list-group-item list-group-item-action';
                    div.innerHTML = `<strong>${escapeHtml(item.nombre)}</strong>` +
                        (item.sku ? ` <small class="text-muted">(${escapeHtml(item.sku)})</small>` : '');
                    div.addEventListener('click', (e) => {
                        e.preventDefault();
                        agregarItem(item);
                        bootstrap.Modal.getInstance(document.getElementById('modalBuscador')).hide();
                    });
                    cont.appendChild(div);
                });
            });
    }, 300);
});

function agregarItem(item) {
    const key = buscadorTipo + '_' + item.id;
    if (itemsSeleccionados[key]) return;
    itemsSeleccionados[key] = {
        tipo: buscadorTipo,
        id: item.id,
        nombre: item.nombre,
        sku: item.sku || '',
        cantidad: 0,
        lote_id: '',
        stock_actual: 0
    };
    cargarStock(key);
    renderTabla();
}

function cargarStock(key) {
    const it = itemsSeleccionados[key];
    const depositoOrigen = document.getElementById('depositoOrigen').value;
    if (!depositoOrigen) {
        it.stock_actual = 0;
        actualizarFila(key);
        return;
    }
    fetch('<?= BASE_URL ?>/transferencias/stock-deposito?deposito_id=' + depositoOrigen +
          '&tipo=' + it.tipo + '&id=' + it.id)
        .then(r => r.json())
        .then(data => {
            it.stock_actual = parseFloat(data.stock) || 0;
            actualizarFila(key);
        });
}

document.getElementById('depositoOrigen').addEventListener('change', function() {
    Object.keys(itemsSeleccionados).forEach(k => cargarStock(k));
});

window.actualizarCantidadTransferencia = function(key, val) {
    const it = itemsSeleccionados[key];
    it.cantidad = parseFloat(val) || 0;
    updateHidden();
};

window.actualizarLoteTransferencia = function(key, val) {
    const it = itemsSeleccionados[key];
    it.lote_id = val;
    // La cantidad depende del lote; por simplicidad se mantiene el stock general
    updateHidden();
};

window.quitarItemTransferencia = function(key) {
    delete itemsSeleccionados[key];
    renderTabla();
};

function actualizarFila(key) {
    const it = itemsSeleccionados[key];
    const cell = document.getElementById('disp-' + key);
    if (cell) cell.textContent = numberFormat(it.stock_actual);
    const inputCant = document.getElementById('cant-' + key);
    if (inputCant) inputCant.max = it.stock_actual;
    updateHidden();
}

function renderTabla() {
    const keys = Object.keys(itemsSeleccionados);
    const tbody = document.getElementById('itemsBody');

    if (!keys.length) {
        document.getElementById('sinItems').style.display = 'block';
        document.getElementById('contenedorTabla').style.display = 'none';
        document.getElementById('itemsHidden').innerHTML = '';
        return;
    }

    document.getElementById('sinItems').style.display = 'none';
    document.getElementById('contenedorTabla').style.display = 'block';

    let html = '';
    keys.forEach(key => {
        const it = itemsSeleccionados[key];
        const badge = it.tipo === 'producto' ? 'bg-info' : 'bg-warning';
        const label = it.tipo === 'producto' ? 'PRODUCTO' : 'MATERIA PRIMA';
        html += `<tr>
            <td><span class="badge ${badge}">${label}</span> <strong>${escapeHtml(it.nombre)}</strong>
                ${it.sku ? `<small class="text-muted">(${escapeHtml(it.sku)})</small>` : ''}</td>
            <td class="text-end fw-bold" id="disp-${key}">${numberFormat(it.stock_actual)}</td>
            <td>
                <select class="form-select form-select-sm" id="lote-${key}"
                    onchange="actualizarLoteTransferencia('${key}', this.value)">
                    <option value="">Sin lote</option>
                </select>
            </td>
            <td><input type="number" step="0.01" min="0" max="${it.stock_actual}" id="cant-${key}"
                class="form-control form-control-sm"
                onchange="actualizarCantidadTransferencia('${key}', this.value)"
                oninput="actualizarCantidadTransferencia('${key}', this.value)"></td>
            <td><button type="button" class="btn btn-sm btn-outline-danger"
                onclick="quitarItemTransferencia('${key}')"><i class="bi bi-x-lg"></i></button></td>
        </tr>`;
    });
    tbody.innerHTML = html;

    // Cargar lotes para cada item
    keys.forEach(key => {
        cargarLotes(key);
    });

    updateHidden();
}

function cargarLotes(key) {
    const it = itemsSeleccionados[key];
    const depositoOrigen = document.getElementById('depositoOrigen').value;
    if (!depositoOrigen) return;
    fetch('<?= BASE_URL ?>/transferencias/stock-deposito?deposito_id=' + depositoOrigen +
          '&tipo=' + it.tipo + '&id=' + it.id)
        .then(r => r.json())
        .then(data => {
            it.stock_actual = parseFloat(data.stock) || 0;
            const cell = document.getElementById('disp-' + key);
            if (cell) cell.textContent = numberFormat(it.stock_actual);
            const inputCant = document.getElementById('cant-' + key);
            if (inputCant) inputCant.max = it.stock_actual;
            const select = document.getElementById('lote-' + key);
            if (!select) return;
            select.innerHTML = '<option value="">Sin lote</option>';
            (data.lotes || []).forEach(l => {
                const opt = document.createElement('option');
                opt.value = l.id;
                opt.textContent = l.codigo_lote + (l.fecha_vencimiento ? ' - Venc. ' + l.fecha_vencimiento : '') +
                    ' (' + numberFormat(l.stock) + ')';
                select.appendChild(opt);
            });
        });
}

function updateHidden() {
    let html = '';
    Object.keys(itemsSeleccionados).forEach(key => {
        const it = itemsSeleccionados[key];
        if (!it.cantidad || it.cantidad <= 0) return;
        const index = Object.keys(itemsSeleccionados).indexOf(key);
        html += `<input type="hidden" name="tipo[${index}]" value="${it.tipo}">`;
        html += `<input type="hidden" name="producto_id[${index}]" value="${it.tipo === 'producto' ? it.id : ''}">`;
        html += `<input type="hidden" name="materia_prima_id[${index}]" value="${it.tipo === 'materia_prima' ? it.id : ''}">`;
        html += `<input type="hidden" name="cantidad[${index}]" value="${it.cantidad}">`;
        html += `<input type="hidden" name="lote_id[${index}]" value="${it.lote_id || ''}">`;
    });
    document.getElementById('itemsHidden').innerHTML = html;
}

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

function numberFormat(val) {
    return Number(val).toLocaleString('es-AR', { maximumFractionDigits: 3 });
}

document.getElementById('formTransferencia').addEventListener('submit', function(e) {
    const origen = document.getElementById('depositoOrigen').value;
    const destino = document.getElementById('depositoDestino').value;
    if (!origen || !destino) {
        e.preventDefault();
        alert('Debe seleccionar depósito de origen y destino.');
        return false;
    }
    if (origen === destino) {
        e.preventDefault();
        alert('Los depósitos de origen y destino deben ser distintos.');
        return false;
    }
    let tieneCantidad = false;
    Object.keys(itemsSeleccionados).forEach(k => {
        if (itemsSeleccionados[k].cantidad > 0) tieneCantidad = true;
    });
    if (!tieneCantidad) {
        e.preventDefault();
        alert('Debe ingresar al menos un ítem con cantidad.');
        return false;
    }
    updateHidden();
});
</script>