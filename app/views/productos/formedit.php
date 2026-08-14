<form method="POST" enctype="multipart/form-data"
      class="card p-4 shadow col-md-8 mx-auto">

    <h4><?= $title ?></h4>
    <input type="hidden" name="csrf_token" value="<?= Csrf::generate() ?>">
    <input class="form-control mb-2" name="nombre" placeholder="Nombre" required value="<?= htmlspecialchars($producto['nombre']) ?>">
    <input class="form-control mb-2" name="sku" placeholder="SKU" required value="<?= htmlspecialchars($producto['sku']) ?>">
    <textarea class="form-control mb-2" name="descripcion"><?= htmlspecialchars($producto['descripcion']) ?></textarea>
    <input class="form-control mb-2" name="precio_venta" type="number" step="0.01" required placeholder="Precio de Venta" value="<?= $producto['precio_venta'] ?>">
    <select class="form-control mb-2" name="unidad_medida">
        <option value="">Seleccionar Unidad de Medida</option>
        <?php foreach ($umedida as $um): ?>
            <option value="<?= $um['id_medida'] ?>" <?= $producto['unidad_medida'] == $um['id_medida'] ? 'selected' : '' ?>>
                <?= $um['nombre']. " - " . $um['detalle'] ?>
            </option>
        <?php endforeach; ?>
    </select>

    <label>Imagen:</label>
    <div class="row align-items-center mb-3">
        <div class="col-md-3 text-center">
            <?php
            $imagenActual = $producto['imagen'] ?? null;
            $rutaImagen = empresaUploadPath('productos') . '/' . $imagenActual;
            if ($imagenActual && file_exists($rutaImagen)):
            ?>
                <img id="preview-imagen" src="<?= empresaUploadUrl('productos') ?>/<?= htmlspecialchars($imagenActual) ?>"
                     class="img-fluid rounded border" style="max-height: 120px;" alt="Preview">
            <?php else: ?>
                <img id="preview-imagen" src="<?= empresaUploadUrl('productos') ?>/sin-imagen.jpg"
                     class="img-fluid rounded border" style="max-height: 120px;" alt="Preview">
            <?php endif; ?>
        </div>
        <div class="col-md-9">
            <input class="form-control" type="file" name="imagen" accept="image/*"
                   onchange="document.getElementById('preview-imagen').src = window.URL.createObjectURL(this.files[0])">
            <small class="text-muted">JPG, PNG o WebP. Max 5MB. Dejar vacio para mantener actual.</small>
        </div>
    </div>

    <h5>Códigos de Barra</h5>
<?php foreach ($barcodes as $codigo): ?>
    <div id="codigos">
        <div class="row mb-2">
            <div class="col">
                <input class="form-control" name="codigos[]" placeholder="Código" value="<?= htmlspecialchars($codigo['codigo']) ?>" readonly>
            </div>
            <div class="col">
                <input class="form-control" name="tipos[]" placeholder="Tipo (EAN, Interno)" value="<?= htmlspecialchars($codigo['tipo']) ?>" readonly>
            </div>
        </div>
    </div>
<?php endforeach; ?>
    <div class="col d-flex justify-content-end">
        <a class="btn btn-secondary me-2" href="<?= BASE_URL ?>/productos">Volver</a>
        <button class="btn btn-success me-2">Guardar</button>
        <?php if (!empty($barcodes)): ?>
        <a class="btn btn-warning" href="<?= BASE_URL ?>/productos/newbarcode/<?= $barcodes[0]['producto_id'] ?>">Nuevo Codigo</a>
        <?php endif; ?>
    </div>
</form>
