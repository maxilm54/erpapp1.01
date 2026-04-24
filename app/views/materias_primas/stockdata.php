 <h4><?= $title ?></h4>

<form method="POST" class="card p-4 shadow col-md-8 mx-auto">
    <input type="hidden" name="id" value="<?= $item['id'] ?>">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
    <div class="row">
        <div class="col-4">
            <small class="form-text text-muted">Materia Prima: <?= $item['nombre'] ?></small>
        </div>
        <div class="col-4">
            <small class="form-text text-muted">SKU: <?= $item['sku'] ?></small>
        </div>
        <div class="col-4">
            <small class="form-text text-muted">Unidad de Medida: <?= $item['nombre_unidad'] ?></small>
        </div>
    </div>
    <div class="row mb-2">
        <div class="col-3">
            <small class="form-text text-muted">Stock actual: <?= $stockmovements['stock'] ?></small>
        </div>
        <div class="col-3">
            <input type="number" class="form-control" name="stock_minimo" value="<?= $item['stock_minimo'] ?>" step="0.01"/>
            <small class="form-text text-muted">Stock mínimo</small>
        </div>
        <div class="col-3">
            <input type="number" class="form-control" name="stock_critico" value="<?= $item['stock_critico'] ?>" step="0.01"/>
            <small class="form-text text-muted">Stock Crítico</small>
        </div>
        <div class="col-3">
            <input type="number" class="form-control" name="stock_maximo" value="<?= $item['stock_maximo'] ?>" step="0.01"/>
            <small class="form-text text-muted">Stock Máximo</small>
        </div>
    </div>
    <div class="col d-flex justify-content-end">
        <a class="btn btn-secondary me-2" href="<?= BASE_URL ?>/materiasprimas">Volver</a>
        <button class="btn btn-success me-2">Guardar</button>
    </div>
</form>