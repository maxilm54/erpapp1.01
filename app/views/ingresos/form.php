<h3 class="mb-3"><?= $title ?></h3>

<form method="POST" class="card p-4 col-md-10 mx-auto" id="formIngreso">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::generate()) ?>">
    <div class="mb-3">
        <label class="form-label">Proveedor: <?= htmlspecialchars($proveedor) ?> #OC: <?= $orden_compra_id ?></label>
    </div>
    <div class="mb-3">
        <label class="form-label">Remito</label>
        <input type="text"
            name="remito"
            class="form-control"
            placeholder="00000-00000000"
            pattern="\d{5}-\d{8}"
            required>
    </div>
    <div class="mb-3">
        <label class="form-label">Depósito de destino</label>
        <select name="deposito_id" class="form-select" required id="depositoGlobal">
            <option value="">Seleccionar depósito...</option>
            <?php foreach ($depositos as $d): ?>
            <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['nombre']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php
    $haySerie = false;
    foreach ($detalle as $it) {
        if (!empty($it['usa_serie']) && (float)$it['faltante'] > 0) { $haySerie = true; break; }
    }
    if ($haySerie): ?>
    <div class="alert alert-info mb-3">
        <i class="fas fa-info-circle"></i> <strong>Recepción parcial:</strong> para items con N° de serie, deje vacío el número de las unidades que no recibirá en esta recepción (podrá recibirlas luego).
    </div>
    <?php endif; ?>
    <table class="table table-bordered" id="tablaItems">
        <thead class="table-dark">
            <tr>
                <th>Tipo</th>
                <th>Nombre</th>
                <th>Cant. Pedida</th>
                <th>Ya Recibida</th>
                <th>Faltante</th>
                <th>Cantidad a Ingresar</th>
                <th>Lote</th>
                <th>N° Serie</th>
                <th>Vencimiento</th>
            </tr>
        </thead>
        <tbody>
            <?php $index = 0; foreach ($detalle as $item): ?>
            <?php
                $esSerie = !empty($item['usa_serie']);
                $faltante = (float)$item['faltante'];
            ?>
            <?php if ($esSerie && $faltante > 0): ?>
            <!-- ITEM CON SERIE: una fila por cada unidad faltante -->
            <?php for ($u = 0; $u < (int)$faltante; $u++): ?>
            <tr class="fila-serie" data-tipo="<?= ($item['tipo'] ?? 'materia_prima') ?>">
                <td>
                    <span class="badge bg-<?= ($item['tipo'] ?? 'materia_prima') === 'producto' ? 'info' : 'warning' ?>">
                        <?= ($item['tipo'] ?? 'materia_prima') === 'producto' ? 'Producto' : 'M.Prima' ?>
                    </span>
                    <span class="badge bg-danger">SERIE</span>
                </td>
                <td><?= htmlspecialchars($item['nombre']) ?></td>
                <td><?= number_format($item['pedida'], 0) ?></td>
                <td><?= number_format($item['recibida'], 0) ?></td>
                <td><?= number_format($faltante, 0) ?></td>
                <td>
                    <input type="hidden" name="tipo[<?= $index ?>]" value="<?= $item['tipo'] ?? 'materia_prima' ?>">
                    <input type="hidden" name="materia_prima_id[<?= $index ?>]" value="<?= $item['materia_prima_id'] ?? '' ?>">
                    <input type="hidden" name="producto_id[<?= $index ?>]" value="<?= $item['producto_id'] ?? '' ?>">
                    <input type="hidden" name="items[<?= $index ?>]" value="1">
                    <input type="hidden" name="usa_serie[<?= $index ?>]" value="1">
                    <span class="badge bg-secondary">1 unidad</span>
                </td>
                <td>
                    <input type="text" name="codigo_lote[<?= $index ?>]" class="form-control form-control-sm" placeholder="Lote (opcional)">
                </td>
                <td>
                    <input type="text" name="numero_serie[<?= $index ?>]" class="form-control form-control-sm" 
                           placeholder="Vacío = no recibir">
                </td>
                <td>
                    <input type="date" name="fecha_vencimiento[<?= $index ?>]" class="form-control form-control-sm fecha-vencimiento">
                </td>
            </tr>
            <?php $index++; endfor; ?>
            <?php else: ?>
            <!-- ITEM NORMAL: cantidad libre -->
            <tr>
                <td>
                    <span class="badge bg-<?= ($item['tipo'] ?? 'materia_prima') === 'producto' ? 'info' : 'warning' ?>">
                        <?= ($item['tipo'] ?? 'materia_prima') === 'producto' ? 'Producto' : 'M.Prima' ?>
                    </span>
                </td>
                <td><?= htmlspecialchars($item['nombre']) ?></td>
                <td><?= number_format($item['pedida'], 3, ',', '.') ?></td>
                <td><?= number_format($item['recibida'], 3, ',', '.') ?></td>
                <td><?= number_format($faltante, 3, ',', '.') ?></td>
                <td>
                    <?php if ($faltante > 0): ?>
                        <input type="hidden" name="tipo[<?= $index ?>]" value="<?= $item['tipo'] ?? 'materia_prima' ?>">
                        <input type="hidden" name="materia_prima_id[<?= $index ?>]" value="<?= $item['materia_prima_id'] ?? '' ?>">
                        <input type="hidden" name="producto_id[<?= $index ?>]" value="<?= $item['producto_id'] ?? '' ?>">
                        <input type="number" name="items[<?= $index ?>]" class="form-control" step="0.01" min="0" 
                            max="<?= $faltante ?>" value="<?= $faltante ?>">
                    <?php else: ?>
                        <span class="badge bg-success">Completo</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($faltante > 0): ?>
                    <input type="text" name="codigo_lote[<?= $index ?>]" class="form-control" placeholder="Lote">
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($faltante > 0): ?>
                    <input type="text" name="numero_serie[<?= $index ?>]" class="form-control" placeholder="(sin serie)">
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($faltante > 0): ?>
                    <input type="date" name="fecha_vencimiento[<?= $index ?>]" class="form-control fecha-vencimiento">
                    <?php endif; ?>
                </td>
            </tr>
            <?php $index++; endif; ?>
            <?php endforeach; ?>
        </tbody>
    </table>

    <button class="btn btn-success w-100">
        Confirmar Ingreso
    </button>

