<h3 class="mb-3"><?= $title ?></h3>

<form method="POST" class="card p-4">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::generate()) ?>">
    <div class="mb-3">
        <label class="form-label">Proveedor</label>
        <select name="proveedor_id" class="form-select" required>
            <option value="">Seleccione proveedor</option>
            <?php foreach ($proveedores as $p): ?>
                <option value="<?= $p['id'] ?>"
                    <?= isset($orden) && $orden['proveedor_id']==$p['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($p['razon_social']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="mb-3">
        <label class="form-label">Observaciones</label>
        <textarea name="observaciones" class="form-control" rows="2" placeholder="Información adicional..."><?= isset($orden['observaciones']) ? htmlspecialchars($orden['observaciones']) : '' ?></textarea>
    </div>

    <h5>Detalle</h5>
    
    <div class="row mb-3">
        <div class="col-md-10">
            <input type="text" id="buscador-item" class="form-control" placeholder="Buscar producto o materia prima...">
        </div>
        <div class="col-md-2">
            <button type="button" class="btn btn-success w-100" id="btn-agregar">+ Agregar</button>
        </div>
    </div>
    <div id="resultados" class="list-group position-absolute w-50" style="z-index:1000; display:none; margin-left: 15px;"></div>

    <div class="table-scroll mt-3">
        <table class="table">
            <thead>
                <tr>
                    <th>Tipo</th>
                    <th>Nombre</th>
                    <th>Cantidad</th>
                    <th>Precio Unitario</th>
                    <th>Moneda</th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="detalle">
            </tbody>
        </table>
    </div>
    
    <div id="items-container">
    </div>

    <div class="d-flex gap-2 justify-content-end">
        <a href="<?= BASE_URL ?>/ordenescompra" class="btn btn-secondary">Cancelar</a>
        <button class="btn btn-primary">Guardar Orden de Compra</button>
    </div>
</form>

<script>
let itemsCache = [];
let itemSeleccionado = null;
let itemCount = <?= isset($detalle) && is_array($detalle) ? count($detalle) : 0 ?>;

// 📥 Cargar items existentes al iniciar (para modo edición)
<?php if (isset($detalle) && is_array($detalle)): ?>
document.addEventListener('DOMContentLoaded', function() {
    const detalleData = <?= json_encode($detalle) ?>;
    console.log('detalleData:', detalleData);
    detalleData.forEach(function(item) {
        agregarLineaExistente(item);
    });
});

function agregarLineaExistente(item) {
    const tbody = document.getElementById('detalle');
    const nombreMedida = (item.nombre_medida || '').toLowerCase();
    const esUnidadEntera = ['u', 'un', 'unidad', 'unidades'].includes(nombreMedida);
    const stepCantidad = esUnidadEntera ? '1' : '0.01';
    const minCantidad = esUnidadEntera ? '1' : '0.01';
    const tipo = item.tipo || 'materia_prima';
    const tipoLabel = tipo === 'producto' ? 'Producto' : 'Materia Prima';
    const uniqueId = tipo + '-' + (item.materia_prima_id || item.producto_id);

    const tr = document.createElement('tr');
    tr.id = 'item-' + uniqueId;
    
    tr.innerHTML = `
        <td><span class="badge bg-${tipo === 'producto' ? 'info' : 'warning'}">${tipoLabel}</span></td>
        <td>${item.nombre}</td>
        <td>
            <input type="number" step="${stepCantidad}" min="${minCantidad}" class="form-control cantidad-input"
                data-index="${itemCount}" value="${item.pedida || ''}">
        </td>
        <td>
            <input type="number" step="0.01" min="0" class="form-control"
                name="items[${itemCount}][precio_unitario]" value="${item.precio_unitario || ''}" required>
        </td>
        <td>
            <select name="items[${itemCount}][moneda]" class="form-select">
                <option value="4" ${(item.moneda || '$') === '$' ? 'selected' : ''}>$</option>
                <option value="5" ${(item.moneda || '$') === 'USD' ? 'selected' : ''}>USD</option>
                <option value="6" ${(item.moneda || '$') === '€' ? 'selected' : ''}>€</option>
            </select>
        </td>
        <td>
            <input type="hidden" name="items[${itemCount}][tipo]" value="${tipo}">
            <input type="hidden" name="items[${itemCount}][materia_prima_id]" value="${item.materia_prima_id ?? ''}">
            <input type="hidden" name="items[${itemCount}][producto_id]" value="${item.producto_id ?? ''}">
            <button type="button" class="btn btn-danger btn-sm" onclick="eliminarLinea('${uniqueId}')">×</button>
        </td>
    `;

    tbody.appendChild(tr);
    
    // El precio y moneda son inputs visibles con name correcto
    // Solo necesitamos crear el hidden para cantidad
    const cantidadInput = tr.querySelector('.cantidad-input');
    const cantidadHidden = document.createElement('input');
    cantidadHidden.type = 'hidden';
    cantidadHidden.name = `items[${itemCount}][cantidad]`;
    cantidadHidden.value = cantidadInput.value;
    tr.appendChild(cantidadHidden); // Agregar al tr, no al td
    
    cantidadInput.addEventListener('input', function() {
        cantidadHidden.value = this.value;
    });
    
    itemCount++;
}
<?php endif; ?>

// 🔍 Buscar items (productos y materias primas)
document.getElementById('buscador-item').addEventListener('input', function () {
    const q = this.value.trim();

    if (q.length < 2) {
        cerrarResultados();
        return;
    }

    fetch(`<?= BASE_URL ?>/ordenescompra/search?q=${encodeURIComponent(q)}`)
        .then(r => r.json())
        .then(data => {
            itemsCache = data;
            mostrarResultados(data);
        });
});

// 📋 Mostrar dropdown
function mostrarResultados(items) {
    const cont = document.getElementById('resultados');
    cont.innerHTML = '';

    if (items.length === 0) {
        cerrarResultados();
        return;
    }

    items.forEach(item => {
        const itemDiv = document.createElement('button');
        itemDiv.type = 'button';
        itemDiv.className = 'list-group-item list-group-item-action d-flex justify-content-between align-items-center';
        
        const tipoBadge = item.tipo === 'producto' 
            ? '<span class="badge bg-info">Producto</span>' 
            : '<span class="badge bg-warning">Materia Prima</span>';
        
        itemDiv.innerHTML = `
            <span>${item.nombre}</span>
            ${tipoBadge}
        `;

        itemDiv.onclick = () => seleccionarItem(item);
        cont.appendChild(itemDiv);
    });

    cont.style.display = 'block';
}

// ✅ Seleccionar item
function seleccionarItem(item) {
    itemSeleccionado = item;
    document.getElementById('buscador-item').value = item.nombre;
    cerrarResultados();
}

// ❌ Cerrar dropdown
function cerrarResultados() {
    document.getElementById('resultados').style.display = 'none';
}

// 🧾 Crear fila
function agregarLinea(item) {
    const tbody = document.getElementById('detalle');
    const tipo = item.tipo;
    const tipoLabel = tipo === 'producto' ? 'Producto' : 'Materia Prima';
    const uniqueId = tipo + '-' + item.id;

    if (document.getElementById('item-' + uniqueId)) {
        alert('El item ya está agregado');
        return;
    }

    const nombreMedida = (item.nombre_medida || '').toLowerCase();
    const esUnidadEntera = ['u', 'un', 'unidad', 'unidades'].includes(nombreMedida);
    const stepCantidad = esUnidadEntera ? '1' : '0.01';
    const minCantidad = esUnidadEntera ? '1' : '0.01';

    const tr = document.createElement('tr');
    tr.id = 'item-' + uniqueId;

    tr.innerHTML = `
        <td><span class="badge bg-${tipo === 'producto' ? 'info' : 'warning'}">${tipoLabel}</span></td>
        <td>${item.nombre}</td>
        <td>
            <input type="number" step="${stepCantidad}" min="${minCantidad}" class="form-control cantidad-input" required>
        </td>
        <td>
            <input type="number" step="0.01" min="0" class="form-control precio-input" required>
        </td>
        <td>
            <select class="form-select moneda-select">
                <option value="4">$</option>
                <option value="5">USD</option>
                <option value="3">€</option>
            </select>
        </td>
        <td>
            <input type="hidden" name="items[${itemCount}][tipo]" value="${tipo}">
            <input type="hidden" name="items[${itemCount}][materia_prima_id]" value="${tipo === 'materia_prima' ? item.id : ''}">
            <input type="hidden" name="items[${itemCount}][producto_id]" value="${tipo === 'producto' ? item.id : ''}">
            <input type="hidden" name="items[${itemCount}][cantidad]" class="cantidad-hidden">
            <input type="hidden" name="items[${itemCount}][precio_unitario]" class="precio-hidden">
            <input type="hidden" name="items[${itemCount}][moneda]" class="moneda-hidden">
            <button type="button" class="btn btn-danger btn-sm" onclick="eliminarLinea('${uniqueId}')">×</button>
        </td>
    `;

    tbody.appendChild(tr);

    // Sincronizar inputs
    const cantidadInput = tr.querySelector('.cantidad-input');
    const precioInput = tr.querySelector('.precio-input');
    const monedaSelect = tr.querySelector('.moneda-select');
    const cantidadHidden = tr.querySelector('.cantidad-hidden');
    const precioHidden = tr.querySelector('.precio-hidden');
    const monedaHidden = tr.querySelector('.moneda-hidden');

    function syncInputs() {
        if (cantidadHidden) cantidadHidden.value = cantidadInput ? cantidadInput.value : '';
        if (precioHidden) precioHidden.value = precioInput ? precioInput.value : '';
        if (monedaHidden) monedaHidden.value = monedaSelect ? monedaSelect.value : '1';
    }

    if (cantidadInput) cantidadInput.addEventListener('input', syncInputs);
    if (precioInput) precioInput.addEventListener('input', syncInputs);
    if (monedaSelect) monedaSelect.addEventListener('change', syncInputs);
    
    syncInputs();
    itemCount++;
}

// ➕ Agregar línea
document.getElementById('btn-agregar').addEventListener('click', function () {
    if (!itemSeleccionado) {
        alert('Seleccione un producto o materia prima');
        return;
    }

    agregarLinea(itemSeleccionado);
    itemSeleccionado = null;
    document.getElementById('buscador-item').value = '';
    document.getElementById('resultados').style.display = 'none';
});

// 🗑 Eliminar línea
function eliminarLinea(uniqueId) {
    document.getElementById('item-' + uniqueId).remove();
}

// ❗ Validación antes de enviar
document.querySelector('form').addEventListener('submit', function (e) {
    // Sincronizar todos los inputs antes de validar
    document.querySelectorAll('#detalle tr').forEach(tr => {
        const cantidadInput = tr.querySelector('.cantidad-input');
        const cantidadHidden = tr.querySelector('.cantidad-hidden');
        if (cantidadInput && cantidadHidden) {
            cantidadHidden.value = cantidadInput.value;
        }
        
        const precioInput = tr.querySelector('.precio-input');
        const precioHidden = tr.querySelector('.precio-hidden');
        if (precioInput && precioHidden) {
            precioHidden.value = precioInput.value;
        }
        
        const monedaSelect = tr.querySelector('.moneda-select');
        const monedaHidden = tr.querySelector('.moneda-hidden');
        if (monedaSelect && monedaHidden) {
            monedaHidden.value = monedaSelect.value;
        }
    });

    const filas = document.querySelectorAll('#detalle tr');
    if (filas.length === 0) {
        e.preventDefault();
        alert('Debe agregar al menos un producto o materia prima');
    }
});
</script>

</form>