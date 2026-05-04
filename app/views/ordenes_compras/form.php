<h3 class="mb-3"><?= $title ?></h3>

<form method="POST" class="card p-4">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::generate()) ?>">
    <div class="mb-3">
        <label class="form-label">Proveedor</label>
        <select name="proveedor_id" class="form-select" required>
            <option value="">Seleccione proveedor</option>
            <?php foreach ($proveedores as $p): ?>
                <option value="<?= $p['id'] ?>"
                    <?= isset($orden) && $orden['proveedor_id']==$p['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($p['razon_social']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <h5>Detalle</h5>
    <div class="table-scroll mt-3">
        <table class="table">
            <thead>
                <tr>
                    <th>Materia Prima</th>
                    <th>Cantidad</th>
                    <th>Precio Unitario</th>
                    <th>Moneda</th>
                </tr>
            </thead>
            <tbody>
                <?php
                    $items = $detalle ?? $materias_primas;
                    foreach ($items as $i => $mp): 
                ?>
                <tr>
                    <td>
                        <?= htmlspecialchars($mp['nombre']) ?>
                        <input type="hidden"
                            name="items[<?= $i ?>][materia_prima_id]"
                            value="<?= $mp['materia_prima_id'] ?? $mp['id'] ?>">
                    </td>
                    <td>
                        <input type="number" step="0.001" min="0"
                            name="items[<?= $i ?>][cantidad]"
                            class="form-control" value="<?= $mp['cantidad'] ?? '' ?>">
                    </td>
                    <td>
                        <input type="number" step="0.001" min="0"
                            name="items[<?= $i ?>][precio_unitario]"
                            class="form-control" value="<?= $mp['precio_unitario'] ?? '' ?>">
                    </td>
                    <td>
                        <select name="items[<?= $i ?>][moneda]" class="form-select">
                            <option value="1" <?= ($mp['moneda'] ?? '1') == '$' ? 'selected' : '' ?>>$</option>
                            <option value="2" <?= ($mp['moneda'] ?? '2') == 'USD' ? 'selected' : '' ?>>USD</option>
                            <option value="3" <?= ($mp['moneda'] ?? '3') == '€' ? 'selected' : '' ?>>€</option>
                        </select>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <button class="btn btn-primary w-100">Guardar Orden de Compra</button>
</form>