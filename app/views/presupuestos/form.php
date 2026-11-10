<?php
$isEdit = isset($presupuesto);
error_log($isEdit);
$action = $isEdit
    ? BASE_URL.'/presupuestos/update/'.$presupuesto['id']
    : BASE_URL.'/presupuestos/store';
?>

<div class="container-fluid">
    <h3 class="mb-3">
        <?= $isEdit ? 'Editar Presupuesto' : 'Nuevo Presupuesto' ?>
    </h3>

    <form method="POST" action="<?= $action ?>" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::generate()) ?>">
        <!-- Cliente -->
        <div class="mb-3">
            <label class="form-label">Cliente</label>
            <select name="cliente_id" id="cliente_id" class="form-select" required>
                <option value="">Seleccione</option>
                <?php foreach ($clientes as $c): ?>
                    <option value="<?= htmlspecialchars($c['id']) ?>"
                        <?= $isEdit && $c['id'] == $presupuesto['cliente_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['razon_social']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <div id="hint_nomenclador" class="form-text d-none">
                <i class="bi bi-tags"></i> Este cliente usa el nomenclador
                <strong id="hint_nomenclador_valor"></strong>:
                los productos se identificarán como <code>SKU-<span id="hint_nomenclador_sufijo"></span></code>.
            </div>
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
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="libre_nomenclador" disabled>
                            <label class="form-check-label" for="libre_nomenclador" title="Requiere un cliente con nomenclador">
                                Aplicar nomenclador (SKU-...)
                            </label>
                        </div>
                    </div>
                    <div class="col-md-1">
                        <button type="button" id="btn_agregar_libre" class="btn btn-outline-success w-100">+</button>
                    </div>
                </div>
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
                    <?php if ($isEdit && !empty($presupuesto['detalle'])): ?>
                        <?php 
                        function esUnidadEntera($nombreMedida) {
                            $nombre = strtolower($nombreMedida ?? '');
                            return in_array($nombre, ['u', 'un', 'unidad', 'unidades']);
                        }
                        ?>
                        <?php foreach ($presupuesto['detalle'] as $idx => $item): ?>
                        <?php 
                            $stepCantidad = esUnidadEntera($item['nombre_medida']) ? '1' : '0.01';
                        ?>
                        <tr>
                            <td>
                                <?= htmlspecialchars($item['nombre']) ?>
                                <?php if (!empty($item['producto_id'])): ?>
                                    <input type="hidden" name="items[<?= $idx ?>][producto_id]" value="<?= $item['producto_id'] ?>">
                                <?php else: ?>
                                    <span class="badge bg-info">Libre</span>
                                    <input type="hidden" name="items[<?= $idx ?>][descripcion]" value="<?= htmlspecialchars($item['descripcion'] ?? $item['nombre']) ?>">
                                <?php endif; ?>
                            </td>
                            <td>
                                <input type="number" step="<?= $stepCantidad ?>" min="<?= $stepCantidad ?>" name="items[<?= $idx ?>][cantidad]" value="<?= $item['cantidad'] ?>" class="form-control" required>
                            </td>
                            <td>
                                <input type="number" step="0.01" name="items[<?= $idx ?>][precio]" value="<?= $item['precio'] ?>" class="form-control" required>
                            </td>
                            <td class="text-center">
                                <button type="button" class="btn btn-danger btn-sm" onclick="this.closest('tr').remove()">&times;</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <label>Observaciones:</label>
        <textarea name="observaciones" class="form-control" rows="4"><?= htmlspecialchars($presupuesto['observaciones'] ?? '') ?></textarea>

        <?php $cpCampos = $cpCampos ?? []; $cpValores = $cpValores ?? [];
              require BASE_PATH . '/app/views/campos_personalizados/inputs.php'; ?>

        <?php if (!empty($usarAdjuntos)): ?>
        <!-- Adjuntos (config Empresa -> Configuraciones) -->
        <div class="card border-secondary mt-3">
            <div class="card-header bg-secondary-subtle py-2">
                <strong><i class="bi bi-paperclip"></i> Adjuntar archivos</strong>
                <small class="text-muted">— también podrá agregar más luego, desde la vista del presupuesto</small>
            </div>
            <div class="card-body py-2">
                <input type="file" class="form-control form-control-sm" name="archivos_adjuntos[]" multiple
                       accept=".pdf,.jpg,.jpeg,.png,.gif,.webp,.heic,.doc,.docx,.odt,.xls,.xlsx,.ods,.csv,.ppt,.pptx,.txt,.zip,.rar,.msg,.eml">
                <div class="form-text small">Hasta 10 MB por archivo. Al aprobar este presupuesto, los adjuntos viajan con la NP automática.</div>
            </div>
        </div>
        <?php endif; ?>

        <div class="mt-3">
            <button class="btn btn-success">Guardar</button>
            <a href="<?= BASE_URL ?>/presupuestos" class="btn btn-secondary">Cancelar</a>
        </div>
    </form>
</div>
<script>
const PRODUCTOS = <?= json_encode($productos, JSON_UNESCAPED_UNICODE) ?>;
// Nomenclador (sufijo de codigo) por cliente, desde el ABM de Clientes
const NOMENCLADORES = <?= json_encode(
    array_reduce($clientes, function ($acc, $c) {
        $nom = trim((string)($c['nomenclador'] ?? ''));
        if ($nom !== '') $acc[$c['id']] = $nom;
        return $acc;
    }, [])
, JSON_UNESCAPED_UNICODE) ?>;
const USAR_NOMENCLADOR = <?= json_encode($usarNomenclador ?? true) ?>;
</script>
<script>
let productoSeleccionado = null;

const inputBuscar   = document.getElementById('producto_buscar');
const resultados    = document.getElementById('resultado_productos');
const btnAgregar    = document.getElementById('btn_agregar_producto');
const tablaDetalle  = document.querySelector('#tabla_detalle tbody');

// Nomenclador del cliente seleccionado (null si no aplica)
function nomencladorCliente() {
    if (!USAR_NOMENCLADOR) return null;
    const id = document.getElementById('cliente_id').value;
    return id && NOMENCLADORES[id] ? NOMENCLADORES[id] : null;
}

function actualizarHintNomenclador() {
    const nom = nomencladorCliente();
    const hint = document.getElementById('hint_nomenclador');
    const chk = document.getElementById('libre_nomenclador');

    if (nom) {
        hint.classList.remove('d-none');
        document.getElementById('hint_nomenclador_valor').textContent = nom;
        document.getElementById('hint_nomenclador_sufijo').textContent = nom;
    } else {
        hint.classList.add('d-none');
        chk.checked = false;
    }
    chk.disabled = !nom;
}

document.getElementById('cliente_id').addEventListener('change', actualizarHintNomenclador);
document.getElementById('libre_crear').addEventListener('change', function () {
    const nom = nomencladorCliente();
    document.getElementById('libre_nomenclador').disabled = !nom || !this.checked;
    if (!this.checked) document.getElementById('libre_nomenclador').checked = false;
});
document.addEventListener('DOMContentLoaded', actualizarHintNomenclador);
<?php if ($isEdit): ?>
// En edicion el cliente ya viene elegido: mostrar el hint al cargar
setTimeout(actualizarHintNomenclador, 0);
<?php endif; ?>

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

<?php $initialIndex = ($isEdit && !empty($presupuesto['detalle'])) ? count($presupuesto['detalle']) : 0; ?>
let index = <?= $initialIndex ?>;

btnAgregar.addEventListener('click', function () {
    if (!productoSeleccionado) return;

    // Determinar step según unidad de medida (U, UN, UNIDAD = entero)
    const nombreMedida = (productoSeleccionado.nombre_medida || '').toLowerCase();
    const esUnidadEntera = ['u', 'un', 'unidad', 'unidades'].includes(nombreMedida);
    const stepCantidad = esUnidadEntera ? '1' : '0.01';
    const minCantidad = esUnidadEntera ? '1' : '0.01';

    const row = document.createElement('tr');

    // Si el cliente usa nomenclador, el backend crea/usara la variante SKU-nom
    const nom = nomencladorCliente();
    const badgeVariante = nom
        ? ` <span class="badge bg-secondary">${productoSeleccionado.sku}-${nom}</span>`
        : '';

    row.innerHTML = `
        <td>
            ${productoSeleccionado.nombre}${badgeVariante}
            <input type="hidden" name="items[${index}][producto_id]" value="${productoSeleccionado.id}">
        </td>
        <td>
            <input type="number" step="${stepCantidad}" min="${minCantidad}" name="items[${index}][cantidad]" class="form-control" required>
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

// 🆓 Agregar producto libre (no existe en la base)
function escapeHtml(texto) {
    return String(texto)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

document.getElementById('btn_agregar_libre').addEventListener('click', function () {
    const nombre   = document.getElementById('libre_nombre').value.trim();
    const cantidad = parseFloat(document.getElementById('libre_cantidad').value) || 0;
    const precio   = parseFloat(document.getElementById('libre_precio').value) || 0;
    const crear    = document.getElementById('libre_crear').checked;
    const conNom   = crear && document.getElementById('libre_nomenclador').checked;

    if (nombre === '') {
        Swal.fire('Falta el nombre', 'Escriba el nombre o descripción del producto libre.', 'warning');
        return;
    }
    if (cantidad <= 0) {
        Swal.fire('Cantidad inválida', 'La cantidad debe ser mayor a cero.', 'warning');
        return;
    }

    const nom = nomencladorCliente();
    const badgeNom = (crear && nom) ? `<span class="badge bg-dark">SKU ...-${nom}</span>` : '';

    const row = document.createElement('tr');
    row.innerHTML = `
        <td>
            ${escapeHtml(nombre)} <span class="badge bg-info">Libre</span>
            ${crear ? '<span class="badge bg-success">se agregará a Productos</span>' : ''}
            ${badgeNom}
            <input type="hidden" name="items[${index}][descripcion]" value="${escapeHtml(nombre)}">
            <input type="hidden" name="items[${index}][crear_producto]" value="${crear ? 1 : 0}">
            <input type="hidden" name="items[${index}][aplicar_nomenclador]" value="${conNom ? 1 : 0}">
        </td>
        <td>
            <input type="number" step="0.01" min="0.01" name="items[${index}][cantidad]" value="${cantidad}" class="form-control" required>
        </td>
        <td>
            <input type="number" step="0.01" min="0" name="items[${index}][precio]" value="${precio}" class="form-control" required>
        </td>
        <td class="text-center">
            <button type="button" class="btn btn-danger btn-sm">&times;</button>
        </td>
    `;
    row.querySelector('button').onclick = () => row.remove();
    tablaDetalle.appendChild(row);
    index++;

    document.getElementById('libre_nombre').value = '';
    document.getElementById('libre_cantidad').value = 1;
    document.getElementById('libre_precio').value = 0;
    document.getElementById('libre_crear').checked = false;
    document.getElementById('libre_nomenclador').checked = false;
    document.getElementById('libre_nomenclador').disabled = !nomencladorCliente();
});
</script>