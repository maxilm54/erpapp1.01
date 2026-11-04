<h1>Nueva Nota de Pedido</h1>
<form method="POST" action="<?= BASE_URL ?>/notaspedido/store">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::generate()) ?>">
    <label>Cliente</label>
    <select name="cliente_id" id="cliente_id" class="form-control" required>
        <option value="">Seleccione cliente</option>
        <?php foreach ($clientes as $c): ?>
            <option value="<?= $c['id'] ?>"><?= $c['razon_social'] ?></option>
        <?php endforeach ?>
    </select>
    <label class="mt-3">Presupuesto (opcional)</label>
    <select name="presupuesto_id" id="presupuesto_id" class="form-control mb-3">
        <option value="">Sin presupuesto</option>
    </select>
    <input type="text" id="buscador-producto" class="form-control" placeholder="Buscar producto...">
    <button type="button" class="btn btn-success" id="btn-agregar">+</button>

    <!-- Producto libre (no existe en la base) -->
    <div class="card border-info mt-3">
        <div class="card-header bg-info-subtle py-2">
            <strong>Producto libre</strong>
            <small class="text-muted">— para productos que todavía no están en la base</small>
        </div>
        <div class="card-body py-2">
            <div class="row g-2 align-items-end">
                <div class="col-md-5">
                    <label class="form-label">Nombre / descripción</label>
                    <input type="text" id="libre_nombre" class="form-control" placeholder="Ej: Producto especial a pedido" autocomplete="off" maxlength="255">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Cantidad</label>
                    <input type="number" id="libre_cantidad" class="form-control" step="0.01" min="0.01" value="1">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Precio</label>
                    <input type="number" id="libre_precio" class="form-control" step="0.01" min="0" value="0">
                </div>
                <div class="col-md-2">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="libre_crear">
                        <label class="form-check-label" for="libre_crear">
                            Agregar a Productos
                        </label>
                    </div>
                </div>
                <div class="col-md-1">
                    <button type="button" id="btn_agregar_libre" class="btn btn-outline-success w-100">+</button>
                </div>
            </div>
        </div>
    </div>

    <div id="resultados" class="list-group position-absolute w-100" style="z-index:1000; display:none;"></div>
    <div class="table-scroll mt-3">
        <table class="table table-striped mt-3">
            <thead>
                <tr>
                    <th>Producto</th>
                    <th width="120">Cantidad</th>
                    <th width="150">Precio</th>
                    <th width="50"></th>
                </tr>
            </thead>
            <tbody id="detalle"></tbody>
        </table>
    </div>
    <br><br>
    <div class="mb-3">
        <label>Observaciones</label>
        <textarea name="observaciones" class="form-control"></textarea>
    </div>

    <button class="btn btn-success">Guardar</button>
    <a href="<?= BASE_URL ?>/notaspedido" class="btn btn-secondary">Cancelar</a>

</form>
<script>
document.getElementById('presupuesto_id').addEventListener('change', function () {
    const presupuestoId = this.value;

    if (!presupuestoId) {
        limpiarDetalle();
        return;
    }

    fetch(`<?= BASE_URL ?>/presupuestos/showAjax/${presupuestoId}`)
        .then(r => r.json())
        .then(data => {
            if (data.error) {
                alert(data.error);
                return;
            }

            cargarLineasDesdePresupuesto(data.detalle);
        });
});

function limpiarDetalle() {
    document.getElementById('detalle').innerHTML = '';
}

// 🧾 Cargar líneas
function cargarLineasDesdePresupuesto(items) {
    limpiarDetalle();

    items.forEach(p => {
        agregarLineaPresupuesto(p);
    });
}

