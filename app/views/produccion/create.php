<h1>Nueva Orden de Producción</h1>

<form method="post">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::generate()) ?>">
    <label class="mt-3">Receta</label>
    <div class="d-flex align-items-center gap-2 w-50">
        <select id="receta_id" name="receta_id" class="form-control" required>
            <?php foreach ($recetas as $r): ?>
                <option
                    value="<?= $r['id'] ?>"
                    data-producto="<?= htmlspecialchars($r['producto']) ?>"
                    data-producto-id="<?= $r['producto_id'] ?>"
                    data-obs="<?= htmlspecialchars($r['proceso_fabrica']) ?>"
                >
                    Receta #<?= $r['id'] ?> - <?= htmlspecialchars($r['producto']) ?> (<?= htmlspecialchars($r['nombre']) ?>)
                </option>
            <?php endforeach ?>
        </select>
        <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalReceta" title="Ver receta"><i class="bi bi-info-circle"></i>
        </button>
    </div>
    <label>Producto</label>
    <!-- input readonly para mostrar el producto -->
    <input type="text" id="producto_nombre" class="form-control w-50" readonly>
    <!-- hidden para enviar el producto_id al backend -->
    <input type="hidden" id="producto_id" name="producto_id">

    <div class="row w-50 mt-3">
        <div class="col-md-6">
            <label class="form-label">Cantidad a producir</label>
            <input type="number" step="0.01" min="0.01" name="cantidad" class="form-control"required>
        </div>
        <div class="col-md-6">
            <label class="form-label">Fecha Entrega</label>
            <input type="datetime-local"name="fecha_entrega" class="form-control"required>
        </div>
    </div>
    <div class="mt-3">
            <span id="stock-indicator" class="badge bg-secondary" style="cursor:pointer" data-bs-toggle="modal" data-bs-target="#modalStock"></span>
    </div>

    <label class="mt-3">Observaciones</label>
    <textarea name="observaciones" class="form-control w-50" id="observaciones" rows="6"></textarea>

    <br>
    <button class="btn btn-success">Crear Orden</button>
    <a href="<?= BASE_URL ?>/ordenproduccion" class="btn btn-secondary">Cancelar</a>
</form>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const recetaSelect   = document.getElementById('receta_id');
    const productoNombre = document.getElementById('producto_nombre');
    const productoId     = document.getElementById('producto_id');
    const observaciones  = document.getElementById('observaciones');

    function actualizarDesdeReceta() {
        const option = recetaSelect.selectedOptions[0];

        productoNombre.value = option.dataset.producto || '';
        productoId.value     = option.dataset.productoId || '';
        observaciones.value  = option.dataset.obs || '';
    }

    // inicial
    actualizarDesdeReceta();

    // cambio de receta
    recetaSelect.addEventListener('change', () => {
        actualizarDesdeReceta();
        chequearStock(); // 🔥 ya que existe, lo aprovechamos
    });
});
</script>

<div class="modal fade" id="modalReceta" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Detalle de Receta</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body" id="modal-receta-body">
        <div class="text-center text-muted">Cargando receta...</div>
      </div>
    </div>
  </div>
</div>

<script>
document.getElementById('modalReceta').addEventListener('show.bs.modal', function () {
    let recetaId = document.getElementById('receta_id').value;
    let body = document.getElementById('modal-receta-body');

    body.innerHTML = 'Cargando receta...';

    fetch("<?= BASE_URL ?>/recetas/ajaxShow/" + recetaId)
        .then(r => r.text())
        .then(html => body.innerHTML = html)
        .catch(() => body.innerHTML = 'Error al cargar receta');
});
</script>

<script>
const recetaSelect = document.getElementById('receta_id');
const cantidadInput = document.querySelector('input[name="cantidad"]');
const indicador = document.getElementById('stock-indicator');

function chequearStock() {
    const recetaId = recetaSelect.value;
    const cantidad = cantidadInput.value;

    if (!recetaId || cantidad <= 0) {
        indicador.className = 'badge bg-secondary';
        indicador.textContent = 'Stock pendiente';
        return;
    }

    fetch("<?= BASE_URL ?>/ordenproduccion/checkStock", {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `receta_id=${recetaId}&cantidad=${cantidad}`
    })
    .then(r => r.json())
    .then(data => {
        indicador.className = 'badge';

        if (data.estado === 'ok') { // si el ajax devuelve ok esta en verde.
            indicador.classList.add('bg-success');
            indicador.textContent = '✔ Stock OK';
        }
        if (data.estado === 'warning') {
            indicador.classList.add('bg-warning', 'text-dark');
            indicador.textContent = '⚠ Stock parcial'; // si el ajax devuelve warning esta en amarillo, y muestra la cantidad faltante al hacer click para incentivar a generar orden de compra de esa materia prima faltante.
        }
        if (data.estado === 'error') {
            indicador.classList.add('bg-danger');
            indicador.textContent = '✖ Stock insuficiente'; // no existe stock para producir la cantidad solicitada, indicador en rojo y al hacer click muestra detalle de lo que falta para incentivar a generar orden de compra.
        }

        indicador.dataset.detalle = JSON.stringify(data.faltantes);
    });
}

recetaSelect.addEventListener('change', chequearStock);
cantidadInput.addEventListener('input', chequearStock);
</script>

<div class="modal fade" id="modalStock">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Detalle de Stock</h5>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="stock-detalle"></div>
    </div>
  </div>
</div>
<script>
document.addEventListener('click', function(e) {
    if (e.target && e.target.id === 'generarOC') {

        const faltantes = JSON.parse(indicador.dataset.detalle || '[]');

        fetch("<?= BASE_URL ?>/ordenescompra/generardesdefaltantes", {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                faltantes: faltantes
            })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Orden de Compra generada',
                    text: 'OC #' + data.id + ' creada correctamente.',
                    showCancelButton: true,
                    confirmButtonText: 'Ver Orden',
                    cancelButtonText: 'Seguir con Producción'
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.open("<?= BASE_URL ?>/ordenescompra/show/" + data.id, '_blank');
                    }
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'No se pudo generar la Orden de Compra'
                });
            }
        });
    }
});
</script>

<script>
document.getElementById('modalStock').addEventListener('show.bs.modal', () => {
    const data = JSON.parse(indicador.dataset.detalle || '[]');
    let html = '<table class="table table-sm">';
    html += '<tr><th>Materia Prima</th><th>Necesario</th><th>Disponible</th><th>Faltante</th></tr>';
    data.forEach(i => {
        html += `<tr class="table-danger">
            <td>${i.materia_prima}</td>
            <td>${i.necesario}</td>
            <td>${i.disponible}</td>
            <td>${i.faltante}</td>
        </tr>`;
    });
    html += '</table>';
    html += `<button id="generarOC" class="btn btn-warning">Generar Orden de Compra</button>`;
    document.getElementById('stock-detalle').innerHTML = html;
});
</script>