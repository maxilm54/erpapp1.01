<form method="POST" class="card p-4 col-md-6 mx-auto" enctype="multipart/form-data">
    <h4><?= $title ?></h4>
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::generate()) ?>">
    <span class="small">Nombre:</span>
        <input name="nombre" class="form-control mb-2" required value="<?= htmlspecialchars($item['nombre'] ?? '') ?>">
    
    <span class="small">SKU:</span>
    <input name="sku" class="form-control mb-2" required value="<?= htmlspecialchars($item['sku'] ?? '') ?>">

    <span class="small">Categoría:</span>
    <select name="categoria" class="form-control mb-2" required>
        <option value="">Seleccione una categoría</option>
        <?php foreach ($categorias as $cat): ?>
            <option value="<?= htmlspecialchars($cat['id_categoria']) ?>" <?= ($item['categoria'] ?? '') == $cat['id_categoria'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($cat['categoria_nombre']) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <span class="small">Unidad de Medida:</span>
    <select name="unidad_medida" class="form-control mb-3" required>
        <option value="">Seleccione una unidad de medida</option>
        <?php foreach ($umedida as $um): ?>
            <option value="<?= htmlspecialchars($um['id_medida']) ?>" <?= ($item['id_unidadmedida'] ?? '') == $um['id_medida'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($um['nombre']) ?> (<?= htmlspecialchars($um['detalle']) ?>)
            </option>
        <?php endforeach; ?>
    </select>

    <label>Imagen:</label>
    <div class="row align-items-center mb-3">
        <div class="col-md-3 text-center">
            <?php
            $imagenActual = $item['imagen'] ?? null;
            $rutaImagen = empresaUploadPath('materiasprimas') . '/' . $imagenActual;
            if ($imagenActual && file_exists($rutaImagen)):
            ?>
                <img id="preview-imagen-mp" src="<?= empresaUploadUrl('materiasprimas') ?>/<?= htmlspecialchars($imagenActual) ?>"
                     class="img-fluid rounded border" style="max-height: 120px;" alt="Preview">
            <?php else: ?>
                <img id="preview-imagen-mp" src="<?= empresaUploadUrl('materiasprimas') ?>/sin-imagen.jpg"
                     class="img-fluid rounded border" style="max-height: 120px;" alt="Preview">
            <?php endif; ?>
        </div>
        <div class="col-md-9">
            <input type="file" name="imagen_mp" class="form-control" accept="image/*"
                   onchange="document.getElementById('preview-imagen-mp').src = window.URL.createObjectURL(this.files[0])">
            <small class="text-muted">JPG, PNG o WebP. Max 5MB. Dejar vacio para mantener actual.</small>
        </div>
    </div>

    <div class="d-flex justify-content-end">
        <a class="btn btn-secondary me-2" href="<?= BASE_URL ?>/materiasprimas">Volver</a>
        <button class="btn btn-success">Guardar</button>
    </div>
</form>
