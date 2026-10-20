<h1>Stock de Productos</h1>

<form method="GET" class="row mb-3">
    <div class="col-md-4">
        <select name="deposito_id" class="form-select" onchange="this.form.submit()">
            <option value="">Todos los depósitos</option>
            <?php foreach ($depositos as $d): ?>
            <option value="<?= $d['id'] ?>" <?= ($deposito_id == $d['id']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($d['nombre']) ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>
</form>

<div class="table-scroll mt-3">
    <table class="table table-bordered table-hover">
        <thead>
            <tr>
                <th>SKU</th>
                <th>Producto</th>
                <th class="text-center">Stock</th>
                <th class="text-center">Stock Mínimo</th>
                <th class="text-center">Stock Máximo</th>
                <th class="text-center">Estado</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($stock as $p): ?>
            <?php
                $badgeClass = match($p['estado'] ?? 'normal') {
                    'critico' => 'bg-danger',
                    'bajo' => 'bg-warning text-dark',
                    'alto' => 'bg-primary',
                    default => 'bg-success'
                };
            ?>
            <tr>
                <td><?= htmlspecialchars($p['sku']) ?></td>
                <td><?= htmlspecialchars($p['nombre']) ?></td>
                <td class="text-center"><?= number_format($p['stock'], 2, ',', '.') ?></td>
                <td class="text-center"><?= number_format($p['stock_minimo'], 2, ',', '.') ?></td>
                <td class="text-center"><?= number_format($p['stock_maximo'], 2, ',', '.') ?></td>
                <td class="text-center">
                    <span class="badge <?= $badgeClass ?>"><?= strtoupper($p['estado'] ?? 'NORMAL') ?></span>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<hr>
<a href="<?= BASE_URL ?>ajustesstock" class="btn btn-primary">Volver</a>