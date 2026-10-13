<?php $esVenta = $mov['tipo'] === 'VENTA'; ?>

<h4><i class="bi bi-boxes"></i> Registrar <?= $esVenta ? 'Salida' : 'Entrada' ?> de Mercaderia - Comprobante #<?= $mov['id'] ?></h4>
<hr>

<?php if (!empty($_SESSION['error'])): ?>
<div class="alert alert-danger"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
<?php endif; ?>

<div class="row">
    <div class="col-md-6">
        <div class="card mb-3">
            <div class="card-body">
                <table class="table table-sm table-borderless mb-0">
                    <tr><td class="text-muted" style="width:140px">Tipo</td><td><span class="badge <?= $esVenta ? 'bg-info' : 'bg-warning text-dark' ?>"><?= $esVenta ? 'Salida' : 'Entrada' ?></span></td></tr>
                    <tr><td class="text-muted">Cliente/Prov.</td><td><?= htmlspecialchars($mov['razon_social'] ?? '-') ?></td></tr>
                    <tr><td class="text-muted">Estado</td><td><?= $mov['estado'] ?></td></tr>
                </table>
            </div>
        </div>
    </div>
</div>

<form method="post" id="form-movimiento-stock" class="row g-3">
    <div class="col-12">
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <strong>Items a mover a stock</strong>
                <span class="text-muted">Ingrese las cantidades a registrar</span>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm table-striped mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th style="width:5%">#</th>
                            <th style="width:10%">Tipo</th>
                            <th>Producto / Materia Prima</th>
                            <th class="text-end">Cant. Comp.</th>
                            <th class="text-end">Ya registrado</th>
                            <th class="text-end">Pendiente</th>
                            <th style="width:15%" class="text-end">Cant. a mover</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $idx = 0; $tieneItemsStock = false; foreach ($mov['detalle'] as $d):
                            $tipoItem = $d['tipo_item'] ?? '';
                            if ($tipoItem === 'MANUAL') continue;

                            $tieneItemsStock = true;

                            if ($tipoItem === 'MATERIAPRIMA') {
                                $tipoLabel = 'MP';
                                $tipoClass = 'bg-warning text-dark';
                            } else {
                                $tipoLabel = 'PROD';
                                $tipoClass = 'bg-info';
                            }

                            $key = $d['producto_id'] ? 'p' . $d['producto_id'] : 'mp' . $d['materia_prima_id'];
                            $yaReg = (float)($registradoPorItem[$key] ?? 0);
                            $cantOriginal = (float)$d['cantidad'];
                            $pendiente = max(0, $cantOriginal - $yaReg);
                            $completo = $pendiente <= 0;
                        ?>
                        <tr id="row-<?= $idx ?>" data-pendiente="<?= $pendiente ?>">
                            <td><?= $idx + 1 ?></td>
                            <td><span class="badge <?= $tipoClass ?>"><?= $tipoLabel ?></span></td>
                            <td>
                                <?= htmlspecialchars($d['item_nombre'] ?? $d['descripcion'] ?? 'Sin nombre') ?>
                                <input type="hidden" name="items[<?= $idx ?>][producto_id]" value="<?= $d['producto_id'] ?? '' ?>">
                                <input type="hidden" name="items[<?= $idx ?>][materia_prima_id]" value="<?= $d['materia_prima_id'] ?? '' ?>">
                                <input type="hidden" name="items[<?= $idx ?>][tipo_item]" value="<?= $tipoItem ?>">
                                <input type="hidden" name="items[<?= $idx ?>][item_nombre]" value="<?= htmlspecialchars($d['item_nombre'] ?? $d['descripcion'] ?? 'Item') ?>">
                            </td>
                            <td class="text-end"><?= number_format($cantOriginal, 2, ',', '.') ?></td>
                            <td class="text-end"><?= number_format($yaReg, 2, ',', '.') ?></td>
                            <td class="text-end pendiente-display" id="pendiente-<?= $idx ?>"><?= number_format($pendiente, 2, ',', '.') ?></td>
                            <td>
                                <?php if ($completo): ?>
                                <span class="badge bg-success w-100">Agotado</span>
                                <input type="hidden" name="items[<?= $idx ?>][cantidad]" value="0">
                                <?php else: ?>
                                <input type="number" name="items[<?= $idx ?>][cantidad]"
                                       class="form-control form-control-sm text-end input-cantidad"
                                       step="0.01" min="0" max="<?= $pendiente ?>"
                                       value="<?= $pendiente ?>"
                                       data-idx="<?= $idx ?>" data-pendiente="<?= $pendiente ?>"
                                       oninput="actualizarPendiente(this)" required>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php $idx++; endforeach; ?>

                        <?php if (!$tieneItemsStock): ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted py-3">
                                Este comprobante no tiene productos ni materias primas para mover a stock.
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php if ($tieneItemsStock): ?>
    <div class="col-12">
        <button type="submit" class="btn btn-success">
            <i class="bi bi-boxes"></i> Registrar Movimiento de Stock
        </button>
        <a href="<?= BASE_URL ?>/sdcomp/show/<?= $mov['id'] ?>" class="btn btn-secondary">Cancelar</a>
    </div>
    <?php else: ?>
    <div class="col-12">
        <a href="<?= BASE_URL ?>/sdcomp/show/<?= $mov['id'] ?>" class="btn btn-secondary">Volver</a>
    </div>
    <?php endif; ?>
