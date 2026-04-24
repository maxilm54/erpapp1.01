<form method="POST" class="card p-4 col-md-6 mx-auto" enctype="multipart/form-data">
    <h4><?= $title ?></h4>
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::generate()) ?>">
    <input name="nombre" class="form-control mb-2" required placeholder="Nombre">
    <input name="sku" class="form-control mb-2" required placeholder="SKU">
    <input name="unidad_medida" class="form-control mb-3" required placeholder="kg, g, lt">
    <select name="categoria"  class="form-control mb-3">
        <option value="">Seleccionar Categoría</option>
        <?php foreach ($categorias as $cat): ?>
            <option value="<?= $cat['id_categoria'] ?>"><?= $cat['categoria_nombre'] ?></option>
        <?php endforeach; ?>
    </select>
    <input type="file" name="imagen_mp" class="form-control mb-3">

    <button class="btn btn-success w-100">Guardar</button>
</form>