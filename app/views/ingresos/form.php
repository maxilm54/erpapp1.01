<h3 class="mb-3"><?= $title ?></h3>

<form method="POST" class="card p-4 col-md-8 mx-auto">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::generate()) ?>">
    <div class="mb-3">
        <label class="form-label">Proveedor: <?= htmlspecialchars($proveedor) ?> #OC: <?= $orden_compra_id ?></label>
    </div>
    <div class="mb-3">
        <label class="form-label">Remito</label>
        <input type="text" name="remito" class="form-control" placeholder="00000-00000000" pattern="\d{5}-\d{8}" required>
    </div>
    <table class="table table-bordered">
        <thead class="table-dark">
            <tr>
                <th>Tipo</th>
                <th>Nombre</th>
                <th>Cantidad Pedida</th>
                <th>Recibida</th>
                <th>Faltante</th>
                <th>Ingresar</th>
            </tr>
        </thead>
        <tbody>
            <?php $index = 0; foreach ($detalle as $item): ?>
            <tr>
                <td>
                    <span class="badge bg-<?= ($item['tipo'] ?? 'materia_prima') === 'producto' ? 'info' : 'warning' ?>">
                        <?= ($item['tipo'] ?? 'materia_prima') === 'producto' ? 'Producto' : 'Materia Prima' ?>
                    </span>
                </td>
                <td><?= htmlspecialchars($item['nombre']) ?></td>
                <td><?= number_format($item['pedida'], 3, ',', '.') ?></td>
                <td><?= number_format($item['recibida'], 3, ',', '.') ?></td>
                <td><?= number_format($item['faltante'], 3, ',', '.') ?></td>
                <td>
                    <?php if ($item['faltante'] > 0): ?>
                        <input type="hidden" name="tipo[<?= $index ?>]" value="<?= $item['tipo'] ?? 'materia_prima' ?>">
                        <input type="hidden" name="materia_prima_id[<?= $index ?>]" value="<?= $item['materia_prima_id'] ?? '' ?>">
                        <input type="hidden" name="producto_id[<?= $index ?>]" value="<?= $item['producto_id'] ?? '' ?>">
                        <input type="number" name="items[<?= $index ?>]" class="form-control" step="0.01" min="0" 
                            max="<?= $item['faltante'] ?>" value="<?= $item['faltante'] ?>">
                    <?php else: ?>
                        <span class="badge bg-success">Completo</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php $index++; endforeach; ?>
        </tbody>
    </table>

    <button class="btn btn-success w-100">
        Confirmar Ingreso
    </button>

</form>