// ➕ Línea bloqueada desde PR
function agregarLineaPresupuesto(p) {
    // Línea libre (sin producto en la base)
    if (!p.producto_id) {
        agregarLineaLibre(p.nombre, p.cantidad, p.precio, false, true);
        return;
    }

    const tbody = document.getElementById('detalle');

    // Determinar step según unidad de medida (U, UN, UNIDAD = entero)
    const nombreMedida = (p.nombre_medida || '').toLowerCase();
    const esUnidadEntera = ['u', 'un', 'unidad', 'unidades'].includes(nombreMedida);
    const stepCantidad = esUnidadEntera ? '1' : '0.01';
    const minCantidad = esUnidadEntera ? '1' : '0.01';

    const tr = document.createElement('tr');
    tr.id = `prod-${p.producto_id}`;

    tr.innerHTML = `
        <td>
            ${p.nombre}
            <input type="hidden" name="items[${p.producto_id}][producto_id]" value="${p.producto_id}">
        </td>
        <td>
            <input type="number"
                   class="form-control"
                   name="items[${p.producto_id}][cantidad]"
                   value="${p.cantidad}"
                   step="${stepCantidad}"
                   min="${minCantidad}"
                   readonly>
        </td>
        <td>
            <input type="number"
                   class="form-control"
                   name="items[${p.producto_id}][precio]"
                   value="${p.precio}"
                   readonly>
        </td>
        <td></td>
    `;

    tbody.appendChild(tr);
}

// 🆓 Producto libre (no existe en la base)
let libreIndex = 0;

