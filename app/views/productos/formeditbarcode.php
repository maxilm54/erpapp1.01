<form method="POST" enctype="multipart/form-data"
      class="card p-4 shadow col-md-8 mx-auto">

    <h4><?= $title ?></h4>
    <label class="form-control mb-2"><?= $producto['nombre'] ?></label>
    <label class="form-control mb-2"><?= $producto['sku'] ?></label>
    <label class="form-control mb-2"><?= $producto['descripcion'] ?></label>
    <label class="form-control mb-2"><?= $producto['unidad_medida'] ?></label>
    <h5>Códigos de Barra</h5>
<?php foreach ($barcodes as $codigo): ?>
    <div id="codigos">
        <input type="hidden" class="form-control" name="ids[]" placeholder="id" value="<?= $codigo['id'] ?>" readonly>
        <div class="row mb-2">
            <div class="col">
                <input class="form-control" name="codigos[]" placeholder="Código" value="<?= $codigo['codigo'] ?>">
            </div>
            <div class="col">
                <input type="text" class="form-control" name="tipos[]" placeholder="Tipo (EAN, Interno)" value="<?= $codigo['tipo'] ?>">
            </div>
        </div>
    </div>
<?php endforeach; ?>
<div class="row">
    <div class="col d-flex justify-content-end">
        <a class="btn btn-secondary me-2" href="<?= BASE_URL ?>/productos">Volver</a>
        <button class="btn btn-success me-2">Guardar</button>
        <a class="btn btn-warning" href="<?= BASE_URL ?>/productos/newbarcode/<?= $codigo['producto_id'] ?>">Nuevo Codigo</a>
    </div>
    
    
</div>
</form>

