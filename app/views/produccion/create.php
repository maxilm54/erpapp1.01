<h1>Nueva Orden de Producción</h1>

<form method="post" id="formOrdenProduccion">
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

    <div class="mt-3">
        <h5 class="h6">Depósito de origen de las materias primas</h5>
        <p class="text-muted small mb-2">
            Podés repartir la cantidad de cada MP entre <strong>varios depósitos</strong> (por ejemplo,
            3 M de <strong>MP sobrante</strong> + 6 M del principal). La suma de las fuentes debe coincidir
            con lo necesario. Los valores en rojo superan el stock del depósito; se permite dejarlo negativo
            para planificar la compra.
        </p>
        <div id="detalle-mp">
            <div class="text-muted small">Seleccioná una receta y una cantidad para ver las materias primas.</div>
        </div>
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

function escaparHtml(valor) {
    const div = document.createElement('div');
    div.textContent = valor == null ? '' : String(valor);
    return div.innerHTML;
}

function formatearCantidad(valor, decimales) {
    const n = parseFloat(valor);
    if (!isFinite(n)) return '0';
    return n.toLocaleString('es-AR', { maximumFractionDigits: decimales == null ? 3 : decimales });
}

function actualizarTotalesFuentes() {
    const inputs = document.querySelectorAll('.fuente-input');
    const porMp = {};

    inputs.forEach(function (inp) {
        const mpId = inp.dataset.mp;
        if (!porMp[mpId]) porMp[mpId] = [];
        porMp[mpId].push(inp);
    });

    Object.keys(porMp).forEach(function (mpId) {
        let total = 0;
        porMp[mpId].forEach(function (inp) {
            const cant = parseFloat(inp.value) || 0;
            const stock = parseFloat(inp.dataset.stock) || 0;
            total += cant;
            const excede = cant > stock + 0.000001;
            inp.classList.toggle('is-invalid', excede);
            inp.classList.toggle('text-danger', excede);
        });

        const ref = document.querySelector('.total-mp[data-mp="' + mpId + '"]');
        if (!ref) return;
        const neces = parseFloat(ref.dataset.necesario) || 0;
        const unidad = ref.dataset.unidad || '';
        let detalle = '';
        if (total + 0.0005 < neces) {
            detalle = ' — faltan ' + formatearCantidad(neces - total) + ' ' + unidad;
        } else if (total > neces + 0.0005) {
            detalle = ' — sobran ' + formatearCantidad(total - neces) + ' ' + unidad;
        }
        ref.textContent = 'Total asignado: ' + formatearCantidad(total) + ' / ' + formatearCantidad(neces) + ' ' + unidad + detalle;
        ref.classList.toggle('text-success', detalle === '');
        ref.classList.toggle('text-danger', detalle !== '');
    });
}

function renderDepositos(items) {
    const cont = document.getElementById('detalle-mp');
    if (!items.length) {
        cont.innerHTML = '<div class="text-muted small">Seleccioná una receta y una cantidad para ver las materias primas.</div>';
        return;
    }

    let html = '<div class="table-responsive"><table class="table table-sm table-bordered align-middle">';
    html += '<thead class="table-dark"><tr><th>Materia Prima</th><th class="text-end">Necesario</th><th>Fuentes (depósitos)</th></tr></thead><tbody>';

    items.forEach(function (it) {
        const unidad = escaparHtml(it.unidad);
        const mpId = it.materia_prima_id;
        const neces = parseFloat(it.necesario) || 0;
        const depositos = it.depositos || [];

        const filas = depositos.map(function (d) {
            const stock = parseFloat(d.stock) || 0;
            const sugerido = parseFloat(d.sugerido) || 0;
            const badgeSobrante = d.es_sobrante ? ' <span class="badge bg-info text-dark">sobrante</span>' : '';
            return '<tr>'
                + '<td>' + escaparHtml(d.nombre) + badgeSobrante + '</td>'
                + '<td class="text-end">' + formatearCantidad(stock) + ' ' + unidad + '</td>'
                + '<td style="width:150px;">'
                + '<input type="number" step="0.001" min="0" class="form-control form-control-sm fuente-input"'
                + ' name="deposito_items[' + mpId + '][' + d.id + ']"'
                + ' value="' + (sugerido > 0 ? sugerido : '') + '"'
                + ' data-mp="' + mpId + '" data-necesario="' + neces + '"'
                + ' data-stock="' + stock + '" data-unidad="' + unidad + '">'
                + '</td>'
                + '</tr>';
        }).join('');

        const presentacion = (it.presentacion && parseFloat(it.presentacion) > 0)
            ? '<br><small class="text-muted">Presentación: ' + formatearCantidad(it.presentacion, 2) + ' ' + unidad + '</small>'
            : '';

        html += '<tr>';
        html += '<td><strong>' + escaparHtml(it.materia_prima) + '</strong>' + presentacion + '</td>';
        html += '<td class="text-end">' + formatearCantidad(neces) + ' ' + unidad + '</td>';
        html += '<td>';
        html += '<table class="table table-sm mb-1"><thead><tr><th>Depósito</th><th class="text-end">Stock</th><th class="text-end">Cantidad</th></tr></thead><tbody>' + filas + '</tbody></table>';
        html += '<small class="total-mp" data-mp="' + mpId + '" data-nombre="' + escaparHtml(it.materia_prima) + '" data-necesario="' + neces + '" data-unidad="' + unidad + '"></small>';
        html += '</td>';
        html += '</tr>';
    });

    html += '</tbody></table></div>';
    cont.innerHTML = html;

    cont.querySelectorAll('.fuente-input').forEach(function (inp) {
        inp.addEventListener('input', actualizarTotalesFuentes);
    });
    actualizarTotalesFuentes();
}

function chequearStock() {
    const recetaId = recetaSelect.value;
    const cantidad = cantidadInput.value;

    if (!recetaId || cantidad <= 0) {
        indicador.className = 'badge bg-secondary';
        indicador.textContent = 'Stock pendiente';
        renderDepositos([]);
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
        renderDepositos(data.items || []);
    });
}

function validarTotalesFuentes() {
    const mensajes = [];
    document.querySelectorAll('.total-mp').forEach(function (ref) {
        const mpId = ref.dataset.mp;
        let total = 0;
        document.querySelectorAll('.fuente-input[data-mp="' + mpId + '"]').forEach(function (inp) {
            total += parseFloat(inp.value) || 0;
        });
        const neces = parseFloat(ref.dataset.necesario) || 0;
        const unidad = ref.dataset.unidad || '';
        if (Math.abs(total - neces) > 0.0005) {
            mensajes.push(
                escaparHtml(ref.dataset.nombre || 'Materia prima') + ': '
                + formatearCantidad(total) + ' de ' + formatearCantidad(neces) + ' ' + unidad
            );
        }
    });
    return mensajes;
}

document.getElementById('formOrdenProduccion').addEventListener('submit', function (e) {
    if (!document.querySelector('.fuente-input')) return;
    const problemas = validarTotalesFuentes();
    if (problemas.length) {
        e.preventDefault();
        Swal.fire({
            icon: 'warning',
            title: 'Revisá las fuentes de MP',
            html: 'La suma de cada materia prima debe coincidir con lo necesario:<br><br>' + problemas.join('<br>')
        });
    }
});

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