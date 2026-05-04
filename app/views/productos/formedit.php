<form method="POST" enctype="multipart/form-data"
      class="card p-4 shadow col-md-8 mx-auto">

    <h4><?= $title ?></h4>
    <input type="hidden" name="csrf_token" value="<?= Csrf::generate() ?>">
    <input class="form-control mb-2" name="nombre" placeholder="Nombre" required value="<?= $producto['nombre'] ?>">
    <input class="form-control mb-2" name="sku" placeholder="SKU" required value="<?= $producto['sku'] ?>">
    <textarea class="form-control mb-2" name="descripcion"><?= $producto['descripcion'] ?></textarea>
    <input class="form-control mb-2" name="precio_venta" type="number" step="0.01" required placeholder="Precio de Venta" value="<?= $producto['precio_venta'] ?>">
    <select class="form-control mb-2" name="unidad_medida">
        <option value="">Seleccionar Unidad de Medida</option>
        <?php foreach ($umedida as $um): ?>
            <option value="<?= $um['id_medida'] ?>" <?= $producto['unidad_medida'] == $um['id_medida'] ? 'selected' : '' ?>>
                <?= $um['nombre']. " - " . $um['detalle'] ?>
            </option>
        <?php endforeach; ?>
    </select>
    <h5>Códigos de Barra</h5>
<?php foreach ($barcodes as $codigo): ?>
    <div id="codigos">
        <div class="row mb-2">
            <div class="col">
                <input class="form-control" name="codigos[]" placeholder="Código" value="<?= $codigo['codigo'] ?>" readonly>
            </div>
            <div class="col">
                <input class="form-control" name="tipos[]" placeholder="Tipo (EAN, Interno)" value="<?= $codigo['tipo'] ?>" readonly>
            </div>
        </div>
    </div>
<?php endforeach; ?>
    <div class="col d-flex justify-content-end">
        <a class="btn btn-secondary me-2" href="<?= BASE_URL ?>/productos">Volver</a>
        <button class="btn btn-success me-2">Guardar</button>
        <a class="btn btn-warning" href="<?= BASE_URL ?>/productos/newbarcode/<?= $codigo['producto_id'] ?>">Nuevo Codigo</a>
    </div>
</form>

