<form method="POST" action="<?= BASE_URL ?>/materiasprimas/create" class="card p-4 col-lg-8 mx-auto" enctype="multipart/form-data">
    <h4><?= htmlspecialchars($title) ?></h4>
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::generate()) ?>">

    <div class="row">
        <div class="col-md-6">
            <label class="form-label" for="nombre">Nombre <span class="text-danger">*</span></label>
            <input name="nombre" id="nombre" class="form-control mb-2" required maxlength="150" placeholder="Nombre">
        </div>
        <div class="col-md-6">
            <label class="form-label" for="sku">SKU <span class="text-danger">*</span></label>
            <input name="sku" id="sku" class="form-control mb-2" required maxlength="100" placeholder="SKU">
        </div>
    </div>

    <label class="form-label" for="idUnidadMedida">Unidad de medida <span class="text-danger">*</span></label>
    <select name="id_unidadmedida" id="idUnidadMedida" class="form-select mb-2" required>
        <option value="">Seleccionar Unidad de Medida</option>
        <?php foreach ($umedida as $um): ?>
            <option value="<?= (int)$um['id_medida'] ?>" data-nombre="<?= htmlspecialchars($um['nombre']) ?>"><?= htmlspecialchars($um['nombre'] . ' (' . $um['detalle'] . ')') ?></option>
        <?php endforeach; ?>
    </select>

    <?php require __DIR__ . '/_compra.php'; ?>

    <label class="form-label" for="categoria">Categoría</label>
    <select name="categoria" id="categoria" class="form-select mb-3">
        <option value="">Sin categoría</option>
        <?php foreach ($categorias as $cat): ?>
            <option value="<?= (int)$cat['id_categoria'] ?>"><?= htmlspecialchars($cat['categoria_nombre']) ?></option>
        <?php endforeach; ?>
    </select>

    <?php require __DIR__ . '/_especificaciones.php'; ?>

    <div class="form-check mb-3">
        <input class="form-check-input" type="checkbox" name="usa_serie" value="1" id="usaSerieMP">
        <label class="form-check-label" for="usaSerieMP">
            <strong>Controla N° de Serie</strong> — cada unidad se recepciona y remite individualmente con su número de serie propio.
        </label>
    </div>

    <label class="form-label">Imagen:</label>
    <div class="row align-items-center mb-3">
        <div class="col-md-3 text-center">
            <img id="preview-imagen-mp" src="<?= empresaUploadUrl('materiasprimas') ?>/sin-imagen.jpg"
                 class="img-fluid rounded border" style="max-height: 120px;" alt="Preview">
        </div>
        <div class="col-md-9">
            <input type="file" name="imagen_mp" class="form-control" accept="image/*"
                   onchange="document.getElementById('preview-imagen-mp').src = window.URL.createObjectURL(this.files[0])">
            <small class="text-muted">JPG, PNG o WebP. Max 5MB.</small>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <label class="form-label" for="barcode">Código de barras</label>
            <input type="text" name="barcode" id="barcode" class="form-control mb-3" placeholder="Opcional">
        </div>
        <div class="col-md-6">
            <label class="form-label" for="tipoCodigo">Tipo de código</label>
            <input type="text" name="tipo" id="tipoCodigo" class="form-control mb-3" placeholder="EAN, Interno, etc.">
        </div>
    </div>

    <div class="d-flex justify-content-end">
        <a class="btn btn-secondary me-2" href="<?= BASE_URL ?>/materiasprimas">Volver</a>
        <button class="btn btn-success me-2">Guardar</button>
    </div>
</form>
