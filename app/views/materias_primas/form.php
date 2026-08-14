<form method="POST" class="card p-4 col-md-6 mx-auto" enctype="multipart/form-data">
    <h4><?= $title ?></h4>
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::generate()) ?>">
    <input name="nombre" class="form-control mb-2" required placeholder="Nombre">
    <input name="sku" class="form-control mb-2" required placeholder="SKU">

    <select name="id_unidadmedida" class="form-control mb-3">
        <option value="">Seleccionar Unidad de Medida</option>
        <?php foreach ($umedida as $um): ?>
            <option value="<?= $um['id_medida'] ?>"><?= $um['nombre'].' ('.$um['detalle'].')' ?></option>
        <?php endforeach; ?>
    </select>

    <select name="categoria" class="form-control mb-3">
        <option value="">Seleccionar Categoría</option>
        <?php foreach ($categorias as $cat): ?>
            <option value="<?= $cat['id_categoria'] ?>"><?= $cat['categoria_nombre'] ?></option>
        <?php endforeach; ?>
    </select>

    <label>Imagen:</label>
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
        <div class="col">
            <input type="text" name="barcode" class="form-control mb-3" placeholder="Código de Barra (opcional)">
        </div>
        <div class="col">
            <input type="text" name="tipo" class="form-control mb-3" placeholder="Tipo de Código (EAN, Interno, etc.)">
        </div>
    </div>
    <div class="col d-flex justify-content-end">
        <a class="btn btn-secondary me-2" href="<?= BASE_URL ?>/materiasprimas">Volver</a>
        <button class="btn btn-success me-2">Guardar</button>
    </div>
    
</form>
