<h1>Editar Receta - <?= $receta['id'] ?></h1>
<div class="container">
<form method="post" class="gap-2" id="formReceta">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::generate()) ?>">
    <input type="hidden" name="receta_id" class="form-control" value="<?= $receta['id'] ?>">
    <input type="hidden" name="producto_id" class="form-control" value="<?= $receta['producto_id'] ?>">
    <label>Producto Final - <?= htmlspecialchars($receta['producto']) ?></label>
    <label>Nombre:</label>
    <input type="text" class="form-control mb-3" required name="nombre" value="<?= htmlspecialchars($receta['nombre']) ?>">
    <hr>

    <h5>Insumos (Materias Primas)</h5>
    <div class="table-scroll">
        <table class="table" id="detalleMaterias">
            <thead>
                <tr>
                    <th>Materia Prima</th>
                    <th width="150">Cantidad</th>
                    <th width="50"></th>
                </tr>
            </thead>
            <tbody>
                <?php $itemIndex = 0; ?>
                <?php foreach ($rec_det as $det): $i = $itemIndex++; ?>
                <tr>
                    <td>
                        <input type="hidden" name="items[<?= $i ?>][tipo]" value="MATERIA_PRIMA">
                        <select name="items[<?= $i ?>][materia_prima_id]" class="form-control" required>
                            <?php
                            $mpActualEnLista = false;
                            foreach ($materias as $m) {
                                if ((int)$m['id'] === (int)$det['materia_prima_id']) {
                                    $mpActualEnLista = true;
                                    break;
                                }
                            }
                            ?>
                            <?php if (!$mpActualEnLista): ?>
                                <option value="<?= (int)$det['materia_prima_id'] ?>" selected>
                                    <?= htmlspecialchars($det['nombre']) ?> (inactiva)
                                </option>
                            <?php endif; ?>
                            <?php foreach ($materias as $m): ?>
                                <option value="<?= (int)$m['id'] ?>" <?= (int)$m['id'] === (int)$det['materia_prima_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($m['nombre']) ?> - (<?= htmlspecialchars($m['nombre_unidad'] ?? '') ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td>
                        <input step="0.001" min="0" class="form-control"
                               type="number" name="items[<?= $i ?>][cantidad]"
                               value="<?= htmlspecialchars((string)$det['cantidad']) ?>" required>
                    </td>
                    <td>
                        <button type="button" class="btn btn-danger btn-sm"
                                onclick="this.closest('tr').remove()">×</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <button type="button" class="btn btn-secondary mb-3" onclick="agregarMateria()">
        + Agregar Materia Prima
    </button>

    <?php if (!empty($usarProductosRecetas) || !empty($rec_productos)): ?>
    <hr>
    <h5>Productos componentes</h5>
    <?php if (!empty($usarProductosRecetas)): ?>
        <p class="text-muted small">
            Podés sumar productos ya fabricados (semielaborados, kits o componentes) además de las materias primas.
        </p>
        <div class="table-scroll">
            <table class="table" id="detalleProductos">
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th width="150">Cantidad</th>
                        <th width="50"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rec_productos as $prod): $i = $itemIndex++; ?>
                    <tr>
                        <td>
                            <input type="hidden" name="items[<?= $i ?>][tipo]" value="PRODUCTO">
                            <select name="items[<?= $i ?>][producto_id]" class="form-control" required>
                                <?php
                                $prodActualEnLista = false;
                                foreach ($productos as $p) {
                                    if ((int)$p['id'] === (int)$prod['producto_id']) {
                                        $prodActualEnLista = true;
                                        break;
                                    }
                                }
                                ?>
                                <?php if (!$prodActualEnLista): ?>
                                    <option value="<?= (int)$prod['producto_id'] ?>" selected>
                                        <?= htmlspecialchars($prod['nombre']) ?> (inactivo)
                                    </option>
                                <?php endif; ?>
                                <?php foreach ($productos as $p): ?>
                                    <option value="<?= (int)$p['id'] ?>" <?= (int)$p['id'] === (int)$prod['producto_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($p['nombre']) ?><?= !empty($p['sku']) ? ' - '.htmlspecialchars($p['sku']) : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td>
                            <input step="0.001" min="0" class="form-control"
                                   type="number" name="items[<?= $i ?>][cantidad]"
                                   value="<?= htmlspecialchars((string)$prod['cantidad']) ?>" required>
                        </td>
                        <td>
                            <button type="button" class="btn btn-danger btn-sm"
                                    onclick="this.closest('tr').remove()">×</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <button type="button" class="btn btn-secondary mb-3" onclick="agregarProducto()">
            + Agregar Producto
        </button>
    <?php else: ?>
        <div class="alert alert-warning py-2">
            La opción <strong>Productos en recetas</strong> está desactivada en Configuraciones.
            Los productos ya cargados se conservan, pero no se pueden agregar ni quitar.
        </div>
        <div class="table-scroll">
            <table class="table">
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th width="150">Cantidad</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rec_productos as $prod): ?>
                    <tr>
                        <td><?= htmlspecialchars($prod['nombre']) ?></td>
                        <td><?= number_format((float)$prod['cantidad'], 3, ',', '.') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
    <?php endif; ?>

    <hr>
    <h5>Procesos de fabricación</h5>
    <p class="text-muted small">Mínimo 1 proceso. Las horas son estimadas por pieza. El costo es fijo por pieza y no se multiplica por la cantidad.</p>
    <?php
        $procesos = $receta['procesos'] ?? [];
        if (empty($procesos)) {
            $procesos = [['nombre' => 'Proceso 1', 'horas_por_unidad' => 0, 'costo' => 0]];
        }
    ?>
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
                <?php foreach ($procesos as $i => $proceso): ?>
                <tr>
                    <td class="num-proceso"><?= $i + 1 ?></td>
                    <td><input type="text" name="procesos[<?= $i ?>][nombre]" class="form-control proceso-nombre" maxlength="150" required value="<?= htmlspecialchars($proceso['nombre'] ?? '') ?>"></td>
                    <td><input type="number" name="procesos[<?= $i ?>][horas_por_unidad]" class="form-control proceso-horas" step="0.0001" min="0" required value="<?= htmlspecialchars((string)($proceso['horas_por_unidad'] ?? 0)) ?>"></td>
                    <td><input type="number" name="procesos[<?= $i ?>][costo]" class="form-control proceso-costo" step="0.01" min="0" value="<?= htmlspecialchars((string)($proceso['costo'] ?? 0)) ?>"></td>
                    <td><button type="button" class="btn btn-danger btn-sm" onclick="eliminarProceso(this)">×</button></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <button type="button" class="btn btn-secondary mb-3" onclick="agregarProceso()">
        + Agregar Proceso
    </button>

    <label>Indique un procedimiento de fabricacion u observacion, en caso de ser necesario:</label>
    <textarea name="procedimiento" class="form-control" rows="4"><?= htmlspecialchars($receta['proceso_fabrica'] ?? '') ?></textarea>
    <br>
    <button class="btn btn-success">Guardar Receta</button>
    <a href="<?= BASE_URL ?>/recetas" class="btn btn-secondary">Cancelar</a>
</form>
</div>

<script>
let index = <?= (int)($itemIndex ?? 0) ?>;
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
