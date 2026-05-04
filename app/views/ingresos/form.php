<h3 class="mb-3"><?= $title ?></h3>

<form method="POST" class="card p-4 col-md-8 mx-auto">
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
    <table class="table table-bordered">
        <thead class="table-dark">
            <tr>
                <th>Materia Prima</th>
                <th>Cantidad Pedida</th>
                <th>Cantidad Recibida</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($detalle as $item): ?>
            <tr>
                <td><?= htmlspecialchars($item['nombre']) ?></td>

                <td>
                    <?= $item['pedida'] ?> <?= $item['unidad_medida'] ?><br>
                    <small class="text-muted">
                        Recibido: <?= $item['recibida'] ?> |
                        Faltante: <?= $item['faltante'] ?>
                    </small>
                </td>

                <td>
                    <?php if ($item['faltante'] > 0): ?>
                        <input type="number"
                            name="items[<?= $item['materia_prima_id'] ?>]"
                            class="form-control"
                            step="0.01"
                            max="<?= $item['faltante'] ?>"
                            value="<?= $item['faltante'] ?>">
                    <?php else: ?>
                        <span class="badge bg-success">Completo</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <button class="btn btn-success w-100">
        Confirmar Ingreso
    </button>

</form>