</form>

<script>
let pendientesOriginales = {};
document.querySelectorAll('.input-cantidad').forEach(function(inp) {
    const idx = inp.dataset.idx;
    pendientesOriginales[idx] = parseFloat(inp.dataset.pendiente);
});

function actualizarPendiente(input) {
    const idx = input.dataset.idx;
    const ingresado = parseFloat(input.value) || 0;
    const pendOrig = pendientesOriginales[idx];
    const nuevoPend = Math.max(0, pendOrig - ingresado);
    const td = document.getElementById('pendiente-' + idx);
    if (td) {
        td.textContent = nuevoPend.toFixed(2).replace('.', ',');
        td.className = 'text-end pendiente-display' + (nuevoPend <= 0 ? ' text-success fw-bold' : '');
    }
    if (ingresado > pendOrig) {
        input.classList.add('is-invalid');
    } else {
        input.classList.remove('is-invalid');
    }
    // Verificar si todos los pendientes estan agotados
    verificarTodoAgotado();
}

function verificarTodoAgotado() {
    const inputs = document.querySelectorAll('.input-cantidad');
    let todoAgotado = true;
    let algunValor = false;
    inputs.forEach(function(inp) {
        const idx = inp.dataset.idx;
        const pendOrig = pendientesOriginales[idx];
        const ingresado = parseFloat(inp.value) || 0;
        if (ingresado > 0) algunValor = true;
        if ((pendOrig - ingresado) > 0.001) todoAgotado = false;
    });
    if (!algunValor) {
        todoAgotado = false;
    }
    const btn = document.getElementById('form-movimiento-stock').querySelector('button[type="submit"]');
    if (todoAgotado) {
        btn.innerHTML = '<i class="bi bi-check-lg"></i> Todo agotado, no hay nada que mover';
        btn.disabled = true;
        btn.className = 'btn btn-secondary';
    }
}

document.getElementById('form-movimiento-stock').addEventListener('submit', function(e) {
    const inputs = document.querySelectorAll('.input-cantidad');
    let ok = true;
    let mensaje = '';
    inputs.forEach(function(inp) {
        const idx = inp.dataset.idx;
        const pendOrig = pendientesOriginales[idx];
        const val = parseFloat(inp.value) || 0;
        if (val > pendOrig) {
            ok = false;
            mensaje = 'Hay cantidades que superan el pendiente disponible. Verifique los valores en rojo.';
            inp.classList.add('is-invalid');
        }
    });
    if (!ok) {
        e.preventDefault();
        alert(mensaje);
    }
});
</script>