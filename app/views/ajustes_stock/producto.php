<h1>Ajuste de Stock – Producto</h1>

<form method="post">

    <div class="mb-3">
        <label>Producto</label>
        <select name="producto_id" class="form-select" required>
            <option value="">Seleccione</option>
            <?php foreach ($productos as $p): ?>
                <option value="<?= $p['id'] ?>">
                    <?= htmlspecialchars($p['nombre']) ?>
                    (Stock: <?= number_format($p['stock'], 2) ?>)
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="mb-3">
        <label>Cantidad (+ / -)</label>
        <input type="number"
               name="cantidad"
               class="form-control"
               step="0.01"
               required>
    </div>

    <div class="mb-3">
        <label>Motivo del ajuste</label>
        <textarea name="motivo"
                  class="form-control"
                  rows="3"
                  required></textarea>
    </div>

    <button class="btn btn-warning">Aplicar Ajuste</button>
    <a href="<?= BASE_URL ?>" class="btn btn-secondary">Cancelar</a>

</form>