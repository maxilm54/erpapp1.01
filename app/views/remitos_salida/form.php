<h1>Nuevo Remito</h1>

<p><strong>Cliente:</strong> <?= htmlspecialchars($np['cliente_nombre']) ?></p>

<form method="post">
<div class="table-scroll mt-3">
    <table class="table">
        <thead>
            <tr>
                <th>Producto</th>
                <th>Pedida</th>
                <th>Precio U</th>
                <th>Precio Subtotal</th>
                <th>Remitida</th>
                <th>Pendiente</th>
                <th>Disponible</th>
                <th width="140">Remitir ahora</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($np['detalle'] as $item): ?>
                <?php if ($item['pendiente'] <= 0) continue; ?>
<?php
                    $u = strtoupper($item['unidad_nombre'] ?? '');
                    $esUnidadEntera = in_array($u, ['U','UN','UNIDAD','UNIDADES']);
                    $step = $esUnidadEntera ? '1' : '0.01';
                ?>
                <tr>
                    <td><?= htmlspecialchars($item['nombre']) ?> <small class="text-muted">(<?= $u ?>)</small></td>
                    <td><?= number_format($item['pedida'], 3, ',', '.') ?></td>
                    <td>$ <?= number_format($item['precio'], 2, ',', '.') ?></td>
                    <td>$ <?= number_format($item['total_linea'], 2, ',', '.') ?></td>
                    <td><?= number_format($item['remitida'], 3, ',', '.') ?></td>
                    <td><?= number_format($item['pendiente'], 3, ',', '.') ?></td>
                    <td><?= number_format($item['stock_disponible'] ?? 0, 3, ',', '.') ?></td>
                    <td>
                        <input type="number"
                            step="<?= $step ?>"
                            min="0"
                            max="<?= $item['pendiente'] ?>"
                            name="items[<?= $item['producto_id'] ?>]"
                            class="form-control" required>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="mb-3">
    <label>Observaciones</label>
    <textarea name="observaciones" class="form-control"></textarea>
</div>

<button class="btn btn-success">Confirmar Remito</button>
<a href="<?= BASE_URL ?>/notaspedido/show/<?= $np['id'] ?>" class="btn btn-secondary">Ver NP</a>

</form>