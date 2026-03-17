<?php
$isEdit = isset($presupuesto);
$action = $isEdit
    ? BASE_URL.'/presupuestos/update/'.$presupuesto['id']
    : BASE_URL.'/presupuestos/store';
?>

<div class="container-fluid">
    <h3 class="mb-3">
        <?= $isEdit ? 'Editar Presupuesto' : 'Nuevo Presupuesto' ?>
    </h3>

    <form method="POST" action="<?= $action ?>">
        <!-- Cliente -->
        <div class="mb-3">
            <label class="form-label">Cliente</label>
            <select name="cliente_id" class="form-select" required>
                <option value="">Seleccione</option>
                <?php foreach ($clientes as $c): ?>
                    <option value="<?= $c['id'] ?>"
                        <?= $isEdit && $c['id'] == $presupuesto['cliente_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['razon_social']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Detalle -->
        <h5 class="mt-4">Detalle</h5>

        <div class="row g-2 align-items-end">
            <div class="col-md-10">
                <label class="form-label">Producto</label>
                <input
                    type="text"
                    id="producto_buscar"
                    class="form-control"
                    placeholder="Buscar producto..."
                    autocomplete="off"
                >
                <div id="resultado_productos" class="list-group position-absolute w-100 d-none" style="z-index: 1000;"></div>
            </div>

            <div class="col-md-2">
                <button type="button" id="btn_agregar_producto" class="btn btn-success w-100" disabled>
                    +
                </button>
            </div>
        </div>
        <div class="table-scroll table-responsive mt-3">
            <table class="table table-bordered align-middle" id="tabla_detalle">
                <thead class="table-dark">
                    <tr>
                        <th>Producto</th>
                        <th width="120">Cantidad</th>
                        <th width="150">Precio</th>
                        <th width="50"></th>
                    </tr>
                </thead>
                <tbody>
                    <!-- líneas dinámicas -->
                </tbody>
            </table>
        </div>
        <label>Proceso u Observaciones de Fabricacion</label>
        <textarea name="procedimiento" class="form-control" rows="4"></textarea>
        <div class="mt-3">
            <button class="btn btn-success">Guardar</button>
            <a href="<?= BASE_URL ?>/presupuestos" class="btn btn-secondary">Cancelar</a>
        </div>
    </form>
</div>
<script>
const PRODUCTOS = <?= json_encode($productos, JSON_UNESCAPED_UNICODE) ?>;
</script>
<script>
let productoSeleccionado = null;

const inputBuscar   = document.getElementById('producto_buscar');
const resultados    = document.getElementById('resultado_productos');
const btnAgregar    = document.getElementById('btn_agregar_producto');
const tablaDetalle  = document.querySelector('#tabla_detalle tbody');

inputBuscar.addEventListener('input', function () {
    const q = this.value.toLowerCase();
    resultados.innerHTML = '';
    productoSeleccionado = null;
    btnAgregar.disabled = true;

    if (q.length < 2) {
        resultados.classList.add('d-none');
        return;
    }

    const filtrados = PRODUCTOS.filter(p =>
        p.nombre.toLowerCase().includes(q)
    );

    if (filtrados.length === 0) {
        resultados.classList.add('d-none');
        return;
    }

    filtrados.forEach(p => {
        const item = document.createElement('button');
        item.type = 'button';
        item.className = 'list-group-item list-group-item-action';
        item.textContent = p.nombre;

        item.onclick = () => {
            inputBuscar.value = p.nombre;
            productoSeleccionado = p;
            resultados.classList.add('d-none');
            btnAgregar.disabled = false;
        };

        resultados.appendChild(item);
    });

    resultados.classList.remove('d-none');
});

let index = 0;

btnAgregar.addEventListener('click', function () {
    if (!productoSeleccionado) return;

    const row = document.createElement('tr');

    row.innerHTML = `
        <td>
            ${productoSeleccionado.nombre}
            <input type="hidden" name="items[${index}][producto_id]" value="${productoSeleccionado.id}">
        </td>
        <td>
            <input type="number" step="0.01" name="items[${index}][cantidad]" class="form-control" required>
        </td>
        <td>
            <input type="number" step="0.01" name="items[${index}][precio]" value="${productoSeleccionado.precio_venta}" class="form-control" required>
        </td>
        <td class="text-center">
            <button type="button" class="btn btn-danger btn-sm">&times;</button>
        </td>
    `;

    row.querySelector('button').onclick = () => row.remove();

    tablaDetalle.appendChild(row);

    index++; // 🔑 CLAVE

    inputBuscar.value = '';
    productoSeleccionado = null;
    btnAgregar.disabled = true;
});
</script>