function escapeHtml(texto) {
    return String(texto)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function agregarLineaLibre(nombre, cantidad, precio, crear, readonly) {
    const tbody = document.getElementById('detalle');
    const key = 'libre_' + (++libreIndex);

    const tr = document.createElement('tr');
    tr.id = 'libre-' + libreIndex;
    tr.innerHTML = `
        <td>
            ${escapeHtml(nombre)}
            <span class="badge bg-info">Libre</span>
            ${crear ? '<span class="badge bg-success">se agregará a Productos</span>' : ''}
            <input type="hidden" name="items[${key}][descripcion]" value="${escapeHtml(nombre)}">
            <input type="hidden" name="items[${key}][crear_producto]" value="${crear ? 1 : 0}">
        </td>
        <td>
            <input type="number" class="form-control" step="0.01" min="0.01"
                   name="items[${key}][cantidad]" value="${cantidad}" ${readonly ? 'readonly' : 'required'}>
        </td>
        <td>
            <input type="number" class="form-control" step="0.01" min="0"
                   name="items[${key}][precio]" value="${precio}" ${readonly ? 'readonly' : 'required'}>
        </td>
        <td class="text-center">
            <button type="button" class="btn btn-danger btn-sm" onclick="this.closest('tr').remove()">×</button>
        </td>
    `;

    tbody.appendChild(tr);
}

document.getElementById('btn_agregar_libre').addEventListener('click', function () {
    const nombre   = document.getElementById('libre_nombre').value.trim();
    const cantidad = parseFloat(document.getElementById('libre_cantidad').value) || 0;
    const precio   = parseFloat(document.getElementById('libre_precio').value) || 0;
    const crear    = document.getElementById('libre_crear').checked;

    if (nombre === '') {
        alert('Escriba el nombre o descripción del producto libre');
        return;
    }
    if (cantidad <= 0) {
        alert('La cantidad debe ser mayor a cero');
        return;
    }

    agregarLineaLibre(nombre, cantidad, precio, crear, false);

    document.getElementById('libre_nombre').value = '';
    document.getElementById('libre_cantidad').value = 1;
    document.getElementById('libre_precio').value = 0;
    document.getElementById('libre_crear').checked = false;
});
</script>
<script>
    document.getElementById('cliente_id').addEventListener('change', function () {
    const clienteId = this.value;
    const select = document.getElementById('presupuesto_id');

    select.innerHTML = '<option value="">Cargando...</option>';

    if (!clienteId) {
        select.innerHTML = '<option value="">Sin presupuesto</option>';
        return;
    }

    fetch(`<?= BASE_URL ?>/presupuestos/porCliente/${clienteId}`)
        .then(r => r.json())
        .then(data => {
            select.innerHTML = '<option value="">Sin presupuesto</option>';

            data.forEach(p => {
                const opt = document.createElement('option');
                opt.value = p.id;
                opt.textContent = `PR #${p.id} - ${p.fecha}`;
                select.appendChild(opt);
            });
        });
});
let productosCache = [];
let productoSeleccionado = null;

// 🔍 Buscar productos
document.getElementById('buscador-producto').addEventListener('input', function () {
    const q = this.value.trim();

    if (q.length < 2) {
        cerrarResultados();
        return;
    }

    fetch(`<?= BASE_URL ?>/productos/search?q=${encodeURIComponent(q)}`)
        .then(r => r.json())
        .then(data => {
            productosCache = data;
            mostrarResultados(data);
        });
});

// 📋 Mostrar dropdown
function mostrarResultados(productos) {
    const cont = document.getElementById('resultados');
    cont.innerHTML = '';

    if (productos.length === 0) {
        cerrarResultados();
        return;
    }

    productos.forEach(p => {
        const item = document.createElement('button');
        item.type = 'button';
        item.className = 'list-group-item list-group-item-action';
        item.textContent = `${p.nombre} ($${p.precio_venta})`;

        item.onclick = () => seleccionarProducto(p);
        cont.appendChild(item);
    });

    cont.style.display = 'block';
}

// ✅ Seleccionar producto
function seleccionarProducto(p) {
    productoSeleccionado = p;
    document.getElementById('buscador-producto').value = p.nombre;
    cerrarResultados();
}

// ❌ Cerrar dropdown
function cerrarResultados() {
    document.getElementById('resultados').style.display = 'none';
}

// ➕ Agregar línea
document.getElementById('btn-agregar').addEventListener('click', function () {
    if (!productoSeleccionado) {
        alert('Seleccione un producto');
        return;
    }

    agregarLinea(productoSeleccionado);
    productoSeleccionado = null;
    document.getElementById('buscador-producto').value = '';
});

// 🧾 Crear fila
function agregarLinea(p) {
    const tbody = document.getElementById('detalle');

    // Evitar duplicados
    if (document.getElementById(`prod-${p.id}`)) {
        alert('El producto ya está agregado');
        return;
    }

    // Determinar step según unidad de medida (U, UN, UNIDAD = entero)
    const nombreMedida = (p.nombre_medida || '').toLowerCase();
    const esUnidadEntera = ['u', 'un', 'unidad', 'unidades'].includes(nombreMedida);
    const stepCantidad = esUnidadEntera ? '1' : '0.01';
    const minCantidad = esUnidadEntera ? '1' : '0.01';

    const tr = document.createElement('tr');
    tr.id = `prod-${p.id}`;

    tr.innerHTML = `
        <td>
            ${p.nombre}
            <input type="hidden" name="items[${p.id}][producto_id]" value="${p.id}">
        </td>
        <td>
            <input type="number" step="${stepCantidad}" min="${minCantidad}"
                   name="items[${p.id}][cantidad]"
                   class="form-control"
                   required>
        </td>
        <td>
            <input type="number" step="0.01" min="0"
                   name="items[${p.id}][precio]"
                   class="form-control"
                   value="${p.precio_venta}"
                   required>
        </td>
        <td class="text-center">
            <button type="button" class="btn btn-danger btn-sm" onclick="eliminarLinea(${p.id})">×</button>
        </td>
    `;

    tbody.appendChild(tr);
}

// 🗑 Eliminar línea
function eliminarLinea(id) {
    document.getElementById(`prod-${id}`).remove();
}

// ❗ Validación antes de enviar
document.querySelector('form').addEventListener('submit', function (e) {
    const filas = document.querySelectorAll('#detalle tr');
    if (filas.length === 0) {
        e.preventDefault();
        alert('Debe agregar al menos un producto');
    }
});
</script>