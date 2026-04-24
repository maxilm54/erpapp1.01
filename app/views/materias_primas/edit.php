<form method="POST" class="card p-4 col-md-6 mx-auto">
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

    <button class="btn btn-success w-100">Guardar</button>
</form>