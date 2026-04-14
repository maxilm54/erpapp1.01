 <h4><?= $title ?></h4>

<form method="POST" class="card p-4 shadow col-md-8 mx-auto">
    <input type="hidden" name="id" value="<?= $producto['id'] ?>">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
    <div class="row">
        <div class="col-6">
            <input class="form-control mt-2" name="nombre" placeholder="Nombre" required value="<?= $producto['nombre'] ?>" readonly>
            <small class="form-text text-muted">Nombre del producto</small>
        </div>
        <div class="col-6">
            <textarea class="form-control" name="descripcion" readonly><?= $producto['descripcion'] ?></textarea>
            <small class="form-text text-muted">Descripción del producto</small>
        </div>
    </div>
    <div class="row">
        <div class="col-4">
            <input class="form-control" name="sku" placeholder="SKU" value="<?= $producto['sku'] ?>" readonly>
            <small class="form-text text-muted">SKU del producto</small>
        </div>
        <div class="col-4">
            <input class="form-control" name="unidad_medida" placeholder="Unidad de Medida (UNIDAD - KG - PACK)" required value="<?= $producto['unidad_medida'] ?>" readonly>
            <small class="form-text text-muted">Unidad de Medida</small>
        </div>
        <div class="col-4">
            <input class="form-control" name="precio_venta" type="number" step="0.01" placeholder="Precio de Venta" value="<?= $producto['precio_venta'] ?>" readonly>
            <small class="form-text text-muted">Precio de Venta</small>
        </div>
    </div>
    <div class="row mb-2">
        <div class="col-3">
            
            <input type="number" class="form-control" name="stock_actual" value="<?= $stockmovements['stock'] ?>"/>
            <small class="form-text text-muted">Stock actual</small>
        </div>
        <div class="col-3">
            <input type="number" class="form-control" name="stock_minimo" value="<?= $producto['stock_minimo'] ?>"/>
            <small class="form-text text-muted">Stock mínimo</small>
        </div>
        <div class="col-3">
            <input type="number" class="form-control" name="stock_maximo" value="<?= $producto['stock_critico'] ?>"/>
            <small class="form-text text-muted">Stock Crítico</small>
        </div>
        <div class="col-3">
            <input type="number" class="form-control" name="stock_actual" value="<?= $producto['stock_maximo'] ?>"/>
            <small class="form-text text-muted">Stock Máximo</small>
        </div>
    </div>
    <div class="col d-flex justify-content-end">
        <a class="btn btn-secondary me-2" href="<?= BASE_URL ?>/productos">Volver</a>
        <button class="btn btn-success me-2">Guardar</button>
    </div>
</form>