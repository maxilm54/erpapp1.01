<h1>Nueva Nota de Pedido</h1>
<form method="POST" action="<?= BASE_URL ?>/notaspedido/store">
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

<div id="resultados" class="list-group position-absolute w-100" style="z-index:1000; display:none;"></div>

<table class="table mt-3">
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
    const tbody = document.getElementById('detalle');

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

    const tr = document.createElement('tr');
    tr.id = `prod-${p.id}`;

    tr.innerHTML = `
        <td>
            ${p.nombre}
            <input type="hidden" name="items[${p.id}][producto_id]" value="${p.id}">
        </td>
        <td>
            <input type="number" step="0.01" min="0.01"
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