</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Alerta de fecha vencida
    document.querySelectorAll('.fecha-vencimiento').forEach(function(input) {
        input.addEventListener('change', function() {
            if (this.value && this.value < '<?= date('Y-m-d') ?>') {
                Swal.fire({
                    icon: 'warning',
                    title: 'Fecha vencida',
                    text: 'La fecha de vencimiento ingresada ya pasó. ¿Está seguro?',
                    showCancelButton: true,
                    confirmButtonText: 'Sí, continuar',
                    cancelButtonText: 'Corregir'
                }).then((result) => {
                    if (!result.isConfirmed) {
                        this.value = '';
                        this.focus();
                    }
                });
            }
        });
    });

    // Validar unicidad de números de serie cargados (recepción parcial: filas vacías se ignoran)
    document.getElementById('formIngreso').addEventListener('submit', function(e) {
        var seriesInputs = document.querySelectorAll('input[name^="numero_serie"]');
        var seriesUsadas = {};
        var duplicados = [];
        seriesInputs.forEach(function(inp) {
            var fila = inp.closest('tr');
            if (fila && fila.classList.contains('fila-serie')) {
                var val = inp.value.trim();
                if (!val) return; // fila vacía = no se recibe ahora (parcial)
                if (seriesUsadas[val]) {
                    inp.classList.add('is-invalid');
                    duplicados.push(inp);
                } else {
                    seriesUsadas[val] = true;
                    inp.classList.remove('is-invalid');
                }
            }
        });
        if (duplicados.length > 0) {
            e.preventDefault();
            Swal.fire({
                icon: 'error',
                title: 'Números de serie duplicados',
                text: 'Hay números de serie repetidos. Cada unidad debe tener un N° de serie único.'
            });
            duplicados[0].focus();
        }
    });
});
</script>