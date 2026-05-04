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

    <h5>Insumos</h5>
    <div class="table-scroll mt-3">
        <table class="table" id="detalle">
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
    <button type="button" class="btn btn-secondary" onclick="agregarLinea()">
        + Agregar Insumo
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

function agregarLinea() {
    const tbody = document.querySelector('#detalle tbody');
    const tr = document.createElement('tr');

    let options = materias.map(m =>
        `<option value="${m.id}">${m.nombre} - (${m.unidad_medida})</option>`
    ).join('');

    tr.innerHTML = `
        <td>
            <select name="items[${index}][materia_prima_id]"
                    class="form-control" required>
                ${options}
            </select>
        </td>
        <td>
            <input type="number" step="0.001"
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
</script>