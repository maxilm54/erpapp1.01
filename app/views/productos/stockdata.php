 <h4><?= $title ?></h4>

<form method="POST" class="card p-4 shadow col-md-8 mx-auto">
    <input type="hidden" name="id" value="<?= $producto['id'] ?>">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
    <div class="row">
        <div class="col-4">
            <small class="form-text text-muted">Producto: <?= $producto['nombre'] ?></small>
        </div>
        <div class="col-4">
            <small class="form-text text-muted">SKU: <?= $producto['sku'] ?></small>
        </div>
        <div class="col-4">
            <small class="form-text text-muted">Unidad de Medida: <?= $producto['unidad_medida'] ?></small>
        </div>
    </div>
    <div class="row">
        <div class="col-6">
            <small class="form-text text-muted">Descripción: <?= $producto['descripcion'] ?></small>
        </div>
        
        <div class="col-4">
            <small class="form-text text-muted">Precio de Venta: <?= $producto['precio_venta'] ?></small>
        </div>
    </div>
    <div class="row mb-2">
        <div class="col-3">
            <small class="form-text text-muted">Stock actual: <?= $stockmovements['stock'] ?></small>
        </div>
        <div class="col-3">
            <input type="number" class="form-control" name="stock_minimo" value="<?= $producto['stock_minimo'] ?>" step="0.01"/>
            <small class="form-text text-muted">Stock mínimo</small>
        </div>
        <div class="col-3">
            <input type="number" class="form-control" name="stock_critico" value="<?= $producto['stock_critico'] ?>" step="0.01"/>
            <small class="form-text text-muted">Stock Crítico</small>
        </div>
        <div class="col-3">
            <input type="number" class="form-control" name="stock_maximo" value="<?= $producto['stock_maximo'] ?>" step="0.01"/>
            <small class="form-text text-muted">Stock Máximo</small>
        </div>
    </div>
    <div class="col d-flex justify-content-end">
        <a class="btn btn-secondary me-2" href="<?= BASE_URL ?>/productos">Volver</a>
        <button class="btn btn-success me-2">Guardar</button>
    </div>
</form>