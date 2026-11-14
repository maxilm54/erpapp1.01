<h1>Nueva Receta</h1>

<form method="post">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::generate()) ?>">
    <input type="text" class="form-control mb-3" required name="nombre" placeholder="Nombre de Receta">
    <label>Producto Final</label>    
    <select name="producto_id" class="form-control" required>
        <?php foreach ($productos as $p): ?>
            <option value="<?= $p['id'] ?>">
                <?= htmlspecialchars($p['nombre']) ?>
            </option>
        <?php endforeach ?>
    </select>

    <hr>

    <h5>Insumos (Materias Primas)</h5>
    <div class="table-scroll mt-3">
        <table class="table" id="detalleMaterias">
            <thead>
                <tr>
                    <th>Materia Prima</th>
                    <th width="150">Cantidad</th>
                    <th width="50"></th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
    <button type="button" class="btn btn-secondary" onclick="agregarMateria()">
        + Agregar Materia Prima
    </button>

    <?php if (!empty($usarProductosRecetas)): ?>
    <hr>
    <h5>Productos componentes</h5>
    <p class="text-muted small">
        Podés sumar productos ya fabricados (semielaborados, kits o componentes) además de las materias primas.
    </p>
    <div class="table-scroll mt-3">
        <table class="table" id="detalleProductos">
            <thead>
                <tr>
                    <th>Producto</th>
                    <th width="150">Cantidad</th>
                    <th width="50"></th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
    <button type="button" class="btn btn-secondary" onclick="agregarProducto()">
        + Agregar Producto
    </button>
    <?php endif; ?>

    <hr>
    <h5>Procesos de fabricación</h5>
    <p class="text-muted small">Mínimo 1 proceso. Las horas son estimadas por pieza. El costo es fijo por pieza y no se multiplica por la cantidad.</p>
    <div class="table-scroll">
        <table class="table" id="tablaProcesos">
            <thead>
                <tr>
                    <th width="50">#</th>
                    <th>Nombre del proceso</th>
                    <th width="180">Horas por pieza</th>
                    <th width="160">Costo fijo</th>
                    <th width="50"></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="num-proceso">1</td>
                    <td><input type="text" name="procesos[0][nombre]" class="form-control proceso-nombre" maxlength="150" required placeholder="Ej: Corte, Torneado, Control"></td>
                    <td><input type="number" name="procesos[0][horas_por_unidad]" class="form-control proceso-horas" step="0.0001" min="0" value="0" required></td>
                    <td><input type="number" name="procesos[0][costo]" class="form-control proceso-costo" step="0.01" min="0" value="0"></td>
                    <td><button type="button" class="btn btn-danger btn-sm" onclick="eliminarProceso(this)">×</button></td>
                </tr>
            </tbody>
        </table>
    </div>
    <button type="button" class="btn btn-secondary mb-3" onclick="agregarProceso()">
        + Agregar Proceso
    </button>

    <label>Indique un procedimiento de fabricacion u observacion, en caso de ser necesario:</label>
    <textarea name="procedimiento" class="form-control" rows="4"></textarea>
    <br><br>
    <button class="btn btn-success">Guardar Receta</button>
    <a href="<?= BASE_URL ?>/recetas" class="btn btn-secondary">Cancelar</a>
</form>

<script>
let index = 0;
const materias = <?= json_encode($materias) ?>;
const productos = <?= json_encode($productos) ?>;

function esc(valor) {
    return String(valor ?? '').replace(/[&<>"']/g, function (c) {
        return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c];
    });
}

function crearFila(tablaId, tipo, campo, opciones) {
    const tbody = document.querySelector('#' + tablaId + ' tbody');
    const tr = document.createElement('tr');
    tr.innerHTML = `
        <td>
            <input type="hidden" name="items[${index}][tipo]" value="${tipo}">
            <select name="items[${index}][${campo}]" class="form-control" required>
                ${opciones}
            </select>
        </td>
        <td>
            <input type="number" step="0.001" min="0"
                   name="items[${index}][cantidad]"
                   class="form-control" required>
        </td>
        <td>
            <button type="button" class="btn btn-danger btn-sm"
                    onclick="this.closest('tr').remove()">×</button>
        </td>
    `;
    tbody.appendChild(tr);
    index++;
}

function agregarMateria() {
    const opciones = materias.map(function (m) {
        return `<option value="${m.id}">${esc(m.nombre)} - (${esc(m.nombre_unidad || '')})</option>`;
    }).join('');
    crearFila('detalleMaterias', 'MATERIA_PRIMA', 'materia_prima_id', opciones);
}

function agregarProducto() {
    const opciones = productos.map(function (p) {
        return `<option value="${p.id}">${esc(p.nombre)}${p.sku ? ' - ' + esc(p.sku) : ''}</option>`;
    }).join('');
    crearFila('detalleProductos', 'PRODUCTO', 'producto_id', opciones);
}

function agregarProceso() {
    const tbody = document.querySelector('#tablaProcesos tbody');
    const tr = document.createElement('tr');
    tr.innerHTML = `
        <td class="num-proceso"></td>
        <td><input type="text" class="form-control proceso-nombre" maxlength="150" required placeholder="Ej: Corte, Torneado, Control"></td>
        <td><input type="number" class="form-control proceso-horas" step="0.0001" min="0" value="0" required></td>
        <td><input type="number" class="form-control proceso-costo" step="0.01" min="0" value="0"></td>
        <td><button type="button" class="btn btn-danger btn-sm" onclick="eliminarProceso(this)">×</button></td>
    `;
    tbody.appendChild(tr);
    reindexarProcesos();
}

function eliminarProceso(btn) {
    const tbody = document.querySelector('#tablaProcesos tbody');
    if (tbody.querySelectorAll('tr').length <= 1) {
        alert('La receta debe tener al menos un proceso.');
        return;
    }
    btn.closest('tr').remove();
    reindexarProcesos();
}

function reindexarProcesos() {
    document.querySelectorAll('#tablaProcesos tbody tr').forEach(function (tr, i) {
        tr.querySelector('.num-proceso').textContent = i + 1;
        const nombre = tr.querySelector('.proceso-nombre');
        const horas = tr.querySelector('.proceso-horas');
        const costo = tr.querySelector('.proceso-costo');
        if (nombre) nombre.name = 'procesos[' + i + '][nombre]';
        if (horas) horas.name = 'procesos[' + i + '][horas_por_unidad]';
        if (costo) costo.name = 'procesos[' + i + '][costo]';
    });
}
</script>