<h1>Ajuste de Stock – Materia Prima</h1>

<form method="post">

    <div class="mb-3">
        <label>Materia Prima</label>
        <select name="materia_prima_id" class="form-select" required>
            <option value="">Seleccione</option>
            <?php foreach ($materias as $m): ?>
                <option value="<?= $m['id'] ?>">
                    <?= htmlspecialchars($m['nombre']) ?>
                    (Stock: <?= number_format($m['stock'], 2) ?